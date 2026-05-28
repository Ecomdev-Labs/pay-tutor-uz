<?php

declare(strict_types=1);

namespace App;

class BotUpdateHandler
{
    private TelegramBot $bot;
    private \PDO $pdo;
    private FreedomPay $freedomPay;
    private bool $demoMode;
    private string $siteUrl;
    private string $supportUsername;
    private string $teacherUsername;

    public function __construct(
        TelegramBot $bot,
        \PDO $pdo,
        FreedomPay $freedomPay,
        bool $demoMode = false,
        string $siteUrl = Config::PRODUCTION_SITE_ORIGIN,
        string $supportUsername = 'SupportUsername',
        string $teacherUsername = 'TeacherUsername'
    ) {
        $this->bot = $bot;
        $this->pdo = $pdo;
        $this->freedomPay = $freedomPay;
        $this->demoMode = $demoMode;
        $this->siteUrl = $siteUrl;
        $this->supportUsername = $supportUsername;
        $this->teacherUsername = $teacherUsername;
    }

    public function handleUpdate(array $update): void
    {
        $this->logUpdate($update);

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function logUpdate(array $update): void
    {
        $logPath = dirname(__DIR__) . '/public/bot_debug.log';
        file_put_contents(
            $logPath,
            date('Y-m-d H:i:s') . ' - Incoming update: ' . json_encode($update, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $chatId = (int) $callbackQuery['message']['chat']['id'];
        $data = (string) $callbackQuery['data'];
        $callbackId = (string) $callbackQuery['id'];

        $this->bot->answerCallbackQuery($callbackId);

        $user = $this->findUser($chatId);
        if (!$user) {
            $this->bot->sendMessage($chatId, 'Пожалуйста, начните с команды /start');
            return;
        }

        if ($data === 'menu_main') {
            $this->resetUserState($chatId);
            $this->bot->sendMessage($chatId, '<b>Главное меню:</b>', $this->mainMenuKeyboard());
            return;
        }

        if ($data === 'menu_buy_lessons') {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Индивидуальные (200,000 UZS)', 'callback_data' => 'course_individual']],
                    [['text' => 'Парные (150,000 UZS)', 'callback_data' => 'course_pair']],
                    [['text' => 'Групповые (100,000 UZS)', 'callback_data' => 'course_group']],
                    [['text' => '🔙 Назад', 'callback_data' => 'menu_main']],
                ],
            ];
            $this->bot->sendMessage($chatId, 'Выберите формат уроков:', $keyboard);
            return;
        }

        if (in_array($data, ['course_individual', 'course_pair', 'course_group'], true)) {
            $prices = [
                'course_individual' => 200000,
                'course_pair' => 150000,
                'course_group' => 100000,
            ];
            $price = $prices[$data];

            $stmt = $this->pdo->prepare(
                'UPDATE users SET state = ?, selected_course = ?, course_price = ? WHERE chat_id = ?'
            );
            $stmt->execute(['waiting_for_lessons_count', $data, $price, $chatId]);

            $this->bot->sendMessage($chatId, 'Введите количество уроков (только цифрой, например: <b>5</b>):');
            return;
        }

        if ($data === 'menu_channel') {
            $price = 300000;
            $orderId = uniqid('order_');

            $stmt = $this->pdo->prepare(
                'INSERT INTO orders (order_id, chat_id, amount, status, product_type) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$orderId, $chatId, $price, 'pending', 'channel']);

            $paymentLink = $this->freedomPay->generatePaymentLink($orderId, $price, 'Доступ в закрытый канал');

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Оплатить 💳', 'url' => $paymentLink]],
                    [['text' => '🔙 Назад', 'callback_data' => 'menu_main']],
                ],
            ];

            $formattedPrice = number_format($price, 0, '.', ' ');
            $this->bot->sendMessage(
                $chatId,
                "Сумма к оплате за доступ в закрытый канал: <b>{$formattedPrice} UZS</b>",
                $keyboard
            );
            return;
        }

        if ($data === 'menu_legal') {
            $legalText = "<b>Юридическая информация</b>\n\nИП / ООО «Demo Online School»\nИНН: 123456789\nОГРН/ОГРНИП: 1234567890123\n\n"
                . "<a href='https://example.com/offer.pdf'>Публичная оферта</a>\n"
                . "<a href='https://example.com/privacy.pdf'>Политика конфиденциальности</a>";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 Назад', 'callback_data' => 'menu_main']],
                ],
            ];
            $this->bot->sendMessage($chatId, $legalText, $keyboard);
            return;
        }

        if ($data === 'menu_support') {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 Назад', 'callback_data' => 'menu_main']],
                ],
            ];
            $this->bot->sendMessage(
                $chatId,
                "Служба поддержки ответит на все ваши вопросы в рабочее время.\n\nПишите сюда: @{$this->supportUsername}",
                $keyboard
            );
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleMessage(array $message): void
    {
        $chatId = (int) $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $username = $message['chat']['username'] ?? '';

        $user = $this->findUser($chatId);
        if (!$user) {
            $stmt = $this->pdo->prepare('INSERT INTO users (chat_id, username, state) VALUES (?, ?, NULL)');
            $stmt->execute([$chatId, $username]);
            $user = [
                'chat_id' => $chatId,
                'state' => null,
                'selected_course' => null,
                'course_price' => null,
            ];
        }

        if ($text === '/start') {
            $this->resetUserState($chatId);
            $this->sendWelcome($chatId);
            return;
        }

        if ($this->demoMode && $text === '/demo') {
            $this->sendDemoInstructions($chatId);
            return;
        }

        if ($this->demoMode && $text === '/reset') {
            $this->resetUserState($chatId);
            $this->bot->sendMessage(
                $chatId,
                "🔄 Состояние сброшено. Начните заново с /start или выберите пункт меню ниже:",
                $this->mainMenuKeyboard()
            );
            return;
        }

        if ($user['state'] === 'waiting_for_lessons_count') {
            $this->handleLessonsCountInput($chatId, $text, $user);
        }
    }

    private function sendWelcome(int $chatId): void
    {
        if ($this->demoMode) {
            $text = "🧪 <b>Demo-стенд PayTutor</b>\n\n"
                . "Это демонстрационный бот для тестирования воронки продаж онлайн-школы.\n"
                . "Оплата — mock, без реальных карт.\n\n"
                . "📖 Кейс: <a href=\"{$this->siteUrl}\">{$this->siteUrl}</a>\n"
                . "ℹ️ Инструкция: /demo\n"
                . "🔄 Сброс: /reset\n\n"
                . "Выберите пункт меню ниже:";
        } else {
            $text = "Добро пожаловать в бота нашей онлайн-школы! 🎓\n\nВыберите интересующий вас пункт меню ниже:";
        }

        $this->bot->sendMessage($chatId, $text, $this->mainMenuKeyboard());
    }

    private function sendDemoInstructions(int $chatId): void
    {
        $text = "<b>Как протестировать demo:</b>\n\n"
            . "1️⃣ Нажмите «Купить уроки» или «Доступ в закрытый канал»\n"
            . "2️⃣ Для уроков — введите количество (например, 3)\n"
            . "3️⃣ Нажмите «Оплатить» — откроется mock-страница\n"
            . "4️⃣ Подтвердите оплату на mock-странице\n"
            . "5️⃣ Вернитесь в Telegram — получите результат (номер заказа или invite-ссылку)\n\n"
            . "Команда /reset — начать сначала.";

        $this->bot->sendMessage($chatId, $text, $this->mainMenuKeyboard());
    }

    /**
     * @param array<string, mixed> $user
     */
    private function handleLessonsCountInput(int $chatId, string $text, array $user): void
    {
        if (!is_numeric($text) || (int) $text <= 0) {
            $this->bot->sendMessage($chatId, '⚠️ Пожалуйста, введите корректное число уроков (например, <b>5</b>):');
            return;
        }

        $lessonsCount = (int) $text;
        $coursePrice = (int) $user['course_price'];
        $totalPrice = $lessonsCount * $coursePrice;
        $orderId = uniqid('order_');

        $stmt = $this->pdo->prepare('UPDATE users SET state = NULL WHERE chat_id = ?');
        $stmt->execute([$chatId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (order_id, chat_id, amount, status, product_type) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $chatId, $totalPrice, 'pending', 'lessons']);

        $courseNames = [
            'course_individual' => 'Индивидуальные уроки',
            'course_pair' => 'Парные уроки',
            'course_group' => 'Групповые уроки',
        ];
        $courseName = $courseNames[$user['selected_course']] ?? 'Уроки';
        $paymentDescription = "$courseName ($lessonsCount шт.)";

        $paymentLink = $this->freedomPay->generatePaymentLink($orderId, $totalPrice, $paymentDescription);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Оплатить 💳', 'url' => $paymentLink]],
                [['text' => '🔙 В главное меню', 'callback_data' => 'menu_main']],
            ],
        ];

        $formattedPrice = number_format($totalPrice, 0, '.', ' ');
        $this->bot->sendMessage(
            $chatId,
            "Вы выбрали <b>{$courseName}</b>.\nКоличество уроков: {$lessonsCount}\nСумма к оплате: <b>{$formattedPrice} UZS</b>",
            $keyboard
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findUser(int $chatId)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE chat_id = ?');
        $stmt->execute([$chatId]);
        return $stmt->fetch();
    }

    private function resetUserState(int $chatId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET state = NULL, selected_course = NULL, course_price = NULL WHERE chat_id = ?'
        );
        $stmt->execute([$chatId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mainMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '📚 Купить уроки', 'callback_data' => 'menu_buy_lessons']],
                [['text' => '🔒 Доступ в закрытый канал', 'callback_data' => 'menu_channel']],
                [['text' => '⚖️ Юридическая информация', 'callback_data' => 'menu_legal']],
                [['text' => '🎧 Поддержка', 'callback_data' => 'menu_support']],
            ],
        ];
    }

    public function getTeacherUsername(): string
    {
        return $this->teacherUsername;
    }
}
