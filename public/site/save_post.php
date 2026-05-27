<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../src/bootstrap.php';
use App\Database;
use function App\ensure_session;
use function App\verify_csrf;

ensure_session();
$db = new Database();
$pdo = $db->getPdo();

if (empty($_SESSION['web_user_id'])) {
    header('Location: /site/login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'CSRF validation failed.';
    } else {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_description'] ?? '');

    if ($title === '' || $slug === '') {
        $error = 'Заголовок и slug обязательны.';
    } else {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE posts SET title=?,slug=?,excerpt=?,content=?,status=?,seo_title=?,seo_description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->execute([$title,$slug,$excerpt,$content,$status,$seo_title,$seo_description,$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO posts (title,slug,excerpt,content,status,seo_title,seo_description) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$title,$slug,$excerpt,$content,$status,$seo_title,$seo_description]);
            $id = (int)$pdo->lastInsertId();
        }
        header('Location: /site/admin.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $id ? 'Редактировать' : 'Создать' ?> публикацию</title>
    <link rel="stylesheet" href="/site/styles.css">
</head>
<body>
<header><h1><?= $id ? 'Редактировать' : 'Создать' ?> публикацию</h1><nav><a href="/site/admin.php">Назад</a></nav></header>
<main>
    <?php if (!empty($error)): ?><div style="color:#c00"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <?= \App\csrf_field() ?>
        <label>Заголовок</label>
        <input name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
        <label>Slug (латиница, без пробелов)</label>
        <input name="slug" value="<?= htmlspecialchars($post['slug'] ?? '') ?>" required>
        <label>Краткое описание (excerpt)</label>
        <input name="excerpt" value="<?= htmlspecialchars($post['excerpt'] ?? '') ?>">
        <label>Контент (HTML разрешён)</label>
        <textarea name="content" rows="10"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        <label>SEO title</label>
        <input name="seo_title" value="<?= htmlspecialchars($post['seo_title'] ?? '') ?>">
        <label>SEO description</label>
        <input name="seo_description" value="<?= htmlspecialchars($post['seo_description'] ?? '') ?>">
        <label>Статус</label>
        <select name="status"><option value="draft"<?= (isset($post['status']) && $post['status'] === 'draft') ? ' selected' : '' ?>>Draft</option><option value="published"<?= (isset($post['status']) && $post['status'] === 'published') ? ' selected' : '' ?>>Published</option></select>
        <button type="submit">Сохранить</button>
    </form>
</main>
</body>
</html>