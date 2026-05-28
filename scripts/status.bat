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

"%PHP%" scripts\check_status.php
set "RC=%ERRORLEVEL%"

echo.
if "%RC%"=="0" (
    echo Откройте в браузере: http://pay-tutor-uz/public/status.php
) else (
    echo Проверьте OSPanel, cloudflared и webhook.
    echo Документация: docs\DEMO_LAPTOP_SETUP.md ^(раздел 11^)
)

pause
exit /b %RC%
