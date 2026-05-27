<?php

declare(strict_types=1);

namespace App;

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    $t = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t) . '">';
}

function verify_csrf(?string $token): bool
{
    ensure_session();
    if (empty($token) || empty($_SESSION['_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf'], $token);
}
