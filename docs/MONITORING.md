# Инструкция: как проверять demo-стенд PayTutor

Краткий чеклист — **без Cursor и без терминала в IDE**.  
Проект: `D:\OSPanel\domains\pay-tutor-uz`

---

## Что должно работать одновременно

| № | Компонент | За что отвечает |
|---|-----------|-----------------|
| 1 | **OSPanel** | Локальный PHP, Apache, сайт `http://pay-tutor-uz/` |
| 2 | **cloudflared** | Проброс в интернет → `demo-api.pay-tutor.ecomdev.uz` |
| 3 | **Telegram webhook** | Бот получает сообщения через публичный URL |
| 4 | **Ноутбук** | Включён, не спит, есть интернет |

Если хотя бы один пункт «красный» — бот для клиентов может не отвечать.

---

## Способ 1 — самый быстрый (1 клик)

1. Откройте папку проекта в проводнике:
   ```
   D:\OSPanel\domains\pay-tutor-uz\scripts
   ```
2. Дважды щёлкните **`status.bat`**
3. Смотрите результат в окне:

### Всё хорошо ✅

```
[OK] Всё работает

  [OK]  PHP / конфиг / БД
  [OK]  OSPanel (локальный HTTP)
  [OK]  Процесс cloudflared
  [OK]  Tunnel (публичный URL)
  [OK]  Telegram webhook
         ✓ @PayTutorDemoBot
         ✓ Webhook зарегистрирован
```

### Есть проблема ❌

Строки с **`[!!]`** — что именно сломано. См. раздел [«Если что-то красное»](#если-что-то-красное) ниже.

---

## Способ 2 — страница в браузере (закладка)

Добавьте в закладки и открывайте когда угодно:

**Локально (только на ноутбуке):**
```
http://pay-tutor-uz/public/status.php
```

**Через интернет (с телефона или другого ПК):**
```
https://demo-api.pay-tutor.ecomdev.uz/public/status.php
```

### Как читать страницу

- **Зелёный баннер** «Demo-стенд работает» — всё OK
- **Красный баннер** «Есть проблемы» — смотрите карточки с меткой «Проблема»
- Страница **сама обновляется каждые 30 секунд**

---

## Способ 3 — проверка бота «как клиент»

1. Откройте в Telegram: [@PayTutorDemoBot](https://t.me/PayTutorDemoBot)
2. Отправьте `/start`
3. Должно появиться главное меню (уроки, канал и т.д.)
4. Выберите «Купить уроки» → формат → введите `1` → «Оплатить»
5. Откроется mock-страница оплаты → «Оплатить (имитация успеха)»
6. В чате с ботом — сообщение с номером заказа

Если `/start` не отвечает — сначала запустите **Способ 1** (`status.bat`).

---

## Способ 4 — фоновый монитор + Telegram

Чтобы **не проверять вручную** — запустите монитор один раз после включения ноутбука:

1. Дважды щёлкните:
   ```
   D:\OSPanel\domains\pay-tutor-uz\scripts\monitor_demo.bat
   ```
2. Окно можно **свернуть** (не закрывать)
3. При сбое или восстановлении придёт сообщение в Telegram на ваш `ADMIN_CHAT_ID`

### Лог проверок

Файл:
```
D:\OSPanel\domains\pay-tutor-uz\data\monitor.log
```

Формат строки: дата → `OK` или `FAIL` → какие компоненты упали.

### Остановить монитор

В окне `monitor_demo.bat` нажмите **Ctrl+C**.

### Свой интервал (например, каждые 2 минуты)

```bat
monitor_demo.bat 120
```

---

## Ежедневный чеклист (2 минуты)

Отмечайте галочкой при проверке:

- [ ] OSPanel запущен (иконка в трее зелёная)
- [ ] `status.bat` → `[OK] Всё работает`
- [ ] Или `status.php` в браузере → зелёный баннер
- [ ] Бот отвечает на `/start` в Telegram
- [ ] (Опционально) `monitor_demo.bat` запущен и свёрнут

---

## Если что-то красное

### `[!!] OSPanel (локальный HTTP)`

**Причина:** Apache не работает или домен `pay-tutor-uz` не настроен.

**Что делать:**
1. Запустите OSPanel
2. Убедитесь, что сайт открывается: `http://pay-tutor-uz/public/health.php`  
   Должен показать JSON с `"ok": true`
3. Снова запустите `status.bat`

---

### `[!!] Процесс cloudflared`

**Причина:** Tunnel не запущен.

**Что делать:**
1. Проверьте, установлен ли сервис:
   ```powershell
   sc query cloudflared
   ```
2. Если сервис есть — запустите:
   ```powershell
   sc start cloudflared
   ```
3. Или вручную (в отдельном окне):
   ```powershell
   cloudflared tunnel run pay-tutor-demo
   ```
4. Снова `status.bat`

Подробнее про tunnel: [`DEMO_LAPTOP_SETUP.md`](DEMO_LAPTOP_SETUP.md) → раздел 5.

---

### `[!!] Tunnel (публичный URL)`

**Причина:** cloudflared не доходит до OSPanel, или ноутбук без интернета.

**Что делать:**
1. Сначала исправьте OSPanel (см. выше)
2. Потом cloudflared (см. выше)
3. Проверьте в браузере:
   ```
   https://demo-api.pay-tutor.ecomdev.uz/public/health.php
   ```
   Ожидается: HTTP 200, в JSON `"ok": true`

---

### `[!!] Telegram webhook`

**Причина:** Webhook не зарегистрирован или URL не совпадает с tunnel.

**Что делать:**
1. Убедитесь, что tunnel уже зелёный
2. Запустите:
   ```
   D:\OSPanel\domains\pay-tutor-uz\scripts\start_demo.bat
   ```
   (перерегистрирует webhook)
3. Или вручную через PHP:
   ```bash
   php scripts/set_webhook.php
   ```
4. Снова `status.bat`

---

### `[!!] PHP / конфиг / БД`

**Причина:** Нет `.env`, неверный `BOT_TOKEN`, нет прав на папку `data`.

**Что делать:**
1. Проверьте файл `.env` в корне проекта
2. Должны быть заполнены: `BOT_TOKEN`, `ADMIN_CHAT_ID`, `CHANNEL_ID`, `PUBLIC_BASE_URL`
3. Пример: скопируйте `.env.example` → `.env` и заполните

---

## Полезные ссылки

| Что | URL |
|-----|-----|
| Страница статуса (локально) | http://pay-tutor-uz/public/status.php |
| Страница статуса (интернет) | https://demo-api.pay-tutor.ecomdev.uz/public/status.php |
| Health JSON (локально) | http://pay-tutor-uz/public/health.php |
| Health JSON (интернет) | https://demo-api.pay-tutor.ecomdev.uz/public/health.php |
| Demo-бот | https://t.me/PayTutorDemoBot |
| Кейс-сайт | https://pay-tutor.ecomdev.uz |
| Инструкция бота | https://pay-tutor.ecomdev.uz/bot.html |

---

## Файлы и скрипты

| Файл | Назначение |
|------|------------|
| `scripts\status.bat` | Разовая проверка (двойной клик) |
| `scripts\monitor_demo.bat` | Фоновый монитор + Telegram |
| `scripts\check_status.php` | То же из командной строки |
| `scripts\start_demo.bat` | Перерегистрация webhook |
| `public\status.php` | Страница статуса в браузере |
| `public\health.php` | JSON для автопроверок |
| `data\monitor.log` | История проверок |

---

## Автозапуск монитора при входе в Windows

1. **Win+R** → введите `taskschd.msc` → Enter
2. **Создать задачу…**
3. Имя: `PayTutor Demo Monitor`
4. Вкладка **Триггеры** → **Создать** → «При входе в систему»
5. Вкладка **Действия** → **Создать**:
   - Программа: `D:\OSPanel\domains\pay-tutor-uz\scripts\monitor_demo.bat`
   - Рабочая папка: `D:\OSPanel\domains\pay-tutor-uz`
   - Аргументы (необязательно): `60` — интервал в секундах
6. **OK** → введите пароль Windows при запросе

После перезагрузки ноутбука монитор стартует сам.

---

## Внешний мониторинг (UptimeRobot)

Если нужно узнать о падении tunnel, когда вы не у компьютера:

1. Зарегистрируйтесь на [uptimerobot.com](https://uptimerobot.com)
2. Создайте монитор типа **HTTP(s)**
3. URL:
   ```
   https://demo-api.pay-tutor.ecomdev.uz/public/health.php
   ```
4. Интервал: 5 минут
5. Уведомления: email или Telegram

> UptimeRobot проверяет только **публичный URL**. Если OSPanel упал, а tunnel ещё «живой» — внешний монитор может показать OK, но бот не будет работать. Поэтому локальный `status.bat` надёжнее.

---

## Краткая шпаргалка

```
Проверить сейчас     →  scripts\status.bat
Смотреть в браузере  →  http://pay-tutor-uz/public/status.php
Не следить вручную   →  scripts\monitor_demo.bat (свернуть окно)
Бот не отвечает      →  start_demo.bat, затем status.bat
Tunnel не работает   →  sc start cloudflared
```

Полная настройка стенда: [`DEMO_LAPTOP_SETUP.md`](DEMO_LAPTOP_SETUP.md)
