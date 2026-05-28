@echo off
setlocal
cd /d "%~dp0.."

echo ========================================
echo   PayTutor Demo - Quick Start Check
echo ========================================
echo.

if not exist ".env" (
    echo [WARN] .env not found. Copy .env.example to .env and fill BOT_TOKEN, etc.
    echo        copy .env.example .env
    echo.
) else (
    echo [OK] .env exists
)

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP not in PATH. Start OSPanel and use its PHP.
    goto :end
)
echo [OK] PHP found

echo.
echo Registering Telegram webhook...
php scripts\set_webhook.php
if errorlevel 1 (
    echo [ERROR] Webhook registration failed. Check BOT_TOKEN and PUBLIC_BASE_URL in .env
    goto :end
)

echo.
echo ========================================
echo   Demo checklist
echo ========================================
echo   1. OSPanel running, site opens locally
echo   2. cloudflared tunnel active (demo-api.pay-tutor.ecomdev.uz)
echo   3. Demo bot created in @BotFather
echo   4. Bot is admin in test channel (for channel access demo)
echo.
echo   Mock URL:  https://demo-api.pay-tutor.ecomdev.uz/public/mock_freedompay.php
echo   Webhook:   https://demo-api.pay-tutor.ecomdev.uz/public/index.php
echo   Case site:   https://pay-tutor.ecomdev.uz
echo.
echo   Fallback polling (no tunnel): php scripts\poll_bot.php
echo   (requires deleteWebhook first)
echo.

:end
pause
