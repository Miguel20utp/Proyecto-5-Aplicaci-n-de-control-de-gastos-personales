@echo off
title Control de Gastos Personales - UTP IS873
echo =====================================================================
echo  Iniciando Aplicacion: Control de Gastos Personales (IS873 - UTP)
echo =====================================================================
echo.

REM 1. Verificar MariaDB
echo [1/3] Verificando servicio de base de datos MySQL/MariaDB...
netstat -ano | findstr :3306 >nul
if %errorlevel% neq 0 (
    echo [INFO] Iniciando servidor MariaDB en segundo plano...
    start "" /b "C:\Program Files\MariaDB 13.0\bin\mariadbd.exe" --defaults-file="C:\Program Files\MariaDB 13.0\data\my.ini" --console
    timeout /t 3 >nul
) else (
    echo [OK] El servicio de base de datos MySQL/MariaDB ya se encuentra activo en el puerto 3306.
)

REM 2. Iniciar servidor PHP en segundo plano
echo [2/3] Iniciando servidor web PHP en http://127.0.0.1:8000 ...
start "PHP Web Server" /b "C:\Users\User\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S 127.0.0.1:8000 -t public

REM 3. Abrir navegador
echo [3/3] Abriendo aplicacion en el navegador web...
timeout /t 2 >nul
start http://127.0.0.1:8000

echo.
echo =====================================================================
echo  Aplicacion ejecutandose en: http://127.0.0.1:8000
echo  Credenciales de acceso: admin@utp.edu.co / Admin123*
echo =====================================================================
echo Para detener el servidor cierre esta ventana.
echo.
pause
