<?php

declare(strict_types=1);

namespace App;

class Database
{
    private \PDO $pdo;

    public function __construct(string $dbPath = __DIR__ . '/../data/database.sqlite')
    {
        // Поддержка смены драйвера через переменную окружения DATABASE_DSN
        $dsn = getenv('DATABASE_DSN') ?: null;

        if ($dsn) {
            $this->pdo = new \PDO($dsn);
            $isNew = false; // Не уверены, создавалась ли БД извне
        } else {
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $isNew = !file_exists($dbPath);
            $this->pdo = new \PDO("sqlite:" . $dbPath);
        }
        // Включаем выброс исключений при ошибках
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // По умолчанию возвращаем ассоциативные массивы
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // Инициализация схемы (CREATE TABLE IF NOT EXISTS безопасно вызывать всегда)
        $this->initDb();
    }

    /**
     * Создает необходимые таблицы при первом запуске
     */
    private function initDb(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            chat_id INTEGER PRIMARY KEY,
            username TEXT,
            state TEXT,
            selected_course TEXT,
            course_price INTEGER
        );

        CREATE TABLE IF NOT EXISTS orders (
            order_id TEXT PRIMARY KEY,
            chat_id INTEGER,
            amount INTEGER,
            status TEXT,
            product_type TEXT
        );

        -- Веб-пользователи для админки/авторизации
        CREATE TABLE IF NOT EXISTS web_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'editor',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Публикации/посты для рекламного сайта
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            excerpt TEXT,
            content TEXT,
            status TEXT DEFAULT 'draft', -- draft|published
            seo_title TEXT,
            seo_description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME
        );

        CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
        SQL;

        $this->pdo->exec($sql);
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
