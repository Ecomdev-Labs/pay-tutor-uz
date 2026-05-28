<?php

declare(strict_types=1);

/**
 * Long polling fallback (когда webhook/tunnel недоступен).
 * Перед запуском снимите webhook: php scripts/set_webhook.php с delete-only или deleteWebhook вручную.
 *
 * Usage: php scripts/poll_bot.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\BotUpdateHandler;
use App\Config;
use App\Database;
use App\FreedomPay;
use App\TelegramBot;

Config::load();

$token = Config::botToken();
if ($token === '' || $token === 'YOUR_BOT_TOKEN_HERE') {
    fwrite(STDERR, "ERROR: Set BOT_TOKEN in .env\n");
    exit(1);
}

$bot = new TelegramBot($token);
$db = new Database();
$pdo = $db->getPdo();

$freedomPay = new FreedomPay(
    Config::get('MERCHANT_ID', 'TEST_MERCHANT') ?? 'TEST_MERCHANT',
    Config::get('SECRET_KEY', 'test_secret') ?? 'test_secret',
    Config::mockPaymentUrl()
);

$handler = new BotUpdateHandler(
    $bot,
    $pdo,
    $freedomPay,
    Config::getBool('DEMO_MODE', false),
    Config::get('SITE_URL', 'https://pay-tutor.ecomdev.uz') ?? 'https://pay-tutor.ecomdev.uz',
    Config::get('SUPPORT_USERNAME', 'SupportUsername') ?? 'SupportUsername',
    Config::get('TEACHER_USERNAME', 'TeacherUsername') ?? 'TeacherUsername'
);

echo "Polling mode. Press Ctrl+C to stop.\n";
echo "Mock payment URL: " . Config::mockPaymentUrl() . "\n\n";

$offset = 0;

while (true) {
    $response = $bot->getUpdates($offset, 30);

    if ($response === false) {
        fwrite(STDERR, date('Y-m-d H:i:s') . " - getUpdates failed, retry in 5s...\n");
        sleep(5);
        continue;
    }

    if (empty($response['ok']) || empty($response['result'])) {
        continue;
    }

    foreach ($response['result'] as $update) {
        $offset = (int) $update['update_id'] + 1;
        try {
            $handler->handleUpdate($update);
        } catch (Throwable $e) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' - Handler error: ' . $e->getMessage() . "\n");
        }
    }
}
