<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\BotUpdateHandler;
use App\Config;
use App\Database;
use App\FreedomPay;
use App\TelegramBot;

Config::load();

header('X-Robots-Tag: noindex, nofollow');

$bot = new TelegramBot(Config::botToken());
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

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    file_put_contents(
        __DIR__ . '/bot_error.log',
        date('Y-m-d H:i:s') . ' - No webhook data received. Input: ' . print_r($input, true) . PHP_EOL,
        FILE_APPEND
    );
    exit('No webhook data received.');
}

$handler->handleUpdate($update);
