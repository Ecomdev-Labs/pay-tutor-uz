<?php

declare(strict_types=1);

/**
 * Регистрация Telegram webhook для demo-бота.
 * Usage: php scripts/set_webhook.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\TelegramBot;

Config::load();

$token = Config::botToken();
if ($token === '' || $token === 'YOUR_BOT_TOKEN_HERE') {
    fwrite(STDERR, "ERROR: Set BOT_TOKEN in .env (copy from .env.example)\n");
    exit(1);
}

$webhookUrl = Config::webhookUrl();
$bot = new TelegramBot($token);

echo "Deleting old webhook...\n";
$deleteResult = $bot->deleteWebhook(true);
echo json_encode($deleteResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "Setting webhook to: {$webhookUrl}\n";
$setResult = $bot->setWebhook($webhookUrl, true);
echo json_encode($setResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

$info = $bot->getWebhookInfo();
echo "Webhook info:\n";
echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

if (!empty($setResult['ok'])) {
    echo "\nOK: Webhook registered.\n";
    exit(0);
}

fwrite(STDERR, "\nERROR: Failed to set webhook.\n");
exit(1);
