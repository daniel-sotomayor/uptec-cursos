@echo off
chcp 65001 >nul
:: UPTEC Cursos - Configuraci¢n de DNS Local en Windows Hosts
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script debe ejecutarse con permisos de Administrador.
    echo Por favor haz clic derecho sobre este archivo y selecciona "Ejecutar como administrador".
    echo.
    pause
    exit /b 1
)

set HOSTS=%SystemRoot%\System32\drivers\etc\hosts
findstr /i "uptec-cursos" "%HOSTS%" >nul
if %errorLevel% equ 0 (
    echo [INFO] Las entradas de 'uptec-cursos' ya existen en el archivo hosts.
) else (
    echo.>> "%HOSTS%"
    echo # VirtualHost UPTEC Cursos>> "%HOSTS%"
    echo 127.0.0.1 uptec-cursos>> "%HOSTS%"
    echo 127.0.0.1 uptec-cursos.local>> "%HOSTS%"
    echo [EXITO] Entradas agregadas correctamente a: %HOSTS%
    echo   - 127.0.0.1 uptec-cursos
    echo   - 127.0.0.1 uptec-cursos.local
)

echo.
ipconfig /flushdns
echo.
echo ========================================================
echo   Listo! Ya puedes acceder desde tu navegador a:
echo   http://uptec-cursos
echo ========================================================
echo.
pause
