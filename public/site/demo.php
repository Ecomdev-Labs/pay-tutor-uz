<?php
declare(strict_types=1);
// Демонстрационная страница для публикации / кейса
// Показывает problem-solution, воронку и позволяет запустить mock-покупку

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$mockUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $host . '/mock_freedompay.php';

// Параметры демонстрации
$orderId = 'demo_' . bin2hex(random_bytes(4));
$amount = 10000;
$description = 'Demo access';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Демо: автоматизация оплаты — кейс</title>
    <meta name="description" content="Кейс: Telegram-бот для онлайн-школы — прием платежей, выдача доступа и интеграции.">
    <meta name="robots" content="noindex,follow">
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header>
    <h1>Кейс: Telegram-бот — приём оплат и выдача доступа</h1>
    <nav><a href="/site/">Главная</a> | <a href="/site/login.php">Войти</a></nav>
</header>
<main>
    <section>
        <h2>Проблема</h2>
        <p>Онлайн-школа требует автоматизации процесса продажи уроков: от выбора формата до выдачи доступа и уведомления преподавателя.</p>
    </section>

    <section>
        <h2>Решение</h2>
        <p>Telegram-бот управляет выбором продукта, генерирует платеж и отслеживает статус через webhook. После подтверждения оплаты бот высылает одноразовую ссылку или сообщение с инструкциями.</p>
    </section>

    <section>
        <h2>Схема воронки</h2>
        <ol>
            <li>Пользователь открывает бот и выбирает товар.</li>
            <li>Бот формирует заказ и отправляет ссылку на платёж.</li>
            <li>Пользователь оплачивает (в демо — mock-платеж).</li>
            <li>Платёжная система вызывает webhook, система помечает заказ как paid.</li>
            <li>Бот отправляет доступ / инструкцию пользователю.</li>
        </ol>
    </section>

    <section>
        <h2>Стек и интеграции</h2>
        <ul>
            <li>PHP + PDO (SQLite / MySQL)</li>
            <li>Telegram Bot API</li>
            <li>Платёжный шлюз (FreedomPay) — mock-имитация для демо</li>
            <li>Cloudflare: DNS, CDN, Tunnel (рекомендация для продакшна)</li>
        </ul>
    </section>

    <section>
        <h2>Демо-поток оплаты</h2>
        <p>Ниже кнопка открывает страницу mock-платежа с заранее заполненными данными.</p>
        <form action="/mock_freedompay.php" method="get" target="_blank">
            <input type="hidden" name="pg_order_id" value="<?= htmlspecialchars($orderId) ?>">
            <input type="hidden" name="pg_amount" value="<?= htmlspecialchars($amount) ?>">
            <input type="hidden" name="pg_description" value="<?= htmlspecialchars($description) ?>">
            <button type="submit">Симулировать оплату (демо)</button>
        </form>
    </section>

    <section>
        <h2>CTA</h2>
        <p>Заинтересованы в похожем решении? Напишите нам в Telegram: @SupportUsername</p>
    </section>
</main>
<footer>
    <p>© <?= date('Y') ?> Demo</p>
</footer>
</body>
</html>