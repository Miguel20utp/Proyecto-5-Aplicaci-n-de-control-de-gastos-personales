# Pruebas de autenticaci贸n y comprobaciones manuales

Con PHP 8.2+ y Composer instalados, desde la ra铆z del proyecto:

1. `composer install` y `composer dump-autoload`; comprueba el autoload con `php -r "require 'vendor/autoload.php'; var_dump(class_exists('App\\Services\\Router'));"`.
2. `php -S 127.0.0.1:8000 -t public` y abre `http://127.0.0.1:8000/`; debe mostrar la plantilla de inicio.
3. Abre `/ruta-inexistente`; debe responder con estado HTTP 404 y la vista correspondiente.
4. Verifica la carga de `app/Views/home.php` en la respuesta y Bootstrap en el layout.
5. Para probar excepciones, configura temporalmente una ruta con un handler que lance `RuntimeException`; el front controller debe responder HTTP 500 y mostrar el detalle solo con `APP_DEBUG=true`.

## Prueba automatizada del modelo

Configura `.env` apuntando a una base de datos desechable creada con `database/schema.sql`, instala Composer y ejecuta `php tests/authentication.php`. La prueba crea una cuenta aleatoria, valida el hash y la contrase帽a incorrecta, y elimina la cuenta al terminar. No la ejecutes contra una base con datos importantes.

Para las operaciones de categor铆as y aislamiento entre usuarios ejecuta `php tests/categories.php` con la misma configuraci贸n de base de pruebas. Comprueba crear, consultar, editar y eliminar, bloquea el borrado cuando hay movimientos y verifica que otro usuario no pueda consultar, modificar ni eliminar una categor铆a. La prueba elimina sus usuarios y registros al terminar.

## Gesti贸n de categor铆as

Con sesi贸n iniciada, abre `/categorias`. Las altas, ediciones y eliminaciones usan POST con token CSRF. La aplicaci贸n solo lista y modifica categor铆as del usuario autenticado y no permite borrar una categor铆a asociada a movimientos.

## Flujos HTTP

Con el servidor de desarrollo activo, comprueba:

1. `GET /` sin sesi贸n responde con redirecci贸n a `/login`; con sesi贸n v谩lida muestra la portada.
2. Registra un correo v谩lido y una contrase帽a de 8 caracteres o m谩s. El usuario aparece en `usuarios` con un hash que no coincide con la contrase帽a; repetir el correo presenta un error entendible.
3. En `POST /login`, la contrase帽a correcta redirige a `/` y la incorrecta muestra un error gen茅rico. Confirma que el ID de sesi贸n cambia al iniciar sesi贸n.
4. Env铆a un formulario de registro o login sin `_csrf` (o con un token alterado): debe responder HTTP 419 sin cambiar datos.
5. Cierra sesi贸n usando el bot贸n; debe volver a `/login`, y una petici贸n posterior a `/` debe redirigir a login.

Para el CRUD, aislamiento entre cuentas y filtros de movimientos ejecuta php tests/movements.php con la misma configuraci贸n desechable. Comprueba registrar, consultar, editar, eliminar, filtros por fecha/categor铆a/tipo y rechazo de categor铆as ajenas o incompatibles.

## Dashboard y estad铆sticas

Con PHP 8.2+, Composer y `.env` configurado para una base de datos desechable creada con `database/schema.sql`, ejecuta `php tests/dashboard.php`. La prueba crea dos usuarios y verifica los totales de ingresos/gastos/saldo, rango de fechas, totales por categor铆a, evoluci贸n acumulada con saldo de apertura, aislamiento entre usuarios y resultados vac铆os. Borra los usuarios creados al finalizar; no la ejecutes contra una base con datos importantes.

Para la interfaz, inicia `php -S 127.0.0.1:8000 -t public`, inicia sesi贸n y abre `/`. Revisa los per铆odos todo el historial, mes actual, mes anterior, a帽o actual y rango personalizado; la URL debe responder con error de validaci贸n ante fechas mal formadas o invertidas. Las gr谩ficas usan Chart.js cargado desde CDN.

## Reportes y exportaci髇

Ejecuta `php tests/reports.php` contra una base de datos desechable configurada en `.env`. Comprueba filtros por fecha, tipo, categor韆 y descripci髇, resumen financiero, aislamiento por usuario, CSV seguro y resultados vac韔s. Con sesi髇 iniciada, abre `/reportes` para revisar la interfaz y descarga.

## Configuraci髇 y preferencias

El esquema inicial ya incluye la tabla configuraciones con 韓dice 鷑ico por usuario y clave; no requiere migraci髇 adicional. Ejecuta php tests/settings.php contra una base desechable creada con database/schema.sql para comprobar lectura y actualizaci髇 del perfil, detecci髇 de correo duplicado, hash de contrase馻, persistencia y aislamiento de tema y validaci髇 CSRF. Las operaciones de perfil, contrase馻 y tema deben enviarse por POST con token CSRF; las contrase馻s y los formatos de correo tambi閚 se validan en el controlador.
