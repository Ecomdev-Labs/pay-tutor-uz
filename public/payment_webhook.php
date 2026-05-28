<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\Database;
use App\TelegramBot;

Config::load();

header('X-Robots-Tag: noindex, nofollow');

$bot = new TelegramBot(Config::botToken());
$db = new Database();
$pdo = $db->getPdo();

$adminChatId = Config::getInt('ADMIN_CHAT_ID', 0);
$channelId = Config::get('CHANNEL_ID', '-1001234567890') ?? '-1001234567890';
$teacherUsername = Config::get('TEACHER_USERNAME', 'TeacherUsername') ?? 'TeacherUsername';

$pgResult = $_POST['pg_result'] ?? null;
$orderId = $_POST['pg_order_id'] ?? null;

if ($pgResult === '1' && $orderId) {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);

    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $chatId = (int) $order['chat_id'];
            $productType = $order['product_type'] ?? 'lessons';

            if ($productType === 'channel') {
                $inviteLinkData = $bot->createChatInviteLink($channelId, 'Заказ: ' . $orderId, 1);

                if ($inviteLinkData && !empty($inviteLinkData['ok'])) {
                    $inviteLink = $inviteLinkData['result']['invite_link'];
                    $msg = "✅ <b>Оплата прошла успешно!</b>\n\n"
                        . "Ваша персональная (одноразовая) ссылка для вступления в закрытый канал:\n"
                        . $inviteLink;
                    $bot->sendMessage($chatId, $msg);
                } else {
                    $msg = "✅ Оплата прошла успешно, но не удалось сгенерировать ссылку. "
                        . "Пожалуйста, напишите в поддержку, указав номер заказа: <b>{$orderId}</b>";
                    $bot->sendMessage($chatId, $msg);
                }
            } else {
                $demoNote = Config::getBool('DEMO_MODE', false)
                    ? "\n\n<i>Demo-стенд: это тестовая оплата без реальных списаний.</i>"
                    : '';
                $msg = "✅ <b>Оплата успешно получена! Спасибо.</b>\n\n"
                    . "Номер вашего заказа: <b>{$orderId}</b>\n\n"
                    . "Для составления расписания занятий, пожалуйста, напишите нашему преподавателю: @{$teacherUsername}.\n"
                    . "<i>(Обязательно сообщите номер вашего заказа)</i>"
                    . $demoNote;
                $bot->sendMessage($chatId, $msg);
            }

            if ($adminChatId > 0) {
                $formattedAmount = number_format((int) $order['amount'], 0, '.', ' ');
                $adminMsg = "💰 <b>НОВАЯ ПРОДАЖА!</b>\n\n"
                    . "Заказ: {$orderId}\n"
                    . "Тип: {$productType}\n"
                    . "Сумма: {$formattedAmount} UZS\n"
                    . "ID Клиента: {$chatId}";
                $bot->sendMessage($adminChatId, $adminMsg);
            }
        }
    }
}

header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<response><pg_status>ok</pg_status></response>';
