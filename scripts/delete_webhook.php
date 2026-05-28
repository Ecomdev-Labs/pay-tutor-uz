<?php

declare(strict_types=1);

/**
 * Снять Telegram webhook (перед запуском poll_bot.php).
 * Usage: php scripts/delete_webhook.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\TelegramBot;

Config::load();

$bot = new TelegramBot(Config::botToken());
$result = $bot->deleteWebhook(true);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

if (!empty($result['ok'])) {
    echo "OK: Webhook deleted. You can run: php scripts/poll_bot.php\n";
    exit(0);
}

exit(1);
