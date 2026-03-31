@echo off
setlocal

set "PROJECT_DIR=C:\laragon\www\cotizaciones_automaticas"
set "PHP_EXE=C:\xampp\php\php.exe"
set "LOG_FILE=%PROJECT_DIR%\storage\logs\telegram-bot.log"

if not exist "%PROJECT_DIR%\storage\logs" (
    mkdir "%PROJECT_DIR%\storage\logs"
)

cd /d "%PROJECT_DIR%"
echo [%date% %time%] Iniciando listener de Telegram...>> "%LOG_FILE%"
"%PHP_EXE%" artisan telegram:escuchar-cotizaciones >> "%LOG_FILE%" 2>&1
echo [%date% %time%] Listener de Telegram finalizado.>> "%LOG_FILE%"

endlocal
