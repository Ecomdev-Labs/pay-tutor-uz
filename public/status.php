<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\DemoHealthCheck;

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

$report = DemoHealthCheck::fullReport();
$allOk = !empty($report['ok']);
$refreshSec = 30;

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusIcon(bool $ok): string
{
    return $ok ? 'ok' : 'fail';
}

function statusText(bool $ok): string
{
    return $ok ? 'Работает' : 'Проблема';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="refresh" content="<?= (int) $refreshSec ?>">
    <title>PayTutor Demo — статус стенда</title>
    <style>
        :root { color-scheme: light dark; font-family: "Segoe UI", sans-serif; }
        body { margin: 0; padding: 1.5rem; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.35rem; margin: 0 0 0.25rem; }
        .lead { color: #94a3b8; margin: 0 0 1.25rem; font-size: 0.95rem; }
        .banner { padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1rem; font-weight: 600; }
        .banner.ok { background: #14532d; border: 1px solid #22c55e; }
        .banner.fail { background: #450a0a; border: 1px solid #ef4444; }
        .grid { display: grid; gap: 0.75rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1rem 1.1rem; }
        .card-head { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
        .card h2 { margin: 0; font-size: 1rem; }
        .pill { font-size: 0.8rem; padding: 0.2rem 0.55rem; border-radius: 999px; font-weight: 600; }
        .pill.ok { background: #166534; color: #bbf7d0; }
        .pill.fail { background: #991b1b; color: #fecaca; }
        .meta { margin: 0.5rem 0 0; color: #94a3b8; font-size: 0.88rem; }
        .meta code { background: #0f172a; padding: 0.1rem 0.35rem; border-radius: 4px; }
        footer { margin-top: 1.25rem; color: #64748b; font-size: 0.82rem; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>PayTutor Demo — мониторинг</h1>
    <p class="lead">Обновление каждые <?= (int) $refreshSec ?> сек · <?= h($report['timestamp']) ?> UTC</p>

    <div class="banner <?= $allOk ? 'ok' : 'fail' ?>">
        <?= $allOk ? '✅ Demo-стенд работает' : '❌ Есть проблемы — см. детали ниже' ?>
    </div>

    <div class="grid">
        <?php foreach ($report['components'] as $key => $component): ?>
            <?php $ok = !empty($component['ok']); ?>
            <section class="card">
                <div class="card-head">
                    <h2><?= h($component['label'] ?? $key) ?></h2>
                    <span class="pill <?= statusIcon($ok) ?>"><?= statusText($ok) ?></span>
                </div>
                <?php if (!empty($component['message'])): ?>
                    <p class="meta"><?= h($component['message']) ?></p>
                <?php endif; ?>
                <?php if (!empty($component['url'])): ?>
                    <p class="meta">URL: <code><?= h($component['url']) ?></code></p>
                <?php endif; ?>
                <?php if (isset($component['latency_ms'])): ?>
                    <p class="meta">Ответ: <?= (int) $component['latency_ms'] ?> ms<?php if (!empty($component['http_code'])): ?>, HTTP <?= (int) $component['http_code'] ?><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($key === 'telegram' && !empty($component['checks'])): ?>
                    <?php foreach ($component['checks'] as $sub): ?>
                        <p class="meta"><?= !empty($sub['ok']) ? '✓' : '✗' ?> <?= h($sub['message'] ?? '') ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <footer>
        <p>JSON: <a href="health.php">локальный health</a> ·
        <a href="<?= h(rtrim($report['local']['public_base_url'] ?? '', '/') . '/health.php') ?>">публичный health</a></p>
        <p>Бот: <a href="https://t.me/<?= h($report['local']['bot_username'] ?? 'PayTutorDemoBot') ?>" target="_blank" rel="noopener">@<?= h($report['local']['bot_username'] ?? 'PayTutorDemoBot') ?></a></p>
    </footer>
</div>
</body>
</html>
