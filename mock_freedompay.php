<?php
declare(strict_types=1);

/**
 * СИМУЛЯТОР FREEDOM PAY (PAYBOX)
 * Этот скрипт имитирует страницу оплаты, на которую переходит пользователь,
 * и отправляет callback-запрос (webhook) на ваш payment_webhook.php, как это сделала бы реальная платежная система.
 */

// Определяем базовый URL для отправки вебхука об успешной оплате
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
// Принудительно используем https, так как ngrok отдает наружу https
$webhookUrl = "https://$host/payment_webhook.php";

// Если форма отправлена (нажата кнопка "Оплатить" или "Отменить")
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['pg_order_id'] ?? '';
    $amount = $_POST['pg_amount'] ?? '';
    $action = $_POST['action'] ?? 'pay';

    // Подготовка данных для отправки на наш webhook
    $postData = http_build_query([
        'pg_order_id' => $orderId,
        'pg_result' => $action === 'pay' ? '1' : '0', // 1 - успех, 0 - ошибка
        'pg_amount' => $amount,
        // В реальном API тут передается еще много параметров и MD5-подпись (pg_sig)
        'pg_sig' => 'simulated_signature_mock'
    ]);

    // Отправляем POST-запрос (curl) на наш скрипт-обработчик (payment_webhook.php)
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // Для локальной разработки отключаем проверку SSL сертификатов
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // Добавляем следование за редиректами, если они есть
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $webhookResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Симулятор FreedomPay - Результат</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
            .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; }
            .success { color: #28a745; }
            .error { color: #dc3545; }
            .btn { background: #0088cc; color: white; border: none; padding: 12px 20px; font-size: 16px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 20px;}
            pre { background: #333; color: #fff; padding: 15px; border-radius: 5px; text-align: left; overflow-x: auto; font-size: 13px;}
        </style>
    </head>
    <body>
        <div class="card">
            <?php if ($action === 'pay'): ?>
                <h2 class="success">✅ Оплата успешно сымитирована!</h2>
                <p>Платежная система якобы списала деньги с карты и отправила скрытый запрос на ваш сервер.</p>
            <?php else: ?>
                <h2 class="error">❌ Оплата отклонена</h2>
                <p>Отправлен статус ошибки.</p>
            <?php endif; ?>

            <div style="text-align: left; margin-top: 20px;">
                <p><strong>Webhook отправлен на:</strong><br><small><?= htmlspecialchars($webhookUrl) ?></small></p>
                <p><strong>Ответ от вашего сервера (HTTP: <?= $httpCode ?>):</strong></p>
                <pre><?= htmlspecialchars($webhookResponse ?: 'Пустой ответ или ошибка cURL: ' . $curlError) ?></pre>
            </div>

            <p>Теперь вы можете закрыть это окно и вернуться в Telegram-бота.</p>
            <a href="https://t.me/" class="btn">Вернуться в Telegram</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Если это GET-запрос (пользователь перешел по ссылке из Telegram-бота)
$orderId = $_GET['pg_order_id'] ?? 'Неизвестно';
$amount = $_GET['pg_amount'] ?? '0';
$description = $_GET['pg_description'] ?? 'Без описания';

$formattedAmount = number_format((float)$amount, 0, '.', ' ');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Симулятор FreedomPay (Оплата)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 400px; width: 100%; }
        h2 { margin-top: 0; color: #333; text-align: center; border-bottom: 2px solid #eee; padding-bottom: 15px;}
        .info { margin-bottom: 30px; }
        .info p { margin: 10px 0; font-size: 16px; color: #555; }
        .info strong { color: #222; }
        .total { font-size: 24px; font-weight: bold; text-align: center; color: #28a745; margin: 20px 0; }
        .btn-pay { background: #28a745; color: white; border: none; padding: 15px 20px; font-size: 18px; border-radius: 8px; cursor: pointer; width: 100%; transition: 0.3s;}
        .btn-pay:hover { background: #218838; }
        .btn-cancel { background: #dc3545; color: white; border: none; padding: 15px 20px; font-size: 18px; border-radius: 8px; cursor: pointer; width: 100%; margin-top: 10px; transition: 0.3s;}
        .btn-cancel:hover { background: #c82333; }
        .mock-badge { background: #ffc107; color: #000; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; display: block; text-align: center; margin-bottom: 20px;}
    </style>
</head>
<body>
    <div class="card">
        <span class="mock-badge">ТЕСТОВЫЙ РЕЖИМ (СИМУЛЯТОР)</span>
        <h2>Страница оплаты</h2>
        
        <div class="info">
            <p><strong>Заказ ID:</strong> <?= htmlspecialchars($orderId) ?></p>
            <p><strong>Назначение:</strong> <?= htmlspecialchars($description) ?></p>
        </div>

        <div class="total">
            К оплате: <?= htmlspecialchars($formattedAmount) ?> UZS
        </div>
        
        <form method="POST">
            <input type="hidden" name="pg_order_id" value="<?= htmlspecialchars($orderId) ?>">
            <input type="hidden" name="pg_amount" value="<?= htmlspecialchars($amount) ?>">
            <button type="submit" name="action" value="pay" class="btn-pay">Оплатить (имитация успеха)</button>
            <button type="submit" name="action" value="cancel" class="btn-cancel">Отменить (имитация ошибки)</button>
        </form>
    </div>
</body>
</html>
