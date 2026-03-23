<?php

declare(strict_types=1);

// Подключение зависимостей
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/FreedomPay.php';

// Конфигурация
$botToken = '8713487564:AAHmAy0C0niI84oDGysjlkmTRn_CHv7xkZ8'; // Замените на токен из BotFather
$merchantId = 'TEST_MERCHANT'; // Замените на реальный merchant ID
$secretKey = 'test_secret'; // Замените на реальный секретный ключ FreedomPay

// Инициализация сервисов
$bot = new TelegramBot($botToken);
$db = new Database(); // При первом запуске создаст базу и таблицы
$pdo = $db->getPdo();

// URL для симулятора FreedomPay (формируется автоматически на основе текущего домена)
$protocol = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$mockInitUrl = "$protocol://$host/mock_freedompay.php";

$freedomPay = new FreedomPay($merchantId, $secretKey, $mockInitUrl);

// Получаем Webhook от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    file_put_contents(__DIR__ . '/bot_error.log', date('Y-m-d H:i:s') . " - No webhook data received. Input: " . print_r($input, true) . PHP_EOL, FILE_APPEND);
    exit('No webhook data received.');
}

// Логируем входящий запрос для дебага
file_put_contents(__DIR__ . '/bot_debug.log', date('Y-m-d H:i:s') . " - Incoming update: " . json_encode($update, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// ---------------------------------------------------------
// 1. Обработка Callback-запросов (Нажатия на инлайн-кнопки)
// ---------------------------------------------------------
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    $callbackId = $callbackQuery['id'];

    // Обязательно "гасим" состояние загрузки у кнопки
    $bot->answerCallbackQuery($callbackId);

    // Получаем состояние пользователя из БД
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();

    if (!$user) {
        $bot->sendMessage($chatId, "Пожалуйста, начните с команды /start");
        exit;
    }

    if ($data === 'course_individual' || $data === 'course_pair') {
        $price = $data === 'course_individual' ? 200000 : 150000;
        
        // Обновляем состояние на "ожидание количества уроков"
        $stmt = $pdo->prepare("UPDATE users SET state = 'waiting_for_lessons_count', selected_course = ?, course_price = ? WHERE chat_id = ?");
        $stmt->execute([$data, $price, $chatId]);

        $bot->sendMessage($chatId, "Введите количество уроков (цифрой, например: 5):");
        
    } elseif ($data === 'course_channel') {
        $price = 300000;
        $orderId = uniqid('order_'); // Генерируем уникальный ID заказа
        
        // Создаем запись заказа в БД
        $stmt = $pdo->prepare("INSERT INTO orders (order_id, chat_id, amount, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$orderId, $chatId, $price]);

        // Генерируем ссылку FreedomPay
        $paymentLink = $freedomPay->generatePaymentLink($orderId, $price, "Доступ в канал");

        // Отправляем кнопку "Оплатить"
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Оплатить 💳', 'url' => $paymentLink]]
            ]
        ];

        $formattedPrice = number_format($price, 0, '.', ' ');
        $bot->sendMessage($chatId, "Сумма к оплате: {$formattedPrice} UZS", $keyboard);
    }
    exit;
}

// ---------------------------------------------------------
// 2. Обработка текстовых сообщений
// ---------------------------------------------------------
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $username = $message['chat']['username'] ?? '';

    // Получаем или создаем пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();

    if (!$user) {
        // Добавляем пользователя, если он пишет впервые
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, username, state) VALUES (?, ?, NULL)");
        $stmt->execute([$chatId, $username]);
        $user = ['chat_id' => $chatId, 'state' => null, 'selected_course' => null, 'course_price' => null];
    }

    // Команда /start
    if ($text === '/start') {
        // Сброс состояния
        $stmt = $pdo->prepare("UPDATE users SET state = NULL, selected_course = NULL, course_price = NULL WHERE chat_id = ?");
        $stmt->execute([$chatId]);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Индивидуальные (200,000 UZS)', 'callback_data' => 'course_individual']],
                [['text' => 'Парные (150,000 UZS)', 'callback_data' => 'course_pair']],
                [['text' => 'Доступ в канал (300,000 UZS)', 'callback_data' => 'course_channel']]
            ]
        ];

        $bot->sendMessage($chatId, "Добро пожаловать в бота курсов немецкого языка!\nПожалуйста, выберите интересующий вас формат:", $keyboard);
        exit;
    }

    // Обработка состояния "ожидание количества уроков"
    if ($user['state'] === 'waiting_for_lessons_count') {
        // Проверяем, ввел ли пользователь число больше 0
        if (is_numeric($text) && (int)$text > 0) {
            $lessonsCount = (int)$text;
            $coursePrice = (int)$user['course_price'];
            $totalPrice = $lessonsCount * $coursePrice;
            $orderId = uniqid('order_');

            // Сброс состояния, так как мы получили нужные данные
            $stmt = $pdo->prepare("UPDATE users SET state = NULL WHERE chat_id = ?");
            $stmt->execute([$chatId]);

            // Сохраняем заказ в статусе pending (в ожидании)
            $stmt = $pdo->prepare("INSERT INTO orders (order_id, chat_id, amount, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$orderId, $chatId, $totalPrice]);

            // Определение названия для описания платежа
            $courseName = $user['selected_course'] === 'course_individual' ? 'Индивидуальные уроки' : 'Парные уроки';
            $paymentDescription = "$courseName ($lessonsCount шт.)";
            
            // Генерация платежной ссылки
            $paymentLink = $freedomPay->generatePaymentLink($orderId, $totalPrice, $paymentDescription);

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Оплатить 💳', 'url' => $paymentLink]]
                ]
            ];

            $formattedPrice = number_format($totalPrice, 0, '.', ' ');
            $bot->sendMessage($chatId, "Вы выбрали $courseName. Количество уроков: $lessonsCount\nСумма к оплате: {$formattedPrice} UZS", $keyboard);
        } else {
            // Если ввели не число
            $bot->sendMessage($chatId, "Пожалуйста, введите корректное число уроков (например, 5):");
        }
        exit;
    }
}
