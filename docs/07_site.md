# Рекламный сайт и админка

Что добавлено:
- Простая статическая/динамическая публичная часть в `public/site/`:
  - `index.php` — главная страница с публикациями
  - `post.php` — просмотр публикации по `slug`
  - `styles.css` — минимальные стили
- Админка и авторизация:
  - `login.php`, `logout.php`, `admin.php`, `save_post.php` — CRUD публикаций
- База данных:
  - Таблицы `web_users` и `posts` добавлены в `src/database.php`.
  - По умолчанию используется SQLite: `data/database.sqlite`.
  - Поддержка `DATABASE_DSN` для переключения на MySQL при деплое.

Как создать первого админа (локально):
1. Откройте PHP-REPL или создайте временный скрипт `scripts/create_admin.php` со следующим содержимым:

```php
<?php
require_once __DIR__ . '/../src/database.php';
$db = new Database();
$pdo = $db->getPdo();
$email = 'admin@example.com';
$pass = 'password123';
$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT OR IGNORE INTO web_users (email,password_hash,role) VALUES (?,?,?);');
$stmt->execute([$email,$hash,'admin']);
echo "Created: $email\n";
```

2. Запустите: `php scripts/create_admin.php` и используйте эти учётные для входа.

Деплой на Cloudflare Pages / Workers:
- Cloudflare Pages может хостить статические сайты — для PHP нужен Workers или внешний сервер. Для простоты используйте VPS + Cloudflare DNS + Cloudflare CDN.
- При использовании Cloudflare туннеля (Cloudflare Tunnel) можно безопасно прокинуть локальный PHP-сервер.

Миграция на MySQL (когда будет сервер):
- Установите `DATABASE_DSN` в формате `mysql:host=HOST;dbname=DBNAME;charset=utf8mb4` и задайте `DB_USER`/`DB_PASS` в окружении.
- Обновите `src/database.php` (PDO) при необходимости для передачи логина/пароля.

Безопасность и рекомендации:
- Храните токены и секреты в `.env` или в переменных окружения, не в коде.
- Ограничьте доступ к `public/site/save_post.php` и admin через HTTPS и сильные пароли.
- Добавьте CSRF-токены для форм в следующей итерации.

См. также руководство по публикации и SEO: [PUBLICATION_AND_SEO.md](PUBLICATION_AND_SEO.md)
