<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\DemoHealthCheck;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

$report = DemoHealthCheck::localChecks();
$statusCode = !empty($report['ok']) ? 200 : 503;
http_response_code($statusCode);

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
