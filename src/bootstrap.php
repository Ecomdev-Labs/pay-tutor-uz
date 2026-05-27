<?php
// Autoload fallback: use composer's autoload if available, otherwise require src files

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/TelegramBot.php';
    require_once __DIR__ . '/FreedomPay.php';
    require_once __DIR__ . '/database.php';
}
