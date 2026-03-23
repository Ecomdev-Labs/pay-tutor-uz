<?php

declare(strict_types=1);

// Подключение зависимостей
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/FreedomPay.php';

// Конфигурация
$botToken = 'YOUR_BOT_TOKEN_HERE'; // Замените на токен из BotFather
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

    // Гасим кнопку загрузки
    $bot->answerCallbackQuery($callbackId);

    // Получаем пользователя
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();

    if (!$user) {
        $bot->sendMessage($chatId, "Пожалуйста, начните с команды /start");
        exit;
    }

    if ($data === 'menu_main') {
        // Сброс состояния и возврат в главное меню
        $stmt = $pdo->prepare("UPDATE users SET state = NULL, selected_course = NULL, course_price = NULL WHERE chat_id = ?");
        $stmt->execute([$chatId]);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📚 Купить уроки', 'callback_data' => 'menu_buy_lessons']],
                [['text' => '🔒 Доступ в закрытый канал', 'callback_data' => 'menu_channel']],
                [['text' => '⚖️ Юридическая информация', 'callback_data' => 'menu_legal']],
                [['text' => '🎧 Поддержка', 'callback_data' => 'menu_support']]
            ]
        ];
        $bot->sendMessage($chatId, "<b>Главное меню:</b>", $keyboard);
        exit;
    }

    if ($data === 'menu_buy_lessons') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Индивидуальные (200,000 UZS)', 'callback_data' => 'course_individual']],
                [['text' => 'Парные (150,000 UZS)', 'callback_data' => 'course_pair']],
                [['text' => 'Групповые (100,000 UZS)', 'callback_data' => 'course_group']],
                [['text' => '🔙 Назад', 'callback_data' => 'menu_main']]
            ]
        ];
        $bot->sendMessage($chatId, "Выберите формат уроков:", $keyboard);
        exit;
    }

    if ($data === 'course_individual' || $data === 'course_pair' || $data === 'course_group') {
        $prices = [
            'course_individual' => 200000,
            'course_pair' => 150000,
            'course_group' => 100000 // Цена групповых из ТЗ/КП
        ];
        $price = $prices[$data];
        
        $stmt = $pdo->prepare("UPDATE users SET state = 'waiting_for_lessons_count', selected_course = ?, course_price = ? WHERE chat_id = ?");
        $stmt->execute([$data, $price, $chatId]);

        $bot->sendMessage($chatId, "Введите количество уроков (только цифрой, например: <b>5</b>):");
        exit;
        
    } elseif ($data === 'menu_channel') {
        $price = 300000;
        $orderId = uniqid('order_');
        
        // Создаем заказ, указывая тип продукта: 'channel'
        $stmt = $pdo->prepare("INSERT INTO orders (order_id, chat_id, amount, status, product_type) VALUES (?, ?, ?, 'pending', 'channel')");
        $stmt->execute([$orderId, $chatId, $price]);

        $paymentLink = $freedomPay->generatePaymentLink($orderId, $price, "Доступ в закрытый канал");

        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Оплатить 💳', 'url' => $paymentLink]],
                [['text' => '🔙 Назад', 'callback_data' => 'menu_main']]
            ]
        ];

        $formattedPrice = number_format($price, 0, '.', ' ');
        $bot->sendMessage($chatId, "Сумма к оплате за доступ в закрытый канал: <b>{$formattedPrice} UZS</b>", $keyboard);
        exit;

    } elseif ($data === 'menu_legal') {
        // Требование FreedomPay (PayBox)
        $legalText = "<b>Юридическая информация</b>\n\nИП / ООО «Ваше Название»\nИНН: 123456789\nОГРН/ОГРНИП: 1234567890123\n\n<a href='https://example.com/offer.pdf'>Публичная оферта</a>\n<a href='https://example.com/privacy.pdf'>Политика конфиденциальности</a>";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 Назад', 'callback_data' => 'menu_main']]
            ]
        ];
        $bot->sendMessage($chatId, $legalText, $keyboard);
        exit;

    } elseif ($data === 'menu_support') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 Назад', 'callback_data' => 'menu_main']]
            ]
        ];
        $bot->sendMessage($chatId, "Служба поддержки ответит на все ваши вопросы в рабочее время.\n\nПишите сюда: @SupportUsername", $keyboard);
        exit;
    }
}

// ---------------------------------------------------------
// 2. Обработка текстовых сообщений
// ---------------------------------------------------------
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $username = $message['chat']['username'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, username, state) VALUES (?, ?, NULL)");
        $stmt->execute([$chatId, $username]);
        $user = ['chat_id' => $chatId, 'state' => null, 'selected_course' => null, 'course_price' => null];
    }

    if ($text === '/start') {
        $stmt = $pdo->prepare("UPDATE users SET state = NULL, selected_course = NULL, course_price = NULL WHERE chat_id = ?");
        $stmt->execute([$chatId]);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📚 Купить уроки', 'callback_data' => 'menu_buy_lessons']],
                [['text' => '🔒 Доступ в закрытый канал', 'callback_data' => 'menu_channel']],
                [['text' => '⚖️ Юридическая информация', 'callback_data' => 'menu_legal']],
                [['text' => '🎧 Поддержка', 'callback_data' => 'menu_support']]
            ]
        ];

        // Приветственное сообщение
        $bot->sendMessage($chatId, "Добро пожаловать в бота нашей онлайн-школы! 🎓\n\nВыберите интересующий вас пункт меню ниже:", $keyboard);
        exit;
    }

    if ($user['state'] === 'waiting_for_lessons_count') {
        if (is_numeric($text) && (int)$text > 0) {
            $lessonsCount = (int)$text;
            $coursePrice = (int)$user['course_price'];
            $totalPrice = $lessonsCount * $coursePrice;
            $orderId = uniqid('order_');

            $stmt = $pdo->prepare("UPDATE users SET state = NULL WHERE chat_id = ?");
            $stmt->execute([$chatId]);

            // Сохраняем заказ, указывая тип: 'lessons'
            $stmt = $pdo->prepare("INSERT INTO orders (order_id, chat_id, amount, status, product_type) VALUES (?, ?, ?, 'pending', 'lessons')");
            $stmt->execute([$orderId, $chatId, $totalPrice]);

            $courseNames = [
                'course_individual' => 'Индивидуальные уроки',
                'course_pair' => 'Парные уроки',
                'course_group' => 'Групповые уроки'
            ];
            $courseName = $courseNames[$user['selected_course']] ?? 'Уроки';
            $paymentDescription = "$courseName ($lessonsCount шт.)";
            
            $paymentLink = $freedomPay->generatePaymentLink($orderId, $totalPrice, $paymentDescription);

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Оплатить 💳', 'url' => $paymentLink]],
                    [['text' => '🔙 В главное меню', 'callback_data' => 'menu_main']]
                ]
            ];

            $formattedPrice = number_format($totalPrice, 0, '.', ' ');
            $bot->sendMessage($chatId, "Вы выбрали <b>$courseName</b>.\nКоличество уроков: $lessonsCount\nСумма к оплате: <b>{$formattedPrice} UZS</b>", $keyboard);
        } else {
            $bot->sendMessage($chatId, "⚠️ Пожалуйста, введите корректное число уроков (например, <b>5</b>):");
        }
        exit;
    }
}
