# Настройка demo-бота на ноутбуке (Cloudflare Tunnel)

Пошаговая инструкция для запуска PayTutor demo на Windows + OSPanel, доступного клиентам через интернет.

## Архитектура

| Компонент | Где | URL |
|-----------|-----|-----|
| Кейс-сайт | Cloudflare Workers | `https://pay-tutor.ecomdev.uz` |
| PHP backend (бот, mock) | OSPanel + Tunnel | `https://demo-api.pay-tutor.ecomdev.uz` |
| Demo-бот | Telegram | `https://t.me/YourDemoBot` |

## 1. Demo-бот в BotFather

1. Откройте [@BotFather](https://t.me/BotFather) → `/newbot`
2. Задайте имя и username (например `@PayTutorDemoBot`)
3. Сохраните **токен** — только для demo, не production

## 2. Telegram ID: ваш user ID и ID канала

### ADMIN_CHAT_ID (ваш личный ID)

Любой из ботов:

| Бот | Действие |
|-----|----------|
| [@userinfobot](https://t.me/userinfobot) | `/start` — покажет ваш Id |
| [@getidsbot](https://t.me/getidsbot) | `/start` |
| [@getupdatesbot](https://t.me/getupdatesbot) | `/start` |

**Ваш ID из скриншота:** `6164426603` → уже можно писать в `.env` как `ADMIN_CHAT_ID=6164426603`.

### CHANNEL_ID (канал «Тест Уроки»)

ID канала **всегда начинается с `-100`**, например `-1001234567890`.

#### Способ 1 — @RawDataBot (самый простой)

1. Откройте канал **«Тест Уроки»**
2. Выберите **любой пост** (или создайте пост «test»)
3. **Переслать** → [@RawDataBot](https://t.me/RawDataBot)
4. В ответе найдите строку `"id": -100xxxxxxxxxx` в блоке `"chat"` или `"forward_from_chat"`

#### Способ 2 — @getupdatesbot с кнопкой Channel

1. [@getupdatesbot](https://t.me/getupdatesbot) → `/start`
2. Нажмите кнопку **Channel** (внизу, не User!)
3. Перешлите **пост из канала** (не сообщение из лички)
4. Бот должен показать id канала с `-100...`

> Если бот показывает **ваш** Id (`6164426603`) — вы переслали не пост из канала или не нажали **Channel**.

#### Способ 3 — скрипт проекта (через PayTutorDemoBot)

PayTutorDemoBot уже админ в «Тест Уроки» — используем его токен:

```bash
# Если webhook ещё не ставили — пропустите delete
php scripts/delete_webhook.php

# Опубликуйте пост в канале ИЛИ перешлите пост из канала боту @PayTutorDemoBot

php scripts/get_channel_id.php

# После получения ID — снова webhook
php scripts/set_webhook.php
```

Скрипт выведет строку вида `CHANNEL_ID=-100...` — скопируйте в `.env`.

#### Способ 4 — curl (ручной)

После поста в канале (webhook должен быть снят):

```bash
curl "https://api.telegram.org/bot<BOT_TOKEN>/getUpdates"
```

Ищите `"channel_post"` → `"chat"` → `"id": -100...`.

### Тестовый закрытый канал

1. Канал **«Тест Уроки»** (или свой private channel)
2. Demo-бот **@PayTutorDemoBot** — администратор с правом **«Пригласительные ссылки»** / Invite users via link
3. `CHANNEL_ID` из способов выше → в `.env`

## 3. Конфигурация проекта

```bash
copy .env.example .env
```

Заполните `.env`:

```env
BOT_TOKEN=...
BOT_USERNAME=PayTutorDemoBot
ADMIN_CHAT_ID=ваш_telegram_id
CHANNEL_ID=-100...
PUBLIC_BASE_URL=https://demo-api.pay-tutor.ecomdev.uz/public
DEMO_MODE=1
```

> **Вариант B (текущий):** корень OSPanel = папка репозитория, PHP в `public/`.  
> `PUBLIC_BASE_URL` должен заканчиваться на `/public`.  
> **Вариант A:** корень OSPanel = `public/` → URL без `/public`.

## 4. OSPanel

1. Проект в списке: **pay-tutor-uz** → локальный URL: `http://pay-tutor-uz/`
2. **Вариант B:** корень домена = `D:\OSPanel\domains\pay-tutor-uz` (как сейчас)
3. PHP 7.4+ с расширениями PDO, curl
4. Включите автозапуск OSPanel при старте Windows

Проверка локально:

```text
http://pay-tutor-uz/public/mock_freedompay.php
http://pay-tutor-uz/public/index.php   → «No webhook data received.» (норма для GET)
```

## 5. Cloudflare Tunnel

### Установка cloudflared

```powershell
winget install --id Cloudflare.cloudflared
```

### Создание tunnel (Dashboard)

1. [Cloudflare Zero Trust](https://one.dash.cloudflare.com/) → **Networks** → **Tunnels**
2. Tunnel **pay-tutor-demo** (ID: `c4f56d88-f3d7-42cb-998b-63af46cfcda0`)
3. **Published application route**:
   - Hostname: `demo-api.pay-tutor.ecomdev.uz`
   - Service: **`http://127.0.0.1:80`** (не `https`!)
4. **HTTP Host header:** `pay-tutor-uz`

Пример конфига: [`cloudflare/tunnel.example.yml`](../cloudflare/tunnel.example.yml)

### Автозапуск tunnel (Windows)

После первого `cloudflared tunnel run`:

```bash
cloudflared service install
```

Или через **Планировщик заданий**: запуск `cloudflared tunnel run <TUNNEL_NAME>` при входе в систему.

### Проверка tunnel

```bash
curl -I https://demo-api.pay-tutor.ecomdev.uz/public/mock_freedompay.php
```

Должен вернуть **HTTP 200**.

## 6. Webhook Telegram

```bash
php scripts/set_webhook.php
```

Webhook URL: `https://demo-api.pay-tutor.ecomdev.uz/public/index.php`

## 7. Полный тест

1. Откройте demo-бота → `/start`
2. «Купить уроки» → тариф → количество → «Оплатить»
3. На mock-странице → «Оплатить (имитация успеха)»
4. В Telegram — сообщение с номером заказа
5. Проверьте «Доступ в закрытый канал» → invite-ссылка

Команды demo: `/demo` (инструкция), `/reset` (сброс состояния)

## 8. Режим polling (запасной)

Если tunnel временно недоступен:

```bash
php -r "require 'src/bootstrap.php'; (new App\TelegramBot(App\Config::botToken()))->deleteWebhook(true);"
php scripts/poll_bot.php
```

**Важно:** при polling webhook должен быть снят. Mock-оплата всё равно требует публичный URL для callback.

## 9. Питание ноутбука

- Отключите sleep при питании от сети
- Ноутбук должен быть включён и в интернете
- Это временный demo-стенд, не SLA production

## 10. Безопасность

- `.env` не коммитить (в `.gitignore`)
- Demo-токен отдельно от production
- Mock merchant secret только для тестов
- Webhook/mock помечены `noindex`

## Клиентам

Дайте ссылку: `https://t.me/BOT_USERNAME` (из `.env` → `BOT_USERNAME`)

Кейс на сайте: `https://pay-tutor.ecomdev.uz`
