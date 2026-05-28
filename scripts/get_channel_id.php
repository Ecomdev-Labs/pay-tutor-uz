<?php

declare(strict_types=1);

/**
 * Узнать CHANNEL_ID через Telegram API (demo-бот должен быть админом канала).
 *
 * Шаги:
 * 1. Опубликуйте любой пост в канале «Тест Уроки» (или перешлите пост боту @PayTutorDemoBot)
 * 2. Запустите: php scripts/get_channel_id.php
 *
 * Если webhook уже установлен — getUpdates пустой. Тогда:
 *   php scripts/delete_webhook.php
 *   (опубликуйте пост в канале)
 *   php scripts/get_channel_id.php
 *   php scripts/set_webhook.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\TelegramBot;

Config::load();

$bot = new TelegramBot(Config::botToken());

$webhookInfo = $bot->getWebhookInfo();
if (!empty($webhookInfo['result']['url'])) {
    echo "WARN: Webhook активен ({$webhookInfo['result']['url']}).\n";
    echo "getUpdates может быть пустым. Сначала: php scripts/delete_webhook.php\n\n";
}

$response = $bot->getUpdates(0, 0);

if ($response === false || empty($response['ok'])) {
    fwrite(STDERR, "ERROR: не удалось вызвать getUpdates\n");
    exit(1);
}

$updates = $response['result'] ?? [];

if ($updates === []) {
    echo "Обновлений пока нет.\n\n";
    echo "Сделайте одно из:\n";
    echo "  A) Опубликуйте пост в канале (бот — админ), затем снова запустите этот скрипт\n";
    echo "  B) Перешлите пост из канала боту @PayTutorDemoBot, затем снова запустите скрипт\n";
    echo "  C) Используйте @RawDataBot — перешлите пост из канала, ищите \"chat\":{\"id\":-100...\n\n";
    echo "Ваш ADMIN_CHAT_ID (user): напишите @userinfobot или @getidsbot — /start\n";
    exit(0);
}

$found = [];

foreach ($updates as $update) {
    $candidates = [];

    if (isset($update['channel_post']['chat'])) {
        $candidates[] = $update['channel_post']['chat'];
    }
    if (isset($update['message']['forward_from_chat'])) {
        $candidates[] = $update['message']['forward_from_chat'];
    }
    if (isset($update['message']['sender_chat'])) {
        $candidates[] = $update['message']['sender_chat'];
    }
    if (isset($update['my_chat_member']['chat'])) {
        $candidates[] = $update['my_chat_member']['chat'];
    }

    foreach ($candidates as $chat) {
        $id = (string) ($chat['id'] ?? '');
        if ($id === '' || isset($found[$id])) {
            continue;
        }
        $isChannel = (strpos($id, '-100') === 0) || (($chat['type'] ?? '') === 'channel');
        $found[$id] = [
            'id' => $id,
            'title' => $chat['title'] ?? $chat['username'] ?? '?',
            'type' => $chat['type'] ?? '?',
            'env_key' => $isChannel ? 'CHANNEL_ID' : 'CHAT_ID',
        ];
    }
}

if ($found === []) {
    echo "Обновления есть, но chat id канала не найден.\n";
    echo "Перешлите пост именно ИЗ канала (не скриншот).\n";
    exit(0);
}

echo "Найденные chat ID:\n\n";

foreach ($found as $item) {
    echo "  {$item['env_key']}={$item['id']}\n";
    echo "    Название: {$item['title']}\n";
    echo "    Тип: {$item['type']}\n\n";
}

echo "Скопируйте CHANNEL_ID в .env\n";