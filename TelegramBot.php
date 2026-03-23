<?php

declare(strict_types=1);

class TelegramBot
{
    private string $token;
    private string $apiUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
    }

    /**
     * Отправка сообщения пользователю
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null)
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $data);
    }

    /**
     * Ответ на нажатие inline кнопки (callback query)
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false)
    {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ];

        return $this->request('answerCallbackQuery', $data);
    }

    /**
     * Внутренний метод для отправки curl-запросов к Telegram API
     */
    private function request(string $method, array $data)
    {
        $ch = curl_init($this->apiUrl . $method);
        
        // Настройки cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Раскомментировать, если есть проблемы с SSL сертификатами

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Логируем ответы от Telegram
        $logData = date('Y-m-d H:i:s') . " - Request to: " . $method . PHP_EOL;
        $logData .= "Data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $logData .= "Response: " . ($response ?: "CURL ERROR: " . $curlError) . PHP_EOL . str_repeat("-", 40) . PHP_EOL;
        file_put_contents(__DIR__ . '/bot_debug.log', $logData, FILE_APPEND);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true) ?? false;
    }
}
