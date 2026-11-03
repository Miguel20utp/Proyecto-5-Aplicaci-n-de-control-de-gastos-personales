# Base de datos MySQL

## Crear e importar desde phpMyAdmin

1. Inicia MySQL desde XAMPP y abre `http://localhost/phpmyadmin`.
2. Abre la pestaña **Importar** y selecciona `database/schema.sql`.
3. Elige el formato SQL y pulsa **Importar**. El script crea la base `control_gastos` y las tablas `usuarios`, `categorias`, `movimientos` y `configuraciones` con sus índices y relaciones.
4. Copia `.env.example` como `.env` (si aún no existe) y configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y `DB_CHARSET` para tu instalación local. No compartas ni subas `.env`.

El esquema no inserta usuarios ni datos de demostración. El inicio de sesión y las operaciones CRUD corresponden a etapas posteriores.

## Comprobar PDO

Desde la raíz del proyecto ejecuta `php database/check_connection.php`. El script carga `.env`, realiza un `SELECT 1` preparado y devuelve un código distinto de cero si falla la conexión. Se requiere PHP con `pdo_mysql` habilitado y MySQL activo.
