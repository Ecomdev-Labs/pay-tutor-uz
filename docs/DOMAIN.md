# Домены PayTutor

Дата фиксации: 2026-05-28

## Боевой (публичный кейс-сайт)

| Параметр | Значение |
|----------|----------|
| Хост | `pay-tutor.ecomdev.uz` |
| Origin | `https://pay-tutor.ecomdev.uz` |
| Хостинг | Cloudflare Workers (`wrangler.toml` → `public/site_static`) |
| Переменная `.env` | `SITE_URL=https://pay-tutor.ecomdev.uz` |

Канонические URL, Open Graph и ссылки в боте должны использовать этот origin. Источник правды в коде: `App\Config::PRODUCTION_SITE_ORIGIN` и `public/site_static/site.json`.

## Demo API (бот, webhook, mock-оплата)

| Параметр | Значение |
|----------|----------|
| Хост | `demo-api.pay-tutor.ecomdev.uz` |
| Origin | `https://demo-api.pay-tutor.ecomdev.uz` |
| Хостинг | OSPanel + Cloudflare Tunnel |
| Переменная `.env` | `PUBLIC_BASE_URL=https://demo-api.pay-tutor.ecomdev.uz/public` |

Технические endpoint (`index.php`, `mock_freedompay.php`, `health.php`) — **noindex**, не путать с кейс-сайтом.

## Локальная разработка (OSPanel)

| Параметр | Значение |
|----------|----------|
| Хост | `pay-tutor-uz` |
| Пример health | `http://pay-tutor-uz/public/health.php` |

При смене боевого домена обновить: `.env`, `.env.example`, `site.json`, canonical/og в HTML, `docs/05_deployment.md`, tunnel DNS при необходимости.
