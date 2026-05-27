<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/bootstrap.php';
use App\Database;
use function App\ensure_session;
use function App\csrf_field;
use function App\verify_csrf;

ensure_session();
$db = new Database();
$pdo = $db->getPdo();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $err = 'CSRF validation failed.';
    } elseif ($email === '' || $pass === '') {
        $err = 'Заполните все поля.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM web_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['web_user_id'] = $user['id'];
            $_SESSION['web_user_email'] = $user['email'];
            header('Location: /site/admin.php');
            exit;
        } else {
            $err = 'Неверный email или пароль.';
        }
    }
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Вход — Админка</title>
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header><h1>Вход в админку</h1><nav><a href="/site/">Главная</a></nav></header>
<main>
    <?php if ($err): ?><div style="color:#c00;"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
        <?= \App\csrf_field() ?>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Пароль</label>
        <input type="password" name="password" required>
        <button type="submit">Войти</button>
    </form>
    <p>Если у вас нет аккаунта, создайте его через sqlite или используйте команду для создания в docs.</p>
</main>
</body>
</html>