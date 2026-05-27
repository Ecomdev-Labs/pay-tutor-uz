<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/database.php';
$db = new Database();
$pdo = $db->getPdo();

if (empty($_SESSION['web_user_id'])) {
    header('Location: /site/login.php');
    exit;
}

// Обработка удаления
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: /site/admin.php');
    exit;
}

// Получаем все посты
$stmt = $pdo->prepare('SELECT * FROM posts ORDER BY created_at DESC');
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Админка — Публикации</title>
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header><h1>Админка</h1><nav><a href="/site/">Главная</a> | <a href="/site/logout.php">Выйти</a></nav></header>
<main>
    <a href="/site/save_post.php">Создать публикацию</a>
    <h3>Все публикации</h3>
    <table style="width:100%;border-collapse:collapse">
        <tr><th>id</th><th>title</th><th>status</th><th>created_at</th><th>actions</th></tr>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['id']) ?></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
                <td><?= htmlspecialchars($p['created_at']) ?></td>
                <td>
                    <a href="/site/save_post.php?id=<?= $p['id'] ?>">Edit</a> |
                    <a href="/site/admin.php?delete=<?= $p['id'] ?>" onclick="return confirm('Удалить?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>