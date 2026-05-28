<?php

declare(strict_types=1);

namespace App;

final class Config
{
    /** Боевой домен кейс-сайта (Cloudflare Workers). См. docs/DOMAIN.md */
    public const PRODUCTION_SITE_HOST = 'pay-tutor.ecomdev.uz';

    public const PRODUCTION_SITE_ORIGIN = 'https://pay-tutor.ecomdev.uz';

    /** Demo API: бот, webhook, mock-оплата (OSPanel + tunnel) */
    public const DEMO_API_HOST = 'demo-api.pay-tutor.ecomdev.uz';

    /** @var array<string, string>|null */
    private static ?array $values = null;

    public static function load(string $envPath = null): void
    {
        if (self::$values !== null) {
            return;
        }

        self::$values = [];

        $path = $envPath ?? dirname(__DIR__) . '/.env';
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $len = strlen($value);
            if (
                $len >= 2
                && (
                    ($value[0] === '"' && $value[$len - 1] === '"')
                    || ($value[0] === "'" && $value[$len - 1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        if (isset(self::$values[$key])) {
            return self::$values[$key];
        }
        $env = getenv($key);
        return $env !== false ? $env : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    public static function botToken(): string
    {
        return self::get('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE') ?? 'YOUR_BOT_TOKEN_HERE';
    }

    public static function publicBaseUrl(): string
    {
        $configured = self::get('PUBLIC_BASE_URL');
        if ($configured !== null && $configured !== '') {
            return rtrim($configured, '/');
        }

        if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
                ? $_SERVER['HTTP_X_FORWARDED_PROTO']
                : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
            return $protocol . '://' . $host;
        }

        return 'http://localhost';
    }

    public static function siteUrl(): string
    {
        $url = self::get('SITE_URL', self::PRODUCTION_SITE_ORIGIN);

        return rtrim($url ?? self::PRODUCTION_SITE_ORIGIN, '/');
    }

    public static function siteHost(): string
    {
        $parsed = parse_url(self::siteUrl());

        return $parsed['host'] ?? self::PRODUCTION_SITE_HOST;
    }

    public static function mockPaymentUrl(): string
    {
        return self::publicBaseUrl() . '/mock_freedompay.php';
    }

    public static function webhookUrl(): string
    {
        return self::publicBaseUrl() . '/index.php';
    }
}
