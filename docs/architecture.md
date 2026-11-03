# Arquitectura

La aplicación usa PHP 8.2+, Composer y el autoload PSR-4 `App\` para cargar clases desde `app/`.

- `public/index.php` es el front controller: carga Composer y `.env`, registra el manejo de errores, declara las rutas y despacha la petición.
- `app/Services/Router.php` resuelve rutas estáticas por método HTTP y ruta.
- `app/Controllers/` aplica autenticación y validación, invoca modelos y renderiza vistas.
- `app/Middleware/AuthMiddleware.php` exige una sesión autenticada para las rutas privadas.
- `app/Models/` contiene consultas PDO para usuarios, categorías, movimientos, dashboard, reportes y preferencias.
- `app/Config/` carga variables de entorno y mantiene una conexión PDO MySQL.
- `app/Services/Session.php` configura cookies de sesión y administra token CSRF y mensajes flash.
- `app/Views/` contiene páginas PHP y el layout compartido.
- `database/schema.sql` define las tablas y relaciones de la aplicación.

Las rutas se declaran en `public/index.php`. Los formularios mutables usan POST; sus controladores verifican tokens CSRF. Los modelos reciben el ID del usuario autenticado en las operaciones de datos que pertenecen a una cuenta.

## Rutas principales

| Método | Ruta | Acción |
| --- | --- | --- |
| GET/POST | `/register` | Formulario y creación de cuenta |
| GET/POST | `/login` | Formulario e inicio de sesión |
| POST | `/logout` | Cierre de sesión |
| GET | `/` | Dashboard |
| GET/POST | `/categorias`, `/categorias/nueva`, `/categorias/editar`, `/categorias/actualizar`, `/categorias/eliminar` | CRUD de categorías |
| GET/POST | `/movimientos`, `/movimientos/nuevo`, `/movimientos/editar`, `/movimientos/actualizar`, `/movimientos/eliminar` | CRUD de movimientos |
| GET | `/reportes`, `/reportes/exportar` | Reportes y descarga CSV |
| GET/POST | `/configuracion`, `/configuracion/perfil`, `/configuracion/contrasena`, `/configuracion/tema` | Perfil y preferencias |

La preferencia de tema se almacena en `configuraciones` y se adapta al atributo de tema claro/oscuro de Bootstrap en el layout.
