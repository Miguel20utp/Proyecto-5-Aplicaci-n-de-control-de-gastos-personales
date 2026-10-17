"""Control de Gastos Personales — aplicación local de un solo usuario.

Requiere Streamlit:  py -m pip install streamlit
Ejecución:           py -m streamlit run app.py

Los importes se almacenan como centavos enteros en SQLite y solo se convierten
a Decimal al validar o presentar valores monetarios.
"""
from __future__ import annotations

import base64
import csv
import hashlib
import hmac
import io
import secrets
import sqlite3
import struct
import time
import unicodedata
from datetime import date
from decimal import Decimal, InvalidOperation
from pathlib import Path
from urllib.parse import quote

try:
    import streamlit as st
except ImportError as exc:
    raise SystemExit(
        "Falta Streamlit. Instálalo con: py -m pip install streamlit\n"
        "Luego ejecuta: py -m streamlit run app.py"
    ) from exc

import pandas as pd


APP_DIR = Path(__file__).resolve().parent
DB_PATH = APP_DIR / "gastos_personales.db"
CENTAVO = Decimal("0.01")
TIPOS = ("INGRESO", "GASTO")
CATEGORIAS = (
    ("Salario", "INGRESO"), ("Otros ingresos", "INGRESO"),
    ("Alimentación", "GASTO"), ("Transporte", "GASTO"),
    ("Vivienda", "GASTO"), ("Salud", "GASTO"), ("Ocio", "GASTO"),
)
NOMBRES_TIPO = {"INGRESO": "Ingreso", "GASTO": "Gasto"}
MESES = ("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
         "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre")


def conectar() -> sqlite3.Connection:
    con = sqlite3.connect(DB_PATH, timeout=10)
    con.row_factory = sqlite3.Row
    con.execute("PRAGMA foreign_keys = ON")
    con.execute("PRAGMA journal_mode = WAL")
    con.executescript("""
        CREATE TABLE IF NOT EXISTS categoria (
            id INTEGER PRIMARY KEY,
            nombre TEXT NOT NULL UNIQUE,
            tipo TEXT NOT NULL CHECK (tipo IN ('INGRESO','GASTO'))
        );
        CREATE TABLE IF NOT EXISTS periodo (
            id INTEGER PRIMARY KEY,
            anio INTEGER NOT NULL,
            mes INTEGER NOT NULL CHECK (mes BETWEEN 1 AND 12),
            UNIQUE (anio, mes)
        );
        CREATE TABLE IF NOT EXISTS transaccion (
            id INTEGER PRIMARY KEY,
            tipo TEXT NOT NULL CHECK (tipo IN ('INGRESO','GASTO')),
            monto_centavos INTEGER NOT NULL CHECK (monto_centavos > 0),
            fecha TEXT NOT NULL,
            descripcion TEXT NOT NULL DEFAULT '',
            categoria_id INTEGER NOT NULL REFERENCES categoria(id),
            periodo_id INTEGER NOT NULL REFERENCES periodo(id),
            creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_transaccion_periodo
            ON transaccion(periodo_id, fecha);
        CREATE TRIGGER IF NOT EXISTS categoria_tipo_insert
        BEFORE INSERT ON transaccion
        WHEN (SELECT tipo FROM categoria WHERE id=NEW.categoria_id) != NEW.tipo
        BEGIN SELECT RAISE(ABORT, 'La categoría no corresponde al tipo'); END;
        CREATE TRIGGER IF NOT EXISTS categoria_tipo_update
        BEFORE UPDATE OF tipo, categoria_id ON transaccion
        WHEN (SELECT tipo FROM categoria WHERE id=NEW.categoria_id) != NEW.tipo
        BEGIN SELECT RAISE(ABORT, 'La categoría no corresponde al tipo'); END;
        CREATE TABLE IF NOT EXISTS usuario (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            correo TEXT NOT NULL UNIQUE,
            sal BLOB NOT NULL,
            hash_contrasena BLOB NOT NULL,
            secreto_totp TEXT NOT NULL,
            intentos INTEGER NOT NULL DEFAULT 0,
            bloqueado_hasta INTEGER NOT NULL DEFAULT 0,
            ultimo_totp INTEGER NOT NULL DEFAULT -1
        );
    """)
    con.executemany("INSERT OR IGNORE INTO categoria(nombre,tipo) VALUES (?,?)", CATEGORIAS)
    con.commit()
    return con


def monto_a_centavos(valor: str) -> int:
    texto = str(valor).strip().replace(" ", "")
    if "," in texto and "." in texto:
        # Acepta tanto 1.234,56 como 1,234.56.
        if texto.rfind(",") > texto.rfind("."):
            texto = texto.replace(".", "").replace(",", ".")
        else:
            texto = texto.replace(",", "")
    elif "," in texto:
        texto = texto.replace(",", ".")
    try:
        valor_decimal = Decimal(texto)
    except (InvalidOperation, ValueError):
        raise ValueError("Escribe un monto numérico, por ejemplo 12500,50.")
    if not valor_decimal.is_finite() or valor_decimal <= 0:
        raise ValueError("El monto debe ser mayor que cero.")
    if valor_decimal != valor_decimal.quantize(CENTAVO):
        raise ValueError("El monto admite máximo dos decimales.")
    centavos = int(valor_decimal * 100)
    if centavos > 9_000_000_000_000_000_000:
        raise ValueError("El monto supera el máximo permitido.")
    return centavos


def a_decimal(centavos: int) -> Decimal:
    return (Decimal(int(centavos)) / 100).quantize(CENTAVO)


def moneda(centavos: int | Decimal) -> str:
    valor = a_decimal(int(centavos)) if isinstance(centavos, int) else centavos
    texto = f"{valor:,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")
    return f"$ {texto}"


def fecha_periodo(con: sqlite3.Connection, fecha: date) -> int:
    con.execute("INSERT OR IGNORE INTO periodo(anio,mes) VALUES (?,?)", (fecha.year, fecha.month))
    return int(con.execute("SELECT id FROM periodo WHERE anio=? AND mes=?",
                           (fecha.year, fecha.month)).fetchone()[0])


def guardar_transaccion(con: sqlite3.Connection, tipo: str, monto: str,
                        fecha: date, categoria: str, descripcion: str,
                        tx_id: int | None = None) -> None:
    if tipo not in TIPOS:
        raise ValueError("Selecciona un tipo de movimiento válido.")
    centavos = monto_a_centavos(monto)
    categoria_row = con.execute("SELECT id,tipo FROM categoria WHERE nombre=?", (categoria,)).fetchone()
    if not categoria_row:
        raise ValueError("La categoría seleccionada no existe.")
    if categoria_row["tipo"] != tipo:
        raise ValueError("La categoría no corresponde al tipo de movimiento.")
    periodo_id = fecha_periodo(con, fecha)
    if tx_id is None:
        con.execute("""INSERT INTO transaccion
            (tipo,monto_centavos,fecha,descripcion,categoria_id,periodo_id)
            VALUES (?,?,?,?,?,?)""",
            (tipo, centavos, fecha.isoformat(), descripcion.strip(), categoria_row["id"], periodo_id))
    else:
        cur = con.execute("""UPDATE transaccion SET tipo=?,monto_centavos=?,fecha=?,
            descripcion=?,categoria_id=?,periodo_id=? WHERE id=?""",
            (tipo, centavos, fecha.isoformat(), descripcion.strip(), categoria_row["id"], periodo_id, tx_id))
        if cur.rowcount != 1:
            raise ValueError("No se encontró el movimiento que intentas editar.")
    con.commit()


def listar_transacciones(con: sqlite3.Connection, anio: int | None = None,
                         mes: int | None = None, tipo: str | None = None,
                         categoria: str | None = None,
                         desde: date | None = None, hasta: date | None = None):
    sql = """SELECT t.id,t.tipo,t.monto_centavos,t.fecha,t.descripcion,
              c.nombre AS categoria,p.anio,p.mes
              FROM transaccion t JOIN categoria c ON c.id=t.categoria_id
              JOIN periodo p ON p.id=t.periodo_id WHERE 1=1"""
    params: list[object] = []
    if anio is not None:
        sql += " AND p.anio=?"; params.append(anio)
    if mes is not None:
        sql += " AND p.mes=?"; params.append(mes)
    if tipo:
        sql += " AND t.tipo=?"; params.append(tipo)
    if categoria:
        sql += " AND c.nombre=?"; params.append(categoria)
    if desde:
        sql += " AND t.fecha>=?"; params.append(desde.isoformat())
    if hasta:
        sql += " AND t.fecha<=?"; params.append(hasta.isoformat())
    sql += " ORDER BY t.fecha DESC,t.id DESC"
    return con.execute(sql, params).fetchall()


def resumen_mensual(con: sqlite3.Connection, anio: int, mes: int):
    filas = con.execute("""SELECT t.tipo,COALESCE(SUM(t.monto_centavos),0) AS total
        FROM transaccion t JOIN periodo p ON p.id=t.periodo_id
        WHERE p.anio=? AND p.mes=? GROUP BY t.tipo""", (anio, mes)).fetchall()
    totales = {"INGRESO": 0, "GASTO": 0}
    totales.update({r["tipo"]: int(r["total"]) for r in filas})
    por_categoria = con.execute("""SELECT c.nombre,SUM(t.monto_centavos) AS total
        FROM transaccion t JOIN categoria c ON c.id=t.categoria_id
        JOIN periodo p ON p.id=t.periodo_id
        WHERE p.anio=? AND p.mes=? AND t.tipo='GASTO'
        GROUP BY c.nombre ORDER BY total DESC,c.nombre""", (anio, mes)).fetchall()
    return totales["INGRESO"], totales["GASTO"], totales["INGRESO"]-totales["GASTO"], por_categoria


def totp_secreto() -> str:
    return base64.b32encode(secrets.token_bytes(20)).decode("ascii").rstrip("=")


def codigo_totp(secreto: str, instante: int | None = None) -> tuple[str, int]:
    contador = int((instante or int(time.time())) // 30)
    clave = base64.b32decode(secreto + "=" * ((8 - len(secreto) % 8) % 8), casefold=True)
    digest = hmac.new(clave, struct.pack(">Q", contador), hashlib.sha1).digest()
    offset = digest[-1] & 0x0F
    numero = struct.unpack(">I", digest[offset:offset + 4])[0] & 0x7FFFFFFF
    return f"{numero % 1_000_000:06d}", contador


def verificar_totp(secreto: str, codigo: str, ultimo: int = -1) -> int | None:
    codigo = "".join(ch for ch in str(codigo) if ch.isdigit())
    if len(codigo) != 6:
        return None
    ahora = int(time.time())
    for desplazamiento in (0, -30, 30):
        esperado, contador = codigo_totp(secreto, ahora + desplazamiento)
        if contador > ultimo and hmac.compare_digest(esperado, codigo):
            return contador
    return None


def hash_contrasena(contrasena: str, sal: bytes | None = None) -> tuple[bytes, bytes]:
    sal = sal or secrets.token_bytes(16)
    return sal, hashlib.scrypt(contrasena.encode("utf-8"), salt=sal, n=2**14, r=8, p=1, dklen=32)


def crear_csv(filas) -> bytes:
    salida = io.StringIO(newline="")
    escritor = csv.writer(salida)
    escritor.writerow(["fecha", "tipo", "categoria", "monto", "descripcion"])
    for fila in filas:
        escritor.writerow([fila["fecha"], NOMBRES_TIPO[fila["tipo"]], fila["categoria"],
                           f"{a_decimal(fila['monto_centavos']):.2f}", fila["descripcion"]])
    return ("\ufeff" + salida.getvalue()).encode("utf-8")


def texto_seguro(texto: str) -> str:
    return unicodedata.normalize("NFKD", str(texto)).encode("ascii", "ignore").decode("ascii")


def crear_pdf(lineas: list[str]) -> bytes:
    """Genera un PDF sencillo multipágina con fuentes estándar, sin librerías externas."""
    lineas = [texto_seguro(x).replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")
              for x in lineas]
    paginas = [lineas[i:i + 46] for i in range(0, max(1, len(lineas)), 46)]
    objetos: dict[int, bytes] = {}
    objetos[1] = b"<< /Type /Catalog /Pages 2 0 R >>"
    objetos[3] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"
    ids_pagina = []
    siguiente = 4
    for grupo in paginas:
        id_pagina, id_contenido = siguiente, siguiente + 1
        siguiente += 2
        ids_pagina.append(id_pagina)
        comandos = ["BT", "/F1 10 Tf", "48 790 Td", "14 TL"]
        for idx, linea in enumerate(grupo):
            if idx:
                comandos.append("T*")
            comandos.append(f"({linea[:120]}) Tj")
        comandos.append("ET")
        stream = "\n".join(comandos).encode("latin-1", "replace")
        objetos[id_pagina] = f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {id_contenido} 0 R >>".encode()
        objetos[id_contenido] = b"<< /Length " + str(len(stream)).encode() + b" >>\nstream\n" + stream + b"\nendstream"
    refs = " ".join(f"{i} 0 R" for i in ids_pagina)
    objetos[2] = f"<< /Type /Pages /Kids [{refs}] /Count {len(ids_pagina)} >>".encode()
    salida = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = [0] * (max(objetos) + 1)
    for numero in range(1, max(objetos) + 1):
        offsets[numero] = len(salida)
        salida.extend(f"{numero} 0 obj\n".encode())
        salida.extend(objetos[numero]); salida.extend(b"\nendobj\n")
    inicio_xref = len(salida)
    salida.extend(f"xref\n0 {len(offsets)}\n0000000000 65535 f \n".encode())
    for offset in offsets[1:]:
        salida.extend(f"{offset:010d} 00000 n \n".encode())
    salida.extend(f"trailer\n<< /Size {len(offsets)} /Root 1 0 R >>\nstartxref\n{inicio_xref}\n%%EOF".encode())
    return bytes(salida)


def rotulo_periodo(anio: int, mes: int) -> str:
    return f"{MESES[mes - 1]} {anio}"


def estilos():
    st.markdown("""<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap');
    :root { --ink:#102332; --muted:#758493; --mint:#00d49a; --blue:#1769ff; }
    html,body,[class*="css"] { font-family:'DM Sans',sans-serif; }
    .stApp { background:linear-gradient(145deg,#f5faf9 0%,#f6f8fc 45%,#f2f7fb 100%); color:var(--ink); }
    [data-testid="stSidebar"] { background:#102332; }
    [data-testid="stSidebar"] * { color:#edf7f5 !important; }
    [data-testid="stSidebar"] [data-testid="stRadio"] label { padding:7px 9px; border-radius:10px; }
    .brand { display:flex; align-items:center; gap:12px; padding:8px 0 22px; }
    .brand-icon { width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,#1769ff,#00d49a);display:grid;place-items:center;color:white;font-size:23px;box-shadow:0 7px 18px #00a77b44; }
    .brand-title { font:800 17px Manrope,sans-serif; letter-spacing:.02em; line-height:1.05; }
    .brand-title span { color:#03d99f; font-size:12px; letter-spacing:.19em; }
    .hero { background:linear-gradient(115deg,#102c3a,#17414a 60%,#0b886c);color:white;border-radius:22px;padding:26px 30px;margin:0 0 19px;box-shadow:0 12px 32px #183d3b1c; }
    .hero h1 { color:white;font:800 27px Manrope,sans-serif;margin:0 0 4px; }
    .hero p { color:#c8e1de;margin:0; }
    .eyebrow { color:#00d49a;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase; }
    [data-testid="stMetric"] { background:white;border:1px solid #e9efef;padding:17px 20px;border-radius:17px;box-shadow:0 5px 20px #183d3b08; }
    [data-testid="stMetricLabel"] { color:#71808b; }
    [data-testid="stMetricValue"] { color:#102332;font-family:Manrope,sans-serif; }
    .section-title { font:700 19px Manrope,sans-serif;color:#102332;margin:8px 0 12px; }
    .small-muted { color:#71808b;font-size:13px; }
    .pill { display:inline-block;padding:4px 9px;border-radius:999px;background:#e8faf4;color:#07885f;font-size:12px;font-weight:700; }
    div.stButton > button[kind="primary"] { background:linear-gradient(100deg,#00bd8b,#00a97f); border:0; }
    </style>""", unsafe_allow_html=True)


def marca():
    st.markdown("""<div class="brand"><div class="brand-icon">▰</div>
    <div class="brand-title">CONTROL DE<br><span>GASTOS PERSONALES</span></div></div>""", unsafe_allow_html=True)


def usuario_actual(con):
    return con.execute("SELECT * FROM usuario WHERE id=1").fetchone()


def pantalla_acceso(con):
    marca()
    st.markdown('<div class="hero"><div class="eyebrow">Finanzas personales</div><h1>Tu dinero, más claro.</h1><p>Registra movimientos, entiende tus hábitos y consulta tus reportes mensuales.</p></div>', unsafe_allow_html=True)
    usuario = usuario_actual(con)
    if not usuario:
        st.subheader("Configura tu cuenta local")
        st.caption("El proyecto define una sola cuenta. La verificación en dos pasos se configura con una aplicación autenticadora.")
        with st.form("crear_cuenta"):
            correo = st.text_input("Correo electrónico", placeholder="tu@correo.com").strip().lower()
            clave = st.text_input("Contraseña", type="password", help="Usa al menos 10 caracteres.")
            confirmar = st.text_input("Confirma la contraseña", type="password")
            enviar = st.form_submit_button("Crear cuenta y configurar verificación", type="primary")
        if enviar:
            if "@" not in correo or "." not in correo.rsplit("@", 1)[-1]:
                st.error("Escribe un correo válido.")
            elif len(clave) < 10:
                st.error("La contraseña debe tener al menos 10 caracteres.")
            elif clave != confirmar:
                st.error("Las contraseñas no coinciden.")
            else:
                sal, digest = hash_contrasena(clave)
                st.session_state.pending_email = correo
                st.session_state.pending_secret = totp_secreto()
                st.session_state.pending_salt = sal
                st.session_state.pending_hash = digest
                st.rerun()
        if st.session_state.get("pending_secret"):
            secreto = st.session_state.pending_secret
            correo = st.session_state.pending_email
            uri = f"otpauth://totp/{quote('Control de Gastos:' + correo)}?secret={secreto}&issuer={quote('Control de Gastos')}&algorithm=SHA1&digits=6&period=30"
            st.info("Agrega una cuenta TOTP en Google Authenticator, Microsoft Authenticator, Authy u otra app. Introduce manualmente esta clave secreta:")
            st.code(secreto)
            st.caption(f"URI de configuración: {uri}")
            st.warning("Guarda la clave en un lugar seguro. No cierres esta sesión hasta confirmar el código de seis dígitos.")
            with st.form("confirmar_totp"):
                codigo = st.text_input("Código de la aplicación autenticadora", max_chars=6)
                confirmar_totp = st.form_submit_button("Confirmar y activar cuenta", type="primary")
            if confirmar_totp:
                contador = verificar_totp(secreto, codigo)
                if contador is None:
                    st.error("El código no coincide o ya venció. Revisa la hora del teléfono y vuelve a intentarlo.")
                else:
                    con.execute("""INSERT INTO usuario
                        (id,correo,sal,hash_contrasena,secreto_totp,ultimo_totp)
                        VALUES (1,?,?,?,?,?)""",
                        (correo, st.session_state.pending_salt,
                         st.session_state.pending_hash, secreto, contador))
                    con.commit()
                    for clave_sesion in ("pending_secret", "pending_email", "pending_salt", "pending_hash"):
                        st.session_state.pop(clave_sesion, None)
                    st.session_state.authenticated = True
                    st.success("Cuenta activada. Ya puedes comenzar.")
                    st.rerun()
    else:
        st.subheader("Iniciar sesión")
        st.caption("Acceso local protegido con contraseña y un código TOTP de un solo uso.")
        with st.form("iniciar_sesion"):
            correo = st.text_input("Correo electrónico").strip().lower()
            clave = st.text_input("Contraseña", type="password")
            codigo = st.text_input("Código de 6 dígitos", max_chars=6)
            enviar = st.form_submit_button("Entrar", type="primary")
        if enviar:
            ahora = int(time.time())
            if ahora < int(usuario["bloqueado_hasta"]):
                st.error("Acceso temporalmente bloqueado. Intenta de nuevo en unos minutos.")
            else:
                _, candidato = hash_contrasena(clave, bytes(usuario["sal"]))
                contador = verificar_totp(usuario["secreto_totp"], codigo, int(usuario["ultimo_totp"]))
                valido = (hmac.compare_digest(candidato, bytes(usuario["hash_contrasena"]))
                          and correo == usuario["correo"] and contador is not None)
                if valido:
                    con.execute("UPDATE usuario SET intentos=0,bloqueado_hasta=0,ultimo_totp=? WHERE id=1", (contador,))
                    con.commit()
                    st.session_state.authenticated = True
                    st.rerun()
                else:
                    intentos = int(usuario["intentos"]) + 1
                    bloqueo = ahora + 600 if intentos >= 5 else 0
                    con.execute("UPDATE usuario SET intentos=?,bloqueado_hasta=? WHERE id=1", (intentos % 5, bloqueo))
                    con.commit()
                    st.error("Correo, contraseña o código incorrectos.")


def dataframe_movimientos(filas):
    return [{"Fecha": f["fecha"], "Tipo": NOMBRES_TIPO[f["tipo"]], "Categoría": f["categoria"],
             "Descripción": f["descripcion"] or "—", "Monto": moneda(f["monto_centavos"])} for f in filas]


def selector_periodo(prefijo: str, actual: date | None = None):
    actual = actual or date.today()
    c1, c2 = st.columns(2)
    mes = c1.selectbox("Mes", options=list(range(1, 13)), index=actual.month - 1,
                       format_func=lambda x: MESES[x - 1], key=f"{prefijo}_mes")
    anio = c2.number_input("Año", min_value=2000, max_value=2100, value=actual.year,
                           step=1, key=f"{prefijo}_anio")
    return int(anio), int(mes)


def cabecera(titulo: str, subtitulo: str):
    st.markdown(f'<div class="hero"><div class="eyebrow">Control financiero</div><h1>{titulo}</h1><p>{subtitulo}</p></div>', unsafe_allow_html=True)


def vista_inicio(con):
    cabecera("Un resumen que sí se entiende.", "Tu panorama financiero, con montos exactos y movimientos organizados.")
    anio, mes = selector_periodo("inicio")
    ingresos, gastos, balance, categorias = resumen_mensual(con, anio, mes)
    c1, c2, c3 = st.columns(3)
    c1.metric("Ingresos", moneda(ingresos))
    c2.metric("Gastos", moneda(gastos))
    c3.metric("Balance", moneda(balance), delta="Disponible" if balance >= 0 else "Déficit", delta_color="normal" if balance >= 0 else "inverse")
    st.markdown(f'<div class="section-title">{rotulo_periodo(anio, mes)}</div>', unsafe_allow_html=True)
    izquierda, derecha = st.columns([1.15, 1])
    with izquierda:
        st.markdown('<div class="section-title">Gastos por categoría</div>', unsafe_allow_html=True)
        if categorias:
            datos = {r["nombre"]: float(a_decimal(r["total"])) for r in categorias}
            st.bar_chart(datos, height=280)
            st.dataframe([{"Categoría": r["nombre"], "Total": moneda(r["total"])} for r in categorias],
                         hide_index=True, use_container_width=True)
        else:
            st.info("Aún no hay gastos en este periodo. El gráfico aparecerá cuando registres alguno.")
    with derecha:
        st.markdown('<div class="section-title">Movimientos recientes</div>', unsafe_allow_html=True)
        recientes = listar_transacciones(con, anio, mes)[:7]
        if recientes:
            st.dataframe(dataframe_movimientos(recientes), hide_index=True, use_container_width=True)
        else:
            st.info("No hay movimientos para este mes.")


def vista_registro(con):
    cabecera("Registrar movimiento", "Agrega un ingreso o gasto. El periodo se toma automáticamente de la fecha.")
    with st.form("form_registro", clear_on_submit=True):
        tipo = st.radio("Tipo de movimiento", TIPOS, horizontal=True,
                        format_func=lambda x: NOMBRES_TIPO[x])
        posibles = [n for n, t in CATEGORIAS if t == tipo]
        categoria = st.selectbox("Categoría", posibles)
        c1, c2 = st.columns(2)
        monto = c1.text_input("Monto (COP)", placeholder="Ej. 125000,50", help="Admite máximo dos decimales.")
        fecha = c2.date_input("Fecha", value=date.today(), format="DD/MM/YYYY")
        descripcion = st.text_input("Descripción (opcional)", max_chars=120, placeholder="Ej. Mercado semanal")
        guardar = st.form_submit_button("Guardar movimiento", type="primary", use_container_width=True)
    if guardar:
        try:
            guardar_transaccion(con, tipo, monto, fecha, categoria, descripcion)
            st.success(f"{NOMBRES_TIPO[tipo]} guardado correctamente.")
            st.rerun()
        except (ValueError, sqlite3.Error) as exc:
            st.error(str(exc))
    st.caption("La aplicación guarda los importes como centavos enteros en SQLite para evitar errores de redondeo.")


def vista_movimientos(con):
    cabecera("Tus movimientos", "Busca, filtra, edita o elimina registros con una confirmación explícita.")
    c1, c2 = st.columns(2)
    desde = c1.date_input("Desde", value=date(date.today().year, date.today().month, 1), key="mov_desde")
    hasta = c2.date_input("Hasta", value=date.today(), key="mov_hasta")
    if desde > hasta:
        st.error("La fecha inicial debe ser anterior o igual a la fecha final.")
        return
    f1, f2, f3 = st.columns(3)
    tipo = f1.selectbox("Tipo", ["Todos", *TIPOS], format_func=lambda x: "Todos" if x == "Todos" else NOMBRES_TIPO[x])
    categoria = f2.selectbox("Categoría", ["Todas", *[n for n, _ in CATEGORIAS]])
    f3.caption("Los filtros se aplican al listado y a la selección para edición.")
    filas = listar_transacciones(con, tipo=None if tipo == "Todos" else tipo,
                                  categoria=None if categoria == "Todas" else categoria,
                                  desde=desde, hasta=hasta)
    st.markdown(f'<div class="section-title">{len(filas)} movimiento(s)</div>', unsafe_allow_html=True)
    if filas:
        st.dataframe(dataframe_movimientos(filas), hide_index=True, use_container_width=True)
        opciones = {f"#{r['id']} · {r['fecha']} · {NOMBRES_TIPO[r['tipo']]} · {r['categoria']} · {moneda(r['monto_centavos'])}": r for r in filas}
        seleccion = st.selectbox("Selecciona un movimiento para editar o eliminar", list(opciones))
        fila = opciones[seleccion]
        st.markdown('<div class="section-title">Editar movimiento</div>', unsafe_allow_html=True)
        with st.form("form_editar"):
            idx_tipo = TIPOS.index(fila["tipo"])
            tipo_nuevo = st.radio("Tipo", TIPOS, index=idx_tipo, horizontal=True,
                                  format_func=lambda x: NOMBRES_TIPO[x], key="editar_tipo")
            cats = [n for n, t in CATEGORIAS if t == tipo_nuevo]
            default_cat = fila["categoria"] if fila["categoria"] in cats else cats[0]
            categoria_nueva = st.selectbox("Categoría", cats, index=cats.index(default_cat), key="editar_categoria")
            e1, e2 = st.columns(2)
            monto_nuevo = e1.text_input("Monto (COP)", value=f"{a_decimal(fila['monto_centavos']):.2f}", key="editar_monto")
            fecha_nueva = e2.date_input("Fecha", value=date.fromisoformat(fila["fecha"]), format="DD/MM/YYYY", key="editar_fecha")
            descripcion_nueva = st.text_input("Descripción", value=fila["descripcion"], max_chars=120, key="editar_descripcion")
            actualizar = st.form_submit_button("Guardar cambios", type="primary")
        if actualizar:
            try:
                guardar_transaccion(con, tipo_nuevo, monto_nuevo, fecha_nueva,
                                    categoria_nueva, descripcion_nueva, int(fila["id"]))
                st.success("Movimiento actualizado.")
                st.rerun()
            except (ValueError, sqlite3.Error) as exc:
                st.error(str(exc))
        st.markdown('<div class="section-title">Eliminar movimiento</div>', unsafe_allow_html=True)
        confirmar = st.checkbox("Confirmo que quiero eliminar este movimiento.", key="confirmar_eliminar")
        if st.button("Eliminar movimiento seleccionado", disabled=not confirmar):
            con.execute("DELETE FROM transaccion WHERE id=?", (fila["id"],))
            con.commit()
            st.success("Movimiento eliminado.")
            st.rerun()
    else:
        st.info("No hay movimientos que coincidan con estos filtros.")


def vista_reportes(con):
    cabecera("Reportes mensuales", "Consulta el balance, los gastos por categoría y exporta tus datos.")
    anio, mes = selector_periodo("reporte")
    ingresos, gastos, balance, categorias = resumen_mensual(con, anio, mes)
    c1, c2, c3 = st.columns(3)
    c1.metric("Total ingresos", moneda(ingresos))
    c2.metric("Total gastos", moneda(gastos))
    c3.metric("Balance del mes", moneda(balance), delta="Positivo" if balance >= 0 else "Negativo", delta_color="normal" if balance >= 0 else "inverse")
    filas = listar_transacciones(con, anio, mes)
    col_izq, col_der = st.columns([1, 1.2])
    with col_izq:
        st.markdown('<div class="section-title">Gastos por categoría</div>', unsafe_allow_html=True)
        if categorias:
            st.dataframe([{"Categoría": r["nombre"], "Total": moneda(r["total"]),
                           "Participación": f"{(int(r['total'])/gastos*100):.1f}%" if gastos else "0%"}
                          for r in categorias], hide_index=True, use_container_width=True)
        else:
            st.info("Sin gastos para graficar en este periodo.")
    with col_der:
        st.markdown('<div class="section-title">Distribución</div>', unsafe_allow_html=True)
        if categorias:
            st.bar_chart({r["nombre"]: float(a_decimal(r["total"])) for r in categorias}, height=280)
        else:
            st.caption("El gráfico se mostrará cuando haya gastos.")
    st.markdown('<div class="section-title">Detalle del periodo</div>', unsafe_allow_html=True)
    if filas:
        st.dataframe(dataframe_movimientos(filas), hide_index=True, use_container_width=True)
    else:
        st.info("Este periodo está vacío: ingresos, gastos y balance son $ 0,00.")
    st.markdown('<div class="section-title">Comparativo y tendencia</div>', unsafe_allow_html=True)
    mes_anterior = 12 if mes == 1 else mes - 1
    anio_anterior = anio - 1 if mes == 1 else anio
    ing_ant, gas_ant, bal_ant, _ = resumen_mensual(con, anio_anterior, mes_anterior)
    k1, k2, k3 = st.columns(3)
    k1.metric("Ingresos vs. mes anterior", moneda(ingresos), delta=moneda(ingresos-ing_ant))
    k2.metric("Gastos vs. mes anterior", moneda(gastos), delta=moneda(gastos-gas_ant), delta_color="inverse")
    k3.metric("Balance anterior", moneda(bal_ant))
    tendencia = []
    for desplazamiento in range(5, -1, -1):
        total_mes = mes - desplazamiento
        total_anio = anio
        while total_mes <= 0:
            total_mes += 12; total_anio -= 1
        ti, tg, tb, _ = resumen_mensual(con, total_anio, total_mes)
        tendencia.append({"Periodo": rotulo_periodo(total_anio, total_mes), "Ingresos": float(a_decimal(ti)),
                          "Gastos": float(a_decimal(tg)), "Balance": float(a_decimal(tb))})
    tendencia_df = pd.DataFrame(tendencia).set_index("Periodo")
    st.line_chart(tendencia_df, height=260)
    st.markdown('<div class="section-title">Exportar</div>', unsafe_allow_html=True)
    csv_bytes = crear_csv(filas)
    resumen = ["CONTROL DE GASTOS PERSONALES", f"Reporte: {rotulo_periodo(anio, mes)}", "",
               f"Ingresos: {moneda(ingresos)}", f"Gastos: {moneda(gastos)}", f"Balance: {moneda(balance)}", "",
               "Gastos por categoría:"]
    resumen.extend([f"- {r['nombre']}: {moneda(r['total'])}" for r in categorias] or ["- Sin gastos registrados"])
    resumen.extend(["", "MOVIMIENTOS:"])
    resumen.extend([f"{f['fecha']} | {NOMBRES_TIPO[f['tipo']]} | {f['categoria']} | {moneda(f['monto_centavos'])} | {f['descripcion']}" for f in filas] or ["Sin movimientos"])
    pdf_bytes = crear_pdf(resumen)
    d1, d2 = st.columns(2)
    nombre = f"reporte_{anio}_{mes:02d}"
    d1.download_button("Descargar CSV", csv_bytes, file_name=nombre + ".csv", mime="text/csv", use_container_width=True)
    d2.download_button("Descargar PDF", pdf_bytes, file_name=nombre + ".pdf", mime="application/pdf", use_container_width=True)


def cargar_demo(con):
    ejemplos = [
        ("INGRESO", "2500000.00", date.today().replace(day=1), "Salario", "Pago mensual"),
        ("GASTO", "185000.00", date.today().replace(day=3), "Alimentación", "Mercado"),
        ("GASTO", "78000.00", date.today().replace(day=5), "Transporte", "Movilidad"),
        ("GASTO", "420000.00", date.today().replace(day=8), "Vivienda", "Servicios"),
        ("GASTO", "65000.00", date.today().replace(day=12), "Ocio", "Cine y cena"),
        ("INGRESO", "180000.00", date.today().replace(day=15), "Otros ingresos", "Trabajo extra"),
    ]
    for tipo, monto, fecha, categoria, desc in ejemplos:
        guardar_transaccion(con, tipo, monto, fecha, categoria, desc)


def vista_categorias(con):
    cabecera("Categorías", "Un catálogo inicial cerrado mantiene consistentes tus reportes.")
    izquierda, derecha = st.columns(2)
    for columna, tipo in ((izquierda, "INGRESO"), (derecha, "GASTO")):
        with columna:
            titulo_tipo = "Ingresos" if tipo == "INGRESO" else "Gastos"
            st.markdown(f'<div class="section-title">{titulo_tipo}</div>', unsafe_allow_html=True)
            nombres = [n for n, t in CATEGORIAS if t == tipo]
            st.dataframe([{"Categoría": n} for n in nombres], hide_index=True, use_container_width=True)
    st.caption("El documento define un catálogo precargado y cerrado. Por eso esta pantalla permite consultarlo, pero no agregar categorías libres que puedan romper la clasificación.")


def vista_ajustes(con):
    cabecera("Ajustes y respaldo", "Administra tu acceso local y descarga una copia de tus datos.")
    st.markdown('<div class="section-title">Cuenta y verificación en dos pasos</div>', unsafe_allow_html=True)
    usuario = usuario_actual(con)
    st.write(f"Cuenta activa: **{usuario['correo']}**")
    st.caption("La app está diseñada para un único usuario y ejecución local. El archivo SQLite se conserva en esta carpeta; mantenlo en un lugar seguro.")
    filas = listar_transacciones(con)
    st.markdown('<div class="section-title">Respaldo de movimientos</div>', unsafe_allow_html=True)
    st.download_button("Descargar respaldo CSV de todos los movimientos", crear_csv(filas),
                       file_name="respaldo_gastos_personales.csv", mime="text/csv")
    st.markdown('<div class="section-title">Datos de demostración</div>', unsafe_allow_html=True)
    if not filas:
        st.caption("Carga algunos movimientos ficticios del mes actual para explorar los reportes. No se cargan automáticamente.")
        if st.button("Cargar datos de demostración"):
            cargar_demo(con)
            st.success("Datos de demostración cargados.")
            st.rerun()
    else:
        st.info("La carga de demostración solo está disponible cuando todavía no hay movimientos.")
    st.markdown('<div class="section-title">Cambiar contraseña</div>', unsafe_allow_html=True)
    with st.form("cambiar_clave"):
        clave_actual = st.text_input("Contraseña actual", type="password")
        nueva = st.text_input("Nueva contraseña (mínimo 10 caracteres)", type="password")
        repetir = st.text_input("Repite la nueva contraseña", type="password")
        codigo = st.text_input("Código de verificación", max_chars=6)
        cambiar = st.form_submit_button("Actualizar contraseña")
    if cambiar:
        _, actual_hash = hash_contrasena(clave_actual, bytes(usuario["sal"]))
        contador = verificar_totp(usuario["secreto_totp"], codigo, int(usuario["ultimo_totp"]))
        if not hmac.compare_digest(actual_hash, bytes(usuario["hash_contrasena"])) or contador is None:
            st.error("La contraseña actual o el código son incorrectos.")
        elif len(nueva) < 10:
            st.error("La nueva contraseña debe tener al menos 10 caracteres.")
        elif nueva != repetir:
            st.error("Las contraseñas nuevas no coinciden.")
        else:
            sal, digest = hash_contrasena(nueva)
            con.execute("UPDATE usuario SET sal=?,hash_contrasena=?,ultimo_totp=? WHERE id=1", (sal, digest, contador))
            con.commit()
            st.success("Contraseña actualizada.")


def main():
    st.set_page_config(page_title="Control de Gastos Personales", page_icon="💸", layout="wide", initial_sidebar_state="expanded")
    estilos()
    con = conectar()
    if not st.session_state.get("authenticated"):
        pantalla_acceso(con)
        con.close()
        return
    marca()
    usuario = usuario_actual(con)
    st.sidebar.caption(f"Sesión · {usuario['correo']}")
    pagina = st.sidebar.radio("Navegación", ["Inicio", "Registrar movimiento", "Movimientos", "Reportes", "Categorías", "Ajustes"], label_visibility="collapsed")
    if st.sidebar.button("Cerrar sesión", use_container_width=True):
        st.session_state.authenticated = False
        st.rerun()
    st.sidebar.markdown("---")
    st.sidebar.caption("Datos locales · SQLite\nImportes exactos · centavos enteros")
    vistas = {"Inicio": vista_inicio, "Registrar movimiento": vista_registro,
              "Movimientos": vista_movimientos, "Reportes": vista_reportes,
              "Categorías": vista_categorias, "Ajustes": vista_ajustes}
    try:
        vistas[pagina](con)
    except sqlite3.Error as exc:
        st.error(f"No se pudo completar la operación de base de datos: {exc}")
    finally:
        con.close()


if __name__ == "__main__":
    main()
