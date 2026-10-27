@echo off
setlocal EnableDelayedExpansion
title Catalogo - servidor local
cd /d "%~dp0"

set "PORT=8000"
set "WORKERS=6"
set "PHPEXE="
if not "%~1"=="" set "PORT=%~1"

echo ============================================
echo    SERVIDOR LOCAL DEL CATALOGO
echo ============================================
echo.

rem ---------- 1. localizar PHP ----------
for /f "delims=" %%p in ('where php 2^>nul') do if not defined PHPEXE set "PHPEXE=%%p"

if not defined PHPEXE (
    for /d %%d in ("C:\laragon\bin\php\php-*") do if exist "%%d\php.exe" set "PHPEXE=%%d\php.exe"
)
if not defined PHPEXE if exist "C:\php\php.exe" set "PHPEXE=C:\php\php.exe"
if not defined PHPEXE if exist "C:\xampp\php\php.exe" set "PHPEXE=C:\xampp\php\php.exe"
if not defined PHPEXE (
    for /d %%d in ("C:\wamp64\bin\php\php*") do if exist "%%d\php.exe" set "PHPEXE=%%d\php.exe"
)

if not defined PHPEXE (
    echo  [X] No encuentro PHP en este equipo.
    echo.
    echo      Instalalo con: winget install --id PHP.PHP.8.4 -e
    echo.
    pause
    exit /b 1
)
echo  [OK] PHP encontrado: !PHPEXE!

rem ---------- 2. comprobar curl ----------
"!PHPEXE!" -m | findstr /i "curl" >nul 2>&1
if errorlevel 1 (
    echo  [X] La extension curl no esta activa en PHP.
    echo      Ejecuta primero configurar-php.bat
    echo.
    pause
    exit /b 1
)
echo  [OK] extension curl activa

rem ---------- 3. comprobar archivos ----------
for %%f in (router.php api.php config.php index.html admin.html) do (
    if not exist "%%f" (
        echo  [X] Falta el archivo %%f en esta carpeta.
        echo      Este .bat debe estar junto al resto de archivos.
        echo.
        pause
        exit /b 1
    )
)
echo  [OK] archivos del proyecto completos

rem ---------- 4. puerto libre ----------
netstat -ano | findstr /c:":%PORT% " | findstr /i "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo  [!] El puerto %PORT% esta ocupado, usando el 8080.
    set "PORT=8080"
)

echo.
echo  ------------------------------------------------
echo    Web    :  http://localhost:!PORT!/
echo    Panel  :  http://localhost:!PORT!/admin.html
echo    m3u8   :  http://localhost:!PORT!/lista.m3u8
echo  ------------------------------------------------
echo.
echo    Ctrl+C para detener el servidor.
echo    (Si Windows pregunta por el firewall, dale Permitir)
echo.

rem ---------- 5. abrir navegador ----------
start "" cmd /c "timeout /t 2 >nul && start http://localhost:!PORT!/admin.html"

rem ---------- 6. arrancar ----------
set "PHP_CLI_SERVER_WORKERS=%WORKERS%"
"!PHPEXE!" -d output_buffering=0 -d display_errors=1 -S localhost:!PORT! router.php

echo.
echo  Servidor detenido.
pause
endlocal