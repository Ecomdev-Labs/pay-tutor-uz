<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
use App\Database;

$db = new Database();
$pdo = $db->getPdo();

// Demo post content based on PUBLICATION_AND_SEO.md
$title = 'Telegram bot для онлайн-школы — кейс PayTutor';
$slug = 'telegram-bot-online-school-case-paytutor';
$excerpt = 'Кейс: Telegram-бот для автоматизации продаж и выдачи цифровых продуктов — mock-демо и рекомендации по публикации.';
$content = '';

$content .= "<h2>Problem — задача</h2>\n";
$content .= "<p>Онлайн-школам требуется автоматизация процесса продажи: подбор формата урока, расчёт стоимости, оплата и автоматическая выдача доступа или инструкции.</p>\n";
$content .= "<h2>Solution — решение</h2>\n";
$content .= "<p>Реализован Telegram-бот, который ведёт воронку, формирует заказ, интегрируется с платёжным шлюзом и через webhook помечает заказ как оплаченный и выдаёт одноразовую ссылку или инструкции.</p>\n";
$content .= "<h2>Демо</h2>\n";
$content .= "<p>Доступен mock-поток оплаты для безопасного тестирования без реальных карт. На странице демо можно симулировать оплату и посмотреть поведение webhook.</p>\n";
$content .= "<h2>Интеграции и стек</h2>\n<ul>\n<li>PHP + SQLite (PDO)</li>\n<li>Telegram Bot API</li>\n<li>Платёжный шлюз (FreedomPay) — локальный симулятор</li>\n</ul>\n";

$seoTitle = 'Telegram bot для онлайн-школ — автоматизация продаж | PayTutor';
$seoDescription = 'Кейс PayTutor: бот для приёма оплат, выдачи доступа и управления воронкой продаж. Демонстрация mock-платежа и рекомендации по публикации.';

// Insert or update
$stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $stmt = $pdo->prepare('UPDATE posts SET title=?,excerpt=?,content=?,status="published",seo_title=?,seo_description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $stmt->execute([$title,$excerpt,$content,$seoTitle,$seoDescription,$existing]);
    echo "Updated existing demo post (id={$existing})\n";
} else {
    $stmt = $pdo->prepare('INSERT INTO posts (title,slug,excerpt,content,status,seo_title,seo_description) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$title,$slug,$excerpt,$content,'published',$seoTitle,$seoDescription]);
    $id = (int)$pdo->lastInsertId();
    echo "Created demo post (id={$id})\n";
}

echo "Done. Visit /site/ to view the promotional site.\n";
