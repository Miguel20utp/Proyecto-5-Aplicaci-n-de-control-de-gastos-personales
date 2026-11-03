# Pruebas de autenticación y comprobaciones manuales

Con PHP 8.2+ y Composer instalados, desde la raíz del proyecto:

1. `composer install` y `composer dump-autoload`; comprueba el autoload con `php -r "require 'vendor/autoload.php'; var_dump(class_exists('App\\Services\\Router'));"`.
2. `php -S 127.0.0.1:8000 -t public` y abre `http://127.0.0.1:8000/`; debe mostrar la plantilla de inicio.
3. Abre `/ruta-inexistente`; debe responder con estado HTTP 404 y la vista correspondiente.
4. Verifica la carga de `app/Views/home.php` en la respuesta y Bootstrap en el layout.
5. Para probar excepciones, configura temporalmente una ruta con un handler que lance `RuntimeException`; el front controller debe responder HTTP 500 y mostrar el detalle solo con `APP_DEBUG=true`.

## Prueba automatizada del modelo

Configura `.env` apuntando a una base de datos desechable creada con `database/schema.sql`, instala Composer y ejecuta `php tests/authentication.php`. La prueba crea una cuenta aleatoria, valida el hash y la contraseña incorrecta, y elimina la cuenta al terminar. No la ejecutes contra una base con datos importantes.

Para las operaciones de categorías y aislamiento entre usuarios ejecuta `php tests/categories.php` con la misma configuración de base de pruebas. Comprueba crear, consultar, editar y eliminar, bloquea el borrado cuando hay movimientos y verifica que otro usuario no pueda consultar, modificar ni eliminar una categoría. La prueba elimina sus usuarios y registros al terminar.

## Gestión de categorías

Con sesión iniciada, abre `/categorias`. Las altas, ediciones y eliminaciones usan POST con token CSRF. La aplicación solo lista y modifica categorías del usuario autenticado y no permite borrar una categoría asociada a movimientos.

## Flujos HTTP

Con el servidor de desarrollo activo, comprueba:

1. `GET /` sin sesión responde con redirección a `/login`; con sesión válida muestra la portada.
2. Registra un correo válido y una contraseña de 8 caracteres o más. El usuario aparece en `usuarios` con un hash que no coincide con la contraseña; repetir el correo presenta un error entendible.
3. En `POST /login`, la contraseña correcta redirige a `/` y la incorrecta muestra un error genérico. Confirma que el ID de sesión cambia al iniciar sesión.
4. Envía un formulario de registro o login sin `_csrf` (o con un token alterado): debe responder HTTP 419 sin cambiar datos.
5. Cierra sesión usando el botón; debe volver a `/login`, y una petición posterior a `/` debe redirigir a login.

Para el CRUD, aislamiento entre cuentas y filtros de movimientos ejecuta php tests/movements.php con la misma configuración desechable. Comprueba registrar, consultar, editar, eliminar, filtros por fecha/categoría/tipo y rechazo de categorías ajenas o incompatibles.

## Dashboard y estadísticas

Con PHP 8.2+, Composer y `.env` configurado para una base de datos desechable creada con `database/schema.sql`, ejecuta `php tests/dashboard.php`. La prueba crea dos usuarios y verifica los totales de ingresos/gastos/saldo, rango de fechas, totales por categoría, evolución acumulada con saldo de apertura, aislamiento entre usuarios y resultados vacíos. Borra los usuarios creados al finalizar; no la ejecutes contra una base con datos importantes.

Para la interfaz, inicia `php -S 127.0.0.1:8000 -t public`, inicia sesión y abre `/`. Revisa los períodos todo el historial, mes actual, mes anterior, año actual y rango personalizado; la URL debe responder con error de validación ante fechas mal formadas o invertidas. Las gráficas usan Chart.js cargado desde CDN.

## Reportes y exportaci�n

Ejecuta `php tests/reports.php` contra una base de datos desechable configurada en `.env`. Comprueba filtros por fecha, tipo, categor�a y descripci�n, resumen financiero, aislamiento por usuario, CSV seguro y resultados vac�os. Con sesi�n iniciada, abre `/reportes` para revisar la interfaz y descarga.
