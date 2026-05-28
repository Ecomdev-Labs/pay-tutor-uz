<?php
declare(strict_types=1);

/**
 * СИМУЛЯТОР FREEDOM PAY (PAYBOX)
 * Этот скрипт имитирует страницу оплаты, на которую переходит пользователь,
 * и отправляет callback-запрос (webhook) на ваш payment_webhook.php, как это сделала бы реальная платежная система.
 */

// Подключение зависимостей (composer autoload или fallback) — mock не использует классы, но подключаем для консистенции
require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;

Config::load();

$webhookUrl = Config::publicBaseUrl() . '/payment_webhook.php';
$botUsername = ltrim(Config::get('BOT_USERNAME', 'PayTutorDemoBot') ?? 'PayTutorDemoBot', '@');
$botTelegramUrl = 'https://t.me/' . $botUsername;
$isDemo = Config::getBool('DEMO_MODE', false);

// Запретить индексирование тестовой страницы
header('X-Robots-Tag: noindex, nofollow');

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
    // Если мы на встроенном PHP-сервере (cli-server), он однопоточный и
    // внутренний HTTP-запрос к тому же серверу заблокирует выполнение.
    // В этом случае симулируем webhook, делая прямой include обработчика.
    if (PHP_SAPI === 'cli-server') {
        $_POST['pg_order_id'] = $orderId;
        $_POST['pg_amount'] = $amount;
        $_POST['pg_result'] = ($action === 'pay') ? '1' : '0';
        $_POST['pg_sig'] = 'simulated_signature_mock';

        ob_start();
        require __DIR__ . '/payment_webhook.php';
        $webhookResponse = ob_get_clean();
        $curlError = '';
        $httpCode = 200;
    } else {
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
    }

    $paymentOk = ($action === 'pay' && $httpCode >= 200 && $httpCode < 300);

    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $paymentOk ? 'Оплата успешна' : 'Оплата не выполнена' ?> — PayTutor</title>
        <?php if ($paymentOk): ?>
        <meta http-equiv="refresh" content="4;url=<?= htmlspecialchars($botTelegramUrl) ?>">
        <?php endif; ?>
        <style>
            * { box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(160deg, #eef2ff 0%, #f0f9ff 100%); min-height: 100vh; margin: 0; display: flex; justify-content: center; align-items: center; padding: 20px; }
            .card { background: white; padding: 36px 32px; border-radius: 16px; box-shadow: 0 12px 40px rgba(0,136,204,0.12); max-width: 420px; width: 100%; text-align: center; }
            .icon { font-size: 56px; line-height: 1; margin-bottom: 12px; }
            h1 { margin: 0 0 12px; font-size: 1.5rem; color: #1e293b; }
            .success h1 { color: #15803d; }
            .error h1 { color: #dc2626; }
            p { color: #475569; line-height: 1.6; margin: 0 0 16px; }
            .order { background: #f8fafc; border-radius: 8px; padding: 12px 16px; margin: 20px 0; font-size: 0.95rem; color: #334155; }
            .btn { background: linear-gradient(135deg, #0088cc, #006699); color: white; border: none; padding: 14px 28px; font-size: 17px; font-weight: 600; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 8px; box-shadow: 0 4px 14px rgba(0,136,204,0.35); }
            .btn:hover { opacity: 0.95; }
            .hint { font-size: 0.85rem; color: #64748b; margin-top: 16px; }
            details { margin-top: 24px; text-align: left; font-size: 0.8rem; color: #64748b; }
            details summary { cursor: pointer; color: #94a3b8; }
            pre { background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 11px; margin-top: 8px; }
        </style>
    </head>
    <body>
        <div class="card <?= $paymentOk ? 'success' : 'error' ?>">
            <?php if ($paymentOk): ?>
                <div class="icon">✅</div>
                <h1>Оплата прошла успешно</h1>
                <p>Спасибо! Подтверждение и дальнейшие инструкции уже отправлены вам в Telegram.</p>
                <div class="order">
                    Заказ: <strong><?= htmlspecialchars($orderId) ?></strong><br>
                    Сумма: <strong><?= htmlspecialchars(number_format((float) $amount, 0, '.', ' ')) ?> UZS</strong>
                </div>
                <a href="<?= htmlspecialchars($botTelegramUrl) ?>" class="btn">Открыть чат с ботом</a>
                <p class="hint">Сейчас откроется Telegram (@<?= htmlspecialchars($botUsername) ?>) — приложение или веб-версия.</p>
            <?php else: ?>
                <div class="icon">❌</div>
                <h1>Оплата не выполнена</h1>
                <p>Платёж отменён или не прошёл. Вы можете вернуться в бот и попробовать снова.</p>
                <a href="<?= htmlspecialchars($botTelegramUrl) ?>" class="btn">Вернуться в бот</a>
            <?php endif; ?>

            <?php if ($isDemo): ?>
            <details>
                <summary>Технические детали (demo)</summary>
                <p>Webhook: <code><?= htmlspecialchars($webhookUrl) ?></code></p>
                <p>HTTP: <?= (int) $httpCode ?></p>
                <pre><?= htmlspecialchars($webhookResponse ?: ($curlError ?: '—')) ?></pre>
            </details>
            <?php endif; ?>
        </div>
        <?php if ($paymentOk): ?>
        <script>
            setTimeout(function () {
                window.location.href = <?= json_encode($botTelegramUrl, JSON_UNESCAPED_UNICODE) ?>;
            }, 3500);
        </script>
        <?php endif; ?>
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
            <button type="submit" name="action" value="pay" class="btn-pay">Оплатить</button>
            <button type="submit" name="action" value="cancel" class="btn-cancel">Отмена</button>
        </form>
    </div>
</body>
</html>
