# Control de Gastos Personales

Aplicación web académica para registrar ingresos y gastos, organizarlos por categorías y consultar su evolución. El proyecto actual está implementado en PHP con arquitectura MVC y una base de datos MySQL.

## Funcionalidades

- Registro, inicio y cierre de sesión.
- Gestión de categorías propias para ingresos y gastos.
- Registro, edición, consulta y eliminación de movimientos.
- Dashboard con saldos, gráficas y filtros por período.
- Reportes con filtros y descarga CSV.
- Edición del perfil, cambio de contraseña y preferencia de tema claro u oscuro.

Los datos de cada cuenta se consultan y modifican en el contexto del usuario autenticado. No se incluyen usuarios ni movimientos de demostración.

## Tecnologías

- PHP 8.2 o superior, con PDO.
- MySQL o MariaDB compatible con el esquema incluido.
- Composer para el autoload PSR-4 y dependencias PHP.
- Bootstrap 5.3 y CSS propio.
- JavaScript y Chart.js 4 (cargados desde CDN).

Composer también declara `setasign/fpdf`, pero el código actual no genera archivos PDF. No hay una funcionalidad PDF disponible en la aplicación.

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo_mysql`, `gd` y `zlib`. `gd` y `zlib` son requisitos de la dependencia FPDF declarada en Composer.
- MySQL o MariaDB con InnoDB y soporte para `CHECK` (MySQL 8.0.16+ o MariaDB 10.2.1+).
- Composer 2.
- Servidor web compatible con PHP (Apache/Nginx configurado para PHP) o el servidor integrado de PHP para desarrollo.
- Acceso a Internet para descargar Bootstrap y Chart.js desde CDN.

## Instalación local

Desde la terminal:

1. Clona el repositorio y entra a su directorio:

   ```bash
   git clone <URL-del-repositorio>
   cd Proyecto-5-Aplicaci-n-de-control-de-gastos-personales
   ```

2. Instala las dependencias:

   ```bash
   composer install
   ```

3. Crea la base de datos e importa `database/schema.sql`. El script crea la base `control_gastos` y las tablas `usuarios`, `categorias`, `movimientos` y `configuraciones`. Puede importarse desde phpMyAdmin o con el cliente MySQL:

   ```bash
   mysql -u root -p < database/schema.sql
   ```

4. Copia `.env.example` a `.env` y configura las variables de conexión para tu entorno. Por ejemplo, para MySQL local con usuario `root` sin contraseña, completa `DB_USERNAME=root` y deja `DB_PASSWORD` vacío. No uses esos valores de ejemplo en un servidor público.

   ```bash
   # PowerShell
   Copy-Item .env.example .env

   # macOS/Linux
   cp .env.example .env
   ```

   Configura como mínimo `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. `APP_DEBUG` debe ser `false` en un entorno accesible a otras personas.

5. Configura Apache o Nginx para que el directorio público del sitio sea `public/`. No expongas la raíz del repositorio como document root. El archivo `public/index.php` es el front controller; el servidor debe dirigir las rutas de la aplicación a ese archivo. El servidor integrado de PHP aplica el fallback de rutas automáticamente.

6. Inicia la aplicación localmente desde la raíz del repositorio:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

   Abre <http://127.0.0.1:8000>. Registra una cuenta desde **Crear una cuenta**; no hay credenciales de demostración.

Para comprobar solo la conexión de base de datos, ejecuta `php database/check_connection.php` después de configurar `.env`.

## Uso

- **Registro e inicio de sesión:** crea una cuenta con nombre, correo y contraseña de al menos 8 caracteres; luego inicia sesión con esos datos. El cierre de sesión se realiza desde el botón de navegación.
- **Categorías:** crea categorías de tipo ingreso o gasto; puedes asignarles un color. Una categoría con movimientos asociados no se puede eliminar y no puede cambiar de tipo.
- **Ingresos y gastos:** registra un movimiento con categoría del mismo tipo, valor positivo, fecha y descripción opcional. La lista permite filtrar por fechas, categoría y tipo; los movimientos propios se pueden editar y eliminar.
- **Dashboard:** consulta ingresos, gastos, saldo y movimientos del período. El filtro ofrece historial completo, mes actual, mes anterior, año actual o rango personalizado. Las gráficas presentan comparación mensual, gastos por categoría y evolución del saldo.
- **Reportes:** filtra movimientos por fechas, tipo, categoría y texto de descripción. La descarga exporta el resultado filtrado como CSV delimitado por punto y coma y codificado en UTF-8.
- **Configuración:** actualiza nombre y correo, cambia la contraseña validando la contraseña actual y guarda la preferencia de tema en la base de datos para conservarla al iniciar sesión de nuevo.

## Pruebas

Las pruebas de integración existentes requieren Composer instalado, las extensiones PHP indicadas y una base MySQL **desechable** creada con `database/schema.sql`. Configura `.env` para esa base antes de ejecutarlas. Las pruebas insertan cuentas y movimientos y los eliminan al finalizar; no las ejecutes contra una base con datos que quieras conservar.

Ejecuta cada archivo desde la raíz del proyecto:

```bash
php tests/authentication.php
php tests/categories.php
php tests/movements.php
php tests/dashboard.php
php tests/reports.php
php tests/settings.php
```

Las pruebas cubren autenticación del modelo, CRUD y aislamiento por usuario, filtros, períodos del dashboard, cálculos, preferencias y consultas de reportes. `tests/reports.php` comprueba el escape CSV en un flujo de prueba que reproduce la regla; no hace una petición HTTP al endpoint de exportación. La guía de pruebas manuales y sus pasos están en [`tests/README.md`](tests/README.md).

## Estructura del proyecto

```text
app/
  Config/       Carga de entorno y conexión PDO
  Controllers/  Acciones de las rutas y validación de entradas
  Middleware/  Reglas de acceso
  Models/      Consultas y persistencia mediante PDO
  Services/    Router y sesiones/CSRF
  Views/       Pantallas y layout compartido
database/      Esquema SQL y comprobación de conexión
docs/          Notas de arquitectura
public/        Front controller y recursos públicos CSS/JS
tests/         Pruebas de integración PHP
```

## Seguridad observada en el código

- Los modelos utilizan consultas PDO preparadas y la conexión desactiva consultas emuladas.
- Los movimientos, categorías, perfiles, preferencias y reportes se consultan dentro del ámbito del usuario autenticado.
- Las operaciones mutables usan POST y validan token CSRF; el cierre de sesión también exige CSRF.
- Las contraseñas se almacenan con `password_hash()` y se comprueban con `password_verify()`. La sesión regenera su identificador al iniciar sesión y al cambiar la contraseña; sus cookies usan HttpOnly, SameSite=Lax y el indicador Secure cuando la petición usa HTTPS.
- Las vistas escapan los valores de texto con `htmlspecialchars`. La exportación antepone un apóstrofo a celdas textuales que comienzan con caracteres de fórmula.
- Los errores técnicos se escriben en el log de PHP; el detalle se muestra en la respuesta solo cuando `APP_DEBUG=true`. Mantén `APP_DEBUG=false` en producción.

No se observa limitación de intentos de inicio de sesión ni protección automatizada contra fuerza bruta. El CSRF depende del token aleatorio de sesión; `CSRF_SECRET_KEY` no se utiliza. HTTPS y la configuración segura del servidor deben proporcionarse en el despliegue.

## Nota de configuración versionada

Aunque `.gitignore` contiene la regla `.env`, `.env` ya aparece en el índice actual de Git y por eso la regla no lo excluye del repositorio. Antes de publicar o compartir la rama, hay que retirar ese archivo del control de versiones conservando la copia local y revisar si contiene secretos que deban rotarse. No edites ni borres la configuración local durante la instalación.

## Archivos heredados

`app.py` corresponde a la implementación previa en Python/Streamlit y SQLite; la aplicación PHP no lo carga. Puede retirarse en una limpieza futura si ya no es necesario para el historial del proyecto. `iniciar_servidor.bat` intenta iniciar ejecutables desde rutas absolutas de una máquina concreta y además asume una ruta de `public/`; usa las instrucciones portables de arriba en su lugar. Ambos archivos se conservan en esta entrega.
