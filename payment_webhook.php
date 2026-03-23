<?php

declare(strict_types=1);

// Подключение зависимостей
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/FreedomPay.php';

// Конфигурация
$botToken = 'YOUR_TELEGRAM_BOT_TOKEN_HERE'; // Замените на токен из BotFather
$merchantId = 'TEST_MERCHANT'; // Тот же merchant ID
$secretKey = 'test_secret'; // Тот же секретный ключ FreedomPay

// Инициализация сервисов
$bot = new TelegramBot($botToken);
$db = new Database();
$pdo = $db->getPdo();
$freedomPay = new FreedomPay($merchantId, $secretKey);

// Данные, приходящие от FreedomPay
// FreedomPay обычно отправляет POST запросы с форматированными данными
$pgResult = $_POST['pg_result'] ?? null;
$orderId = $_POST['pg_order_id'] ?? null;
// $pgSig = $_POST['pg_sig'] ?? null; // В реальном проекте необходимо проверить подпись!

// Проверяем статус платежа
if ($pgResult === '1' && $orderId) {
    // В реальном проекте необходимо генерировать подпись на основе полученных POST параметров
    // и сравнивать её с $pgSig, чтобы убедиться, что запрос действительно от FreedomPay.

    // Обновляем статус заказа в базе
    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);

    // Если заказ действительно обновился (был pending), уведомляем пользователя
    if ($stmt->rowCount() > 0) {
        // Достаем chat_id
        $stmt = $pdo->prepare("SELECT chat_id FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $chatId = (int)$order['chat_id'];
            $bot->sendMessage($chatId, "✅ Оплата прошла успешно! Спасибо за покупку. Скоро мы с вами свяжемся для подтверждения.");
        }
    }
}

// Отвечаем FreedomPay, чтобы они прекратили слать callback'и (формат XML)
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<response><pg_status>ok</pg_status></response>';
