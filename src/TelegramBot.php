<?php

declare(strict_types=1);

namespace App;

class TelegramBot
{
    private string $token;
    private string $apiUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
    }

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

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false)
    {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ];

        return $this->request('answerCallbackQuery', $data);
    }

    public function createChatInviteLink($chatId, string $name = '', int $memberLimit = 1)
    {
        $data = [
            'chat_id' => $chatId,
            'member_limit' => $memberLimit,
        ];

        if ($name !== '') {
            $data['name'] = $name;
        }

        return $this->request('createChatInviteLink', $data);
    }

    /**
     * @return array|false
     */
    public function getUpdates(int $offset = 0, int $timeout = 30)
    {
        $query = ['timeout' => $timeout];
        if ($offset > 0) {
            $query['offset'] = $offset;
        }

        return $this->request('getUpdates', $query, 'GET');
    }

    /**
     * @return array|false
     */
    public function setWebhook(string $url, bool $dropPendingUpdates = true)
    {
        $data = [
            'url' => $url,
            'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
        ];

        return $this->request('setWebhook', $data);
    }

    /**
     * @return array|false
     */
    public function deleteWebhook(bool $dropPendingUpdates = false)
    {
        $data = [
            'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
        ];

        return $this->request('deleteWebhook', $data);
    }

    /**
     * @return array|false
     */
    public function getMe()
    {
        return $this->request('getMe', [], 'GET');
    }

    /**
     * @return array|false
     */
    public function getWebhookInfo()
    {
        return $this->request('getWebhookInfo', [], 'GET');
    }

    /**
     * @param array<string, mixed> $data
     * @return array|false
     */
    private function request(string $method, array $data = [], string $httpMethod = 'POST')
    {
        $url = $this->apiUrl . $method;

        if ($httpMethod === 'GET' && $data !== []) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $httpMethod === 'GET' && isset($data['timeout']) ? (int) $data['timeout'] + 5 : 15);

        if ($httpMethod === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $logData = date('Y-m-d H:i:s') . " - Request to: " . $method . PHP_EOL;
        $logData .= "Data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $logData .= "Response: " . ($response ?: "CURL ERROR: " . $curlError) . PHP_EOL . str_repeat('-', 40) . PHP_EOL;
        file_put_contents(__DIR__ . '/bot_debug.log', $logData, FILE_APPEND);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true) ?? false;
    }
}
