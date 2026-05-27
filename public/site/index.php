<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Получаем опубликованные посты
$stmt = $pdo->prepare("SELECT id,title,slug,excerpt,created_at,seo_title,seo_description FROM posts WHERE status = 'published' ORDER BY created_at DESC");
$stmt->execute();
$posts = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Главная — Онлайн-курсы</title>
    <meta name="description" content="Онлайн-курсы немецкого языка — уроки, доступ в закрытый канал, поддержка.">
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header>
    <h1>Онлайн-курсы немецкого</h1>
    <nav>
        <a href="/site/">Главная</a>
        <a href="/site/login.php">Войти (админ)</a>
    </nav>
</header>
<main>
    <section class="hero">
        <h2>Коротко о нас</h2>
        <p>Профессиональные уроки немецкого языка — частные, парные и групповые занятия. Оплата через безопасный шлюз.</p>
    </section>

    <section class="post-list">
        <h3>Новости и публикации</h3>
        <?php if (count($posts) === 0): ?>
            <p>Пока нет опубликованных материалов.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="post">
                    <h4><a href="/site/post.php?slug=<?= htmlspecialchars($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h4>
                    <p class="meta"><?= htmlspecialchars($post['created_at']) ?></p>
                    <p><?= htmlspecialchars($post['excerpt'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<footer>
    <p>© <?= date('Y') ?> Онлайн-курсы — все права защищены.</p>
</footer>
</body>
</html>