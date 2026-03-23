<?php

declare(strict_types=1);

class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath = __DIR__ . '/database.sqlite')
    {
        $isNew = !file_exists($dbPath);
        
        $this->pdo = new PDO("sqlite:" . $dbPath);
        // Включаем выброс исключений при ошибках
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // По умолчанию возвращаем ассоциативные массивы
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($isNew) {
            $this->initDb();
        }
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
            status TEXT
        );
        SQL;

        $this->pdo->exec($sql);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
