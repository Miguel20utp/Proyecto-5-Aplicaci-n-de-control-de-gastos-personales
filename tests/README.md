# Comprobaciones manuales

Con PHP 8.2+ y Composer instalados, desde la raíz del proyecto:

1. `composer install` y `composer dump-autoload`; comprueba el autoload con `php -r "require 'vendor/autoload.php'; var_dump(class_exists('App\\Services\\Router'));"`.
2. `php -S 127.0.0.1:8000 -t public` y abre `http://127.0.0.1:8000/`; debe mostrar la plantilla de inicio.
3. Abre `/ruta-inexistente`; debe responder con estado HTTP 404 y la vista correspondiente.
4. Verifica la carga de `app/Views/home.php` en la respuesta y Bootstrap en el layout.
5. Para probar excepciones, configura temporalmente una ruta con un handler que lance `RuntimeException`; el front controller debe responder HTTP 500 y mostrar el detalle solo con `APP_DEBUG=true`.
