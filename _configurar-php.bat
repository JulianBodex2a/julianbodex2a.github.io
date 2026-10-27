@echo off
setlocal
title Configurar PHP

set "PHPDIR=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe"

if not exist "%PHPDIR%\php.exe" (
    echo [X] No encuentro php.exe en: %PHPDIR%
    pause & exit /b 1
)
if not exist "%PHPDIR%\ext" (
    echo [X] No existe la carpeta ext en: %PHPDIR%
    echo     Reinstala con: winget install --id PHP.PHP.8.4 -e
    pause & exit /b 1
)
echo  [OK] PHP y carpeta ext localizados

if not exist "%PHPDIR%\cacert.pem" (
    echo  [..] Descargando cacert.pem
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile '%PHPDIR%\cacert.pem' -UseBasicParsing"
)
if exist "%PHPDIR%\cacert.pem" (echo  [OK] cacert.pem listo) else (echo  [!] Bajalo a mano de https://curl.se/ca/cacert.pem)

echo  [..] Creando php.ini
> "%PHPDIR%\php.ini" echo [PHP]
>>"%PHPDIR%\php.ini" echo extension_dir = "%PHPDIR%\ext"
>>"%PHPDIR%\php.ini" echo.
>>"%PHPDIR%\php.ini" echo extension=curl
>>"%PHPDIR%\php.ini" echo extension=openssl
>>"%PHPDIR%\php.ini" echo extension=mbstring
>>"%PHPDIR%\php.ini" echo extension=fileinfo
>>"%PHPDIR%\php.ini" echo.
>>"%PHPDIR%\php.ini" echo curl.cainfo = "%PHPDIR%\cacert.pem"
>>"%PHPDIR%\php.ini" echo openssl.cafile = "%PHPDIR%\cacert.pem"
>>"%PHPDIR%\php.ini" echo.
>>"%PHPDIR%\php.ini" echo memory_limit = 256M
>>"%PHPDIR%\php.ini" echo max_execution_time = 0
>>"%PHPDIR%\php.ini" echo date.timezone = Europe/Madrid

echo.
echo  ===== COMPROBACION =====
php --ini
echo.
php -m | findstr /i "curl openssl"
echo.
echo  Si arriba aparecen curl y openssl, ya esta listo.
echo.
pause
endlocal