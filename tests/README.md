# Pruebas de autenticación y comprobaciones manuales

Con PHP 8.2+ y Composer instalados, desde la raíz del proyecto:

1. `composer install` y `composer dump-autoload`; comprueba el autoload con `php -r "require 'vendor/autoload.php'; var_dump(class_exists('App\\Services\\Router'));"`.
2. `php -S 127.0.0.1:8000 -t public` y abre `http://127.0.0.1:8000/`; debe mostrar la plantilla de inicio.
3. Abre `/ruta-inexistente`; debe responder con estado HTTP 404 y la vista correspondiente.
4. Verifica la carga de `app/Views/home.php` en la respuesta y Bootstrap en el layout.
5. Para probar excepciones, configura temporalmente una ruta con un handler que lance `RuntimeException`; el front controller debe responder HTTP 500 y mostrar el detalle solo con `APP_DEBUG=true`.

## Prueba automatizada del modelo

Configura `.env` apuntando a una base de datos desechable creada con `database/schema.sql`, instala Composer y ejecuta `php tests/authentication.php`. La prueba crea una cuenta aleatoria, valida el hash y la contraseña incorrecta, y elimina la cuenta al terminar. No la ejecutes contra una base con datos importantes.

## Flujos HTTP

Con el servidor de desarrollo activo, comprueba:

1. `GET /` sin sesión responde con redirección a `/login`; con sesión válida muestra la portada.
2. Registra un correo válido y una contraseña de 8 caracteres o más. El usuario aparece en `usuarios` con un hash que no coincide con la contraseña; repetir el correo presenta un error entendible.
3. En `POST /login`, la contraseña correcta redirige a `/` y la incorrecta muestra un error genérico. Confirma que el ID de sesión cambia al iniciar sesión.
4. Envía un formulario de registro o login sin `_csrf` (o con un token alterado): debe responder HTTP 419 sin cambiar datos.
5. Cierra sesión usando el botón; debe volver a `/login`, y una petición posterior a `/` debe redirigir a login.
