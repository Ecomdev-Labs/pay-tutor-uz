<?php

declare(strict_types=1);


// Подключение зависимостей (composer autoload или fallback)
require_once __DIR__ . '/../src/bootstrap.php';
use App\Database;
use App\TelegramBot;
use App\FreedomPay;

// Конфигурация
$botToken = 'YOUR_BOT_TOKEN_HERE'; // Замените на токен из BotFather
$merchantId = 'TEST_MERCHANT'; // Тот же merchant ID
$secretKey = 'test_secret'; // Тот же секретный ключ FreedomPay

$adminChatId = 123456789; // ID владельца бота (ваш ID для уведомлений)
$channelId = '-1001234567890'; // Замените на реальный ID закрытого канала

// Инициализация сервисов
$bot = new TelegramBot($botToken);
$db = new Database();
$pdo = $db->getPdo();
$freedomPay = new FreedomPay($merchantId, $secretKey);

// Технический webhook — не индексировать
header('X-Robots-Tag: noindex, nofollow');

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
        // Достаем инфу о заказе
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $chatId = (int)$order['chat_id'];
            $productType = $order['product_type'] ?? 'lessons';
            
            // Если купили доступ в закрытый канал
            if ($productType === 'channel') {
                // Создаем одноразовую ссылку на вступление (member_limit: 1)
                $inviteLinkData = $bot->createChatInviteLink($channelId, "Заказ: " . $orderId, 1);
                
                if ($inviteLinkData && $inviteLinkData['ok']) {
                    $inviteLink = $inviteLinkData['result']['invite_link'];
                    $msg = "✅ <b>Оплата прошла успешно!</b>\n\nВаша персональная (одноразовая) ссылка для вступления в закрытый канал:\n" . $inviteLink;
                    $bot->sendMessage($chatId, $msg);
                } else {
                    $msg = "✅ Оплата прошла успешно, но не удалось сгенерировать ссылку. Пожалуйста, напишите в поддержку, указав номер заказа: <b>{$orderId}</b>";
                    $bot->sendMessage($chatId, $msg);
                }
            } else {
                // Если купили уроки
                $msg = "✅ <b>Оплата успешно получена! Спасибо.</b>\n\nНомер вашего заказа: <b>{$orderId}</b>\n\nДля составления расписания занятий, пожалуйста, напишите нашему преподавателю: @TeacherUsername.\n<i>(Обязательно сообщите ей/ему номер вашего заказа)</i>";
                $bot->sendMessage($chatId, $msg);
            }

            // Уведомление администратору о продаже
            $formattedAmount = number_format((int)$order['amount'], 0, '.', ' ');
            $adminMsg = "💰 <b>НОВАЯ ПРОДАЖА!</b>\n\nЗаказ: {$orderId}\nТип: {$productType}\nСумма: {$formattedAmount} UZS\nID Клиента: {$chatId}";
            $bot->sendMessage($adminChatId, $adminMsg);
        }
    }
}

// Отвечаем FreedomPay, чтобы они прекратили слать callback'и (формат XML)
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<response><pg_status>ok</pg_status></response>';
