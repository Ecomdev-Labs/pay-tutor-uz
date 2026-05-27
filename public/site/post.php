<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/database.php';
$db = new Database();
$pdo = $db->getPdo();

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    header('Location: /site/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo "<h1>404 — Не найдено</h1>";
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($post['seo_title'] ?: $post['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($post['seo_description'] ?: substr(strip_tags($post['content']),0,150)) ?>">
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header>
    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <nav><a href="/site/">Главная</a> | <a href="/site/login.php">Войти</a></nav>
</header>
<main>
    <p class="meta">Опубликовано: <?= htmlspecialchars($post['created_at']) ?></p>
    <article><?= $post['content'] ?></article>
</main>
<footer>
    <p>© <?= date('Y') ?> Онлайн-курсы</p>
</footer>
</body>
</html>