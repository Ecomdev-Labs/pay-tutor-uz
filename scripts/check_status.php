<?php

declare(strict_types=1);

/**
 * Проверка demo-стенда: OSPanel, tunnel, cloudflared, Telegram webhook.
 *
 * Usage:
 *   php scripts/check_status.php
 *   php scripts/check_status.php --json
 *   php scripts/check_status.php --notify
 *   php scripts/check_status.php --log data/monitor.log
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\DemoHealthCheck;
use App\TelegramBot;

Config::load();

$json = in_array('--json', $argv, true);
$notify = in_array('--notify', $argv, true);
$quiet = in_array('--quiet', $argv, true);
$logPath = null;

foreach ($argv as $i => $arg) {
    if ($arg === '--log' && isset($argv[$i + 1])) {
        $logPath = $argv[$i + 1];
    }
}

$report = DemoHealthCheck::fullReport();
$allOk = !empty($report['ok']);

if ($json) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} elseif (!$quiet) {
    echo "========================================\n";
    echo "  PayTutor Demo — статус стенда\n";
    echo "  " . gmdate('Y-m-d H:i:s') . " UTC\n";
    echo "========================================\n\n";
    echo ($allOk ? '[OK] ' : '[FAIL] ') . ($allOk ? 'Всё работает' : 'Есть проблемы') . "\n\n";

    foreach ($report['components'] as $key => $component) {
        $ok = !empty($component['ok']);
        $label = $component['label'] ?? $key;
        echo ($ok ? '  [OK]  ' : '  [!!]  ') . $label . "\n";
        if (!empty($component['message'])) {
            echo '         ' . $component['message'] . "\n";
        }
        if (!empty($component['url'])) {
            echo '         ' . $component['url'] . "\n";
        }
        if (isset($component['latency_ms'])) {
            echo '         ' . (int) $component['latency_ms'] . " ms\n";
        }
        if ($key === 'telegram' && !empty($component['checks'])) {
            foreach ($component['checks'] as $sub) {
                echo '         ' . (!empty($sub['ok']) ? '✓' : '✗') . ' ' . ($sub['message'] ?? '') . "\n";
            }
        }
        echo "\n";
    }

    echo "Страница статуса: http://pay-tutor-uz/public/status.php\n";
    echo "Публичный health:  " . rtrim(Config::publicBaseUrl(), '/') . "/health.php\n";
}

if ($logPath !== null) {
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $line = gmdate('c') . "\t" . ($allOk ? 'OK' : 'FAIL') . "\t" . json_encode(
        array_map(static fn ($c) => !empty($c['ok']),
            $report['components']
        ),
        JSON_UNESCAPED_UNICODE
    ) . "\n";
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

if ($notify) {
    $stateFile = dirname(__DIR__) . '/data/monitor.state.json';
    $prevOk = null;
    if (is_readable($stateFile)) {
        $decoded = json_decode((string) file_get_contents($stateFile), true);
        if (is_array($decoded) && array_key_exists('last_ok', $decoded)) {
            $prevOk = (bool) $decoded['last_ok'];
        }
    }

    $state = [
        'last_ok' => $allOk,
        'updated_at' => gmdate('c'),
    ];
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if ($prevOk !== null && $prevOk !== $allOk) {
        $adminId = Config::getInt('ADMIN_CHAT_ID', 0);
        $token = Config::botToken();
        if ($adminId > 0 && $token !== '' && $token !== 'YOUR_BOT_TOKEN_HERE') {
            $lines = [$allOk ? '✅ Demo-стенд снова работает' : '❌ Demo-стенд недоступен'];
            foreach ($report['components'] as $component) {
                if (empty($component['ok'])) {
                    $lines[] = '• ' . ($component['label'] ?? '') . ': ' . ($component['message'] ?? 'ошибка');
                }
            }
            $lines[] = '';
            $lines[] = 'Проверка: ' . rtrim(Config::publicBaseUrl(), '/') . '/status.php';
            try {
                (new TelegramBot($token))->sendMessage($adminId, implode("\n", $lines));
            } catch (\Throwable $e) {
                if (!$quiet && !$json) {
                    fwrite(STDERR, "WARN: не удалось отправить Telegram: " . $e->getMessage() . "\n");
                }
            }
        }
    }
}

exit($allOk ? 0 : 1);
