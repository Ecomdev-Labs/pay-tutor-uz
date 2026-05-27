# Схема базы данных (предложение)

СУБД: MySQL / MariaDB (совместимо с PHP через PDO).

Таблицы (предложенные):

1. `users`
- `id` INT PK
- `telegram_id` BIGINT UNIQUE
- `username` VARCHAR
- `state` VARCHAR — состояние диалога
- `created_at`, `updated_at`

2. `payments`
- `id` INT PK
- `transaction_id` VARCHAR UNIQUE — id от FreedomPay
- `user_id` INT FK -> users(id)
- `amount` DECIMAL
- `status` ENUM('pending','paid','failed','cancelled')
- `meta` JSON — дополнительные данные
- `created_at`, `updated_at`

3. `logs`
- `id` INT PK
- `source` VARCHAR
- `level` VARCHAR
- `message` TEXT
- `context` JSON
- `created_at`

Замечания:
- Добавить индексы по `transaction_id` и `telegram_id`.
- Использовать миграции (sql-файлы) для управления схемой.