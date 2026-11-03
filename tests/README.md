# Pruebas y validación manual

## Requisitos para las pruebas automatizadas

Las pruebas de integración necesitan PHP 8.2+, Composer, `pdo_mysql` y una base MySQL desechable creada con `database/schema.sql`. Configura `.env` para apuntar a esa base. **No las ejecutes en una base con información que quieras conservar.** Las pruebas crean usuarios y movimientos y los eliminan al terminar. Ejecuta un archivo por vez desde la raíz del proyecto:

```bash
composer install
php tests/authentication.php
php tests/categories.php
php tests/movements.php
php tests/dashboard.php
php tests/reports.php
php tests/settings.php
```

Cada archivo prueba, respectivamente: alta y hash de contraseñas; CRUD y aislamiento de categorías; CRUD, compatibilidad de categoría y filtros de movimientos; períodos, estadísticas y aislamiento del dashboard; filtros y resúmenes de reportes; perfil, contraseña, tema y utilidades CSRF.

`tests/reports.php` verifica el BOM y reproduce la regla de escape de celdas de fórmula en un CSV temporal. No llama al controlador ni comprueba la respuesta HTTP real de `/reportes/exportar`.

Para probar la conexión configurada, ejecuta `php database/check_connection.php`. El script requiere `.env`, Composer y un servidor MySQL accesible.

## Lista de comprobación manual en navegador

Inicia MySQL, configura `.env`, instala Composer y ejecuta `php -S 127.0.0.1:8000 -t public`. Prueba lo siguiente:

1. Sin iniciar sesión, abre `/` y confirma la redirección a `/login`. Abre una ruta inexistente y confirma la página 404.
2. Registra una cuenta, cierra sesión e inicia con la contraseña correcta. Comprueba que una contraseña incorrecta no permite entrar y que el cierre de sesión vuelve a `/login`.
3. Reenvía registro, inicio de sesión, cierre de sesión y un formulario CRUD sin `_csrf` o con un token modificado: deben responder 419 y no cambiar datos.
4. Crea categorías de ingreso y gasto. Edita sus nombres y colores. Confirma que una categoría usada no se puede eliminar ni cambiar de tipo.
5. Registra ingresos y gastos con fechas distintas. Comprueba edición y eliminación, además de filtros por fecha, tipo y categoría.
6. Crea una segunda cuenta y confirma que no puede ver ni cambiar categorías o movimientos de la primera; prueba también un ID ajeno en una URL de edición.
7. En el dashboard, recorre historial completo, mes actual, mes anterior, año actual y rango personalizado. Prueba fechas inválidas e invertidas. Contrasta saldos y gráficas con los movimientos ingresados.
8. En reportes, combina filtros y compara totales y filas. Descarga el CSV y verifica que Excel/LibreOffice lo abra como UTF-8 delimitado por punto y coma. Como comprobación manual de fórmula, exporta una descripción que empiece con `=`, `+`, `-` o `@` y confirma que se antepone un apóstrofo.
9. Cambia el perfil, cambia la contraseña usando la actual, y guarda tema claro y oscuro. Recarga y vuelve a iniciar sesión para confirmar que el tema persiste.
10. En herramientas del navegador, revisa que textos de entrada se muestren como texto y no como HTML ejecutable.

Las pruebas automatizadas de modelos no sustituyen estos recorridos HTTP. Para Apache/Nginx, repite las rutas con el document root configurado en `public/`.
