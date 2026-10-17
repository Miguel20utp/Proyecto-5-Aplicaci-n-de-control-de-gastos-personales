# Control de Gastos Personales

**Universidad Tecnológica de Pereira (UTP)**  
**Facultad de Ingenierías — Programa de Ingeniería de Sistemas y Computación**  
**Asignatura:** IS873 – Laboratorio de Software (Grupo 102)  
**Proyecto:** Aplicación Web de Control de Gastos Personales  
**Autores:**
- Laura Camila Uribe Alzate
- Miguel Angel Inga Valencia
- Harold Ordoñez

---

## 1. Descripción General

**Control de Gastos Personales** es una solución web integral diseñada para registrar, organizar, consultar y analizar ingresos y egresos personales. El sistema calcula en tiempo real el balance financiero mensual mediante aritmética decimal exacta, provee visualizaciones interactivas de distribución de gastos por categoría, genera reportes detallados y permite la exportación en formatos universales (CSV compatible con Excel y PDF listo para impresión).

La aplicación está construida con una arquitectura modular sin frameworks (Pure PHP 8.2+ con PDO), separación estricta de responsabilidades (MVC / Servicios / Modelos), alta cohesión y bajo acoplamiento, cumpliendo estándares de seguridad web (OWASP).

---

## 2. Tecnologías Obligatorias Utilizadas

- **Backend:** PHP 8.2+ (Probado y verificado en PHP 8.3.33).
- **Base de Datos:** MySQL 8.0+ / MariaDB 10.4+ con motor transaccional `InnoDB`.
- **Capa de Abstracción de Datos:** PDO (PHP Data Objects) con consultas preparadas nativas y emulación desactivada.
- **Frontend:** HTML5 semántico, CSS3 personalizado, Bootstrap 5.3 y Bootstrap Icons.
- **Gráficos Dinámicos:** Chart.js 4.4.
- **Gestor de Dependencias:** Composer 2.x.
- **Generación de Reportes PDF:** Biblioteca `setasign/fpdf` v1.9.0 (integrada vía Composer).
- **Entorno Local Recomendado:** XAMPP para Windows.
- **Codificación:** UTF-8 / utf8mb4 con soporte completo para el idioma español (tildes, eñes).
- **Moneda Predeterminada:** Peso Colombiano (COP), con formato local (`$ 1.250.000,00`).

---

## 3. Arquitectura del Proyecto

```text
control-gastos/
├── app/
│   ├── config/
│   │   └── database.php         # Conexión Singleton PDO y cargador de variables .env
│   ├── controllers/
│   │   ├── AuthController.php   # Login seguro, logout, control de fuerza bruta
│   │   ├── DashboardController.php # Métricas financieras, KPIs y datos de gráficas
│   │   ├── TransactionController.php # CRUD de movimientos, validaciones y filtros
│   │   ├── CategoryController.php    # Gestión y catálogo de categorías
│   │   └── ReportController.php      # Resumen mensual y exportación de archivos
│   ├── models/
│   │   ├── User.php             # Modelo de usuarios y bloqueo tras intentos fallidos
│   │   ├── Category.php         # Catálogo de categorías con integridad referencial
│   │   ├── Period.php           # Sincronización automática de periodos (año/mes)
│   │   └── Transaction.php      # Modelo de transacciones con filtros en BD
│   ├── services/
│   │   ├── FinancialCalculator.php # Cálculos matemáticos exactos (BCMath)
│   │   ├── CsvExporter.php      # Exportador CSV con BOM UTF-8 y anti CSV Injection
│   │   └── PdfExporter.php      # Generador de reportes PDF estructurados
│   ├── helpers/
│   │   ├── csrf.php             # Generación y validación de tokens anti-CSRF
│   │   ├── validation.php       # Validador de tipos, fechas y montos positivos
│   │   ├── format.php           # Formateo de moneda colombiana COP y fechas
│   │   └── auth.php             # Manejo de sesiones seguras y mensajes flash
│   └── views/
│       ├── layouts/             # Plantillas base (header, sidebar, footer)
│       ├── auth/                # Pantalla de login
│       ├── dashboard/           # Panel principal con tarjetas y Chart.js
│       ├── transactions/        # Listado paginado, creación y edición
│       ├── categories/          # Catálogo de categorías ingresos/gastos
│       └── reports/             # Balance mensual y tablas analíticas
├── database/
│   ├── schema.sql               # DDL de tablas InnoDB, claves foráneas, índices y triggers
│   ├── seed.sql                 # Categorías oficiales predeterminadas y usuario inicial
│   └── demo.sql                 # Transacciones de ejemplo multimes
├── docs/
│   ├── MANUAL_USUARIO.md        # Guía paso a paso para el usuario final
│   ├── PANTALLAS.md             # Descripción detallada de cada pantalla
│   └── TRAZABILIDAD.md          # Matriz de trazabilidad de requerimientos
├── public/
│   ├── index.php                # Front Controller y enrutador principal
│   ├── .htaccess                # Reescribidor Apache y protección de carpetas
│   └── assets/
│       ├── css/style.css        # Hoja de estilos con paleta UTP profesional
│       └── js/main.js           # Dinamismo de categorías, modales y anti-doble envío
├── storage/
│   ├── logs/                    # Registro controlado de excepciones
│   └── reports/                 # Archivos temporales generados
├── tests/
│   └── run_tests.php            # Suite de pruebas automatizadas (18 criterios + RNF-02)
├── .env.example                 # Plantilla de configuración
├── .env                         # Archivo activo de configuración local
├── composer.json                # Definición de dependencias y autoloader PSR-4
└── README.md                    # Documentación principal del sistema
```

---

## 4. Instalación y Puesta en Marcha con XAMPP (Windows)

### Paso 1: Clonar o copiar el repositorio
Ubique la carpeta del proyecto en el directorio `htdocs` de XAMPP:
```bash
# Ruta típica:
C:\xampp\htdocs\control-gastos
```

### Paso 2: Iniciar Servicios en XAMPP
Abra el **XAMPP Control Panel** e inicie los módulos:
- **Apache** (Puerto 80 o configurado)
- **MySQL** (Puerto 3306)

### Paso 3: Crear la Base de Datos e Importar Scripts SQL
Abra phpMyAdmin (`http://localhost/phpmyadmin`) o la consola MySQL y ejecute:
1. Cree la base de datos e importe la estructura:
   ```sql
   source C:/xampp/htdocs/control-gastos/database/schema.sql;
   ```
2. Cargue las categorías iniciales y el usuario administrador:
   ```sql
   source C:/xampp/htdocs/control-gastos/database/seed.sql;
   ```
3. *(Opcional)* Cargue los datos de demostración multimes para pruebas visuales inmediatas:
   ```sql
   source C:/xampp/htdocs/control-gastos/database/demo.sql;
   ```

### Paso 4: Configurar el archivo `.env`
Si el archivo `.env` no existe, copie `.env.example` como `.env`:
```ini
APP_NAME="Control de Gastos Personales"
APP_ENV=local
APP_DEBUG=true
APP_URL="http://localhost:8000"

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=control_gastos
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

### Paso 5: Instalar dependencias con Composer
Abra una consola en la raíz del proyecto y ejecute:
```bash
composer install
```

### Paso 6: Ejecutar la Aplicación
Existen dos formas estándar de ejecutar el proyecto:

- **Opción A (Recomendada con servidor embebido de PHP):**
  ```bash
  php -S 127.0.0.1:8000 -t public
  ```
  Acceda en su navegador web a: `http://127.0.0.1:8000`

- **Opción B (Directamente bajo Apache en XAMPP):**
  Acceda a: `http://localhost/control-gastos/public/` o `http://localhost/control-gastos/`

---

## 5. Credenciales de Demostración

- **Correo electrónico:** `admin@utp.edu.co`
- **Contraseña:** `Admin123*`

> Las contraseñas se almacenan mediante el algoritmo `BCRYPT` mediante la función nativa `password_hash()`.

---

## 6. Ejecución de Pruebas Automatizadas

El sistema incluye una suite de pruebas automatizadas reproducibles que validan todos los criterios de aceptación y requerimientos no funcionales.

Para ejecutar las pruebas en consola:
```bash
php tests/run_tests.php
```

### Resultados de Ejecución Verificados:
- **19/19 Pruebas Aprobadas (100% de éxito)**.
- **Rendimiento RNF-02:** Consulta y cálculo consolidado sobre 5.000 transacciones completado en **~8 ms** (Límite exigido: 3.000 ms).
- **Precisión Financiera RNF-04:** Verificación de balance con datos conocidos ($2.000.000 ingresos - $750.000 gastos = $1.250.000 balance exacto al centavo).
- **Seguridad RNF-06:** Resistencia comprobada contra inyecciones SQL, tokens CSRF válidos/inválidos, y protección contra fórmulas maliciosas en CSV.

---

## 7. Medidas de Seguridad Implementadas

1. **Consultas Preparadas con PDO:** Consultas parametrizadas en todos los modelos para erradicar el riesgo de SQL Injection.
2. **Protección Anti-CSRF:** Tokens criptográficos aleatorios generados por sesión (`bin2hex(random_bytes(32))`), requeridos en todas las peticiones POST (crear, actualizar, eliminar).
3. **Prevención de Inyección de Fórmulas en CSV:** Sanitización de campos que comiencen con `=`, `+`, `-`, `@` anteponiendo un apóstrofe de escape.
4. **Protección contra Fuerza Bruta:** Bloqueo temporal automático de cuentas tras 5 intentos fallidos consecutivos durante 15 minutos.
5. **Escape XSS:** Función `e()` con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` en todas las vistas.
6. **Manejo Seguro de Errores:** Errores y trazas técnicas registradas en `storage/logs/app_error.log` sin exponer credenciales ni rutas internas a los usuarios.
7. **Integridad Referencial en Base de Datos:** Claves foráneas con `ON DELETE RESTRICT` y triggers que garantizan que una categoría de gasto no pueda asignarse a un ingreso y viceversa.

---
## Servidor Web PHP

http://127.0.0.1:8000
