@echo off
setlocal
cd /d "%~dp0.."

set "PHP="
where php >nul 2>&1 && set "PHP=php"
if not defined PHP if exist "D:\OSPanel\modules\php\PHP_8.1\php.exe" set "PHP=D:\OSPanel\modules\php\PHP_8.1\php.exe"
if not defined PHP if exist "D:\OSPanel\modules\php\PHP_8.2\php.exe" set "PHP=D:\OSPanel\modules\php\PHP_8.2\php.exe"

if not defined PHP (
    echo [ERROR] PHP не найден. Запустите OSPanel.
    pause
    exit /b 1
)

set "INTERVAL=60"
if not "%~1"=="" set "INTERVAL=%~1"

echo ========================================
echo   PayTutor Demo — фоновый монитор
echo   Интервал: %INTERVAL% сек
echo   Лог: data\monitor.log
echo   Telegram: уведомление при сбое/восстановлении
echo   Остановка: Ctrl+C
echo ========================================
echo.

:loop
"%PHP%" scripts\check_status.php --quiet --log data\monitor.log --notify
timeout /t %INTERVAL% /nobreak >nul
goto loop
