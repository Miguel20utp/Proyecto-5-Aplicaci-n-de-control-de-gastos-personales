# Arquitectura inicial

Aplicación PHP 8.2+ con Composer y namespace PSR-4 `App\` apuntando a `app/`.
`public/index.php` carga configuración, registra el manejo de errores y excepciones y deriva las peticiones al router. Las rutas registran métodos HTTP y controladores; los controladores renderizan vistas mediante `Controller::view()`, dentro del layout compartido.

La autenticación usa `App\Models\User` con la conexión PDO singleton existente. `/register` y `/login` aceptan GET y POST; `/` está protegido por `AuthMiddleware` y el cierre de sesión solo acepta POST con token CSRF. Las sesiones usan cookies HttpOnly/SameSite, modo estricto y regeneración del identificador al autenticar.

Copiar `.env.example` a `.env` para configurar el entorno. La plantilla Bootstrap se sirve desde CDN y los estilos propios están en `public/assets/css/app.css`.
