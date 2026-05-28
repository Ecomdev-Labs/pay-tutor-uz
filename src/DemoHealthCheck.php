<?php

declare(strict_types=1);

namespace App;

final class DemoHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public static function localChecks(): array
    {
        Config::load();

        $checks = [];
        $ok = true;

        $token = Config::botToken();
        $tokenOk = $token !== '' && $token !== 'YOUR_BOT_TOKEN_HERE';
        $checks['config'] = [
            'ok' => $tokenOk,
            'message' => $tokenOk ? 'BOT_TOKEN задан' : 'BOT_TOKEN не настроен в .env',
        ];
        $ok = $ok && $tokenOk;

        $dataDir = dirname(__DIR__) . '/data';
        $dataWritable = is_dir($dataDir) && is_writable($dataDir);
        $checks['data_dir'] = [
            'ok' => $dataWritable,
            'message' => $dataWritable ? 'Папка data доступна для записи' : 'Папка data недоступна для записи',
        ];
        $ok = $ok && $dataWritable;

        try {
            new Database();
            $checks['database'] = ['ok' => true, 'message' => 'SQLite подключена'];
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'message' => 'SQLite: ' . $e->getMessage()];
            $ok = false;
        }

        $checks['demo_mode'] = [
            'ok' => Config::getBool('DEMO_MODE', false),
            'message' => Config::getBool('DEMO_MODE', false) ? 'DEMO_MODE=1' : 'DEMO_MODE выключен',
        ];

        return [
            'ok' => $ok,
            'checks' => $checks,
            'public_base_url' => Config::publicBaseUrl(),
            'webhook_url' => Config::webhookUrl(),
            'bot_username' => Config::get('BOT_USERNAME'),
            'timestamp' => gmdate('c'),
        ];
    }

    /**
     * @return array{ok: bool, http_code: int, latency_ms: int|null, message: string, body: mixed|null}
     */
    public static function httpProbe(string $url, int $timeoutSec = 8): array
    {
        $started = microtime(true);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeoutSec,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSec),
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'PayTutorDemoHealth/1.0',
            ]);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            if ($body === false) {
                return [
                    'ok' => false,
                    'http_code' => 0,
                    'latency_ms' => $latencyMs,
                    'message' => $error !== '' ? $error : 'HTTP запрос не выполнен',
                    'body' => null,
                ];
            }

            $decoded = json_decode($body, true);
            $healthOk = $httpCode === 200 && is_array($decoded) && !empty($decoded['ok']);

            return [
                'ok' => $healthOk,
                'http_code' => $httpCode,
                'latency_ms' => $latencyMs,
                'message' => $healthOk ? 'OK' : ('HTTP ' . $httpCode),
                'body' => $decoded,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeoutSec,
                'header' => "Accept: application/json\r\nUser-Agent: PayTutorDemoHealth/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if ($body === false) {
            return [
                'ok' => false,
                'http_code' => 0,
                'latency_ms' => $latencyMs,
                'message' => 'HTTP запрос не выполнен',
                'body' => null,
            ];
        }

        $decoded = json_decode($body, true);
        $healthOk = is_array($decoded) && !empty($decoded['ok']);

        return [
            'ok' => $healthOk,
            'http_code' => 200,
            'latency_ms' => $latencyMs,
            'message' => $healthOk ? 'OK' : 'Некорректный ответ health',
            'body' => $decoded,
        ];
    }

    public static function isCloudflaredRunning(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $output = [];
            exec('pgrep -x cloudflared 2>/dev/null', $output, $code);
            return $code === 0 && $output !== [];
        }

        $output = [];
        exec('tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL', $output);
        foreach ($output as $line) {
            if (stripos($line, 'cloudflared.exe') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok: bool, checks: array<string, mixed>}
     */
    public static function webhookChecks(): array
    {
        Config::load();
        $token = Config::botToken();
        if ($token === '' || $token === 'YOUR_BOT_TOKEN_HERE') {
            return [
                'ok' => false,
                'checks' => ['api' => ['ok' => false, 'message' => 'BOT_TOKEN не задан']],
            ];
        }

        $bot = new TelegramBot($token);
        $expectedUrl = Config::webhookUrl();
        $checks = [];
        $ok = true;

        $me = $bot->getMe();
        $meOk = is_array($me) && !empty($me['ok']);
        $checks['bot_api'] = [
            'ok' => $meOk,
            'message' => $meOk
                ? ('@' . ($me['result']['username'] ?? 'bot'))
                : ('getMe: ' . json_encode($me, JSON_UNESCAPED_UNICODE)),
        ];
        $ok = $ok && $meOk;

        $info = $bot->getWebhookInfo();
        $infoOk = is_array($info) && !empty($info['ok']);
        $result = $infoOk ? ($info['result'] ?? []) : [];
        $actualUrl = (string) ($result['url'] ?? '');
        $urlMatch = $actualUrl === $expectedUrl;
        $lastError = (string) ($result['last_error_message'] ?? '');
        $pending = (int) ($result['pending_update_count'] ?? 0);

        $webhookOk = $infoOk && $urlMatch !== false && $actualUrl !== '' && $lastError === '';
        $checks['webhook'] = [
            'ok' => $webhookOk,
            'message' => $webhookOk
                ? 'Webhook зарегистрирован'
                : trim(
                    ($actualUrl === '' ? 'Webhook не установлен. ' : '')
                    . ($urlMatch ? '' : 'URL не совпадает с PUBLIC_BASE_URL. ')
                    . ($lastError !== '' ? 'Ошибка: ' . $lastError : '')
                ),
            'url' => $actualUrl,
            'expected_url' => $expectedUrl,
            'pending_updates' => $pending,
            'last_error_date' => $result['last_error_date'] ?? null,
        ];
        $ok = $ok && $webhookOk;

        return ['ok' => $ok, 'checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fullReport(): array
    {
        Config::load();

        $localUrl = Config::get('LOCAL_HEALTH_URL', 'http://pay-tutor-uz/public/health.php') ?? 'http://pay-tutor-uz/public/health.php';
        $publicUrl = rtrim(Config::publicBaseUrl(), '/') . '/health.php';

        $local = self::localChecks();
        $localHttp = self::httpProbe($localUrl);
        $publicHttp = self::httpProbe($publicUrl);
        $cloudflared = self::isCloudflaredRunning();
        $webhook = self::webhookChecks();

        $components = [
            'local_php' => [
                'ok' => !empty($local['ok']),
                'label' => 'PHP / конфиг / БД',
            ],
            'local_http' => [
                'ok' => $localHttp['ok'],
                'label' => 'OSPanel (локальный HTTP)',
                'url' => $localUrl,
                'latency_ms' => $localHttp['latency_ms'],
                'message' => $localHttp['message'],
            ],
            'cloudflared' => [
                'ok' => $cloudflared,
                'label' => 'Процесс cloudflared',
                'message' => $cloudflared ? 'cloudflared.exe запущен' : 'cloudflared.exe не найден',
            ],
            'tunnel_http' => [
                'ok' => $publicHttp['ok'],
                'label' => 'Tunnel (публичный URL)',
                'url' => $publicUrl,
                'latency_ms' => $publicHttp['latency_ms'],
                'message' => $publicHttp['message'],
                'http_code' => $publicHttp['http_code'],
            ],
            'telegram' => [
                'ok' => $webhook['ok'],
                'label' => 'Telegram webhook',
                'checks' => $webhook['checks'],
            ],
        ];

        $allOk = true;
        foreach ($components as $component) {
            if (empty($component['ok'])) {
                $allOk = false;
                break;
            }
        }

        return [
            'ok' => $allOk,
            'timestamp' => gmdate('c'),
            'components' => $components,
            'local' => $local,
            'local_http' => $localHttp,
            'public_http' => $publicHttp,
            'webhook' => $webhook,
        ];
    }
}
