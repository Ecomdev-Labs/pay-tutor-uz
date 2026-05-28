# Cursor, Cline и экономия на AI Agent — итоговая памятка

> Дата: 28 мая 2026  
> Проект: pay-tutor-uz  
> Подписка: Cursor Pro ($20/мес), on-demand отключён

---

## 1. Как устроены расходы в Cursor Pro

### Два пула лимита (сбрасываются раз в месяц, у вас — 28-го числа)

| Пул | Что расходует |
|-----|---------------|
| **Auto + Composer** | Agent с моделью Auto или Composer |
| **API** | Конкретные модели: GPT-5.5 High, Claude, Max Mode и т.д. |

### Важно понимать

- **Included $3.48** — это не списание с карты, а использование **включённого** лимита подписки.
- **On-demand = $0** и **Disabled** → сверх лимита карту не снимут, но Agent **перестанет работать** до reset.
- **Tab (автодополнение)** на Pro безлимитный и обычно работает даже после исчерпания Agent.

### Когда лимит кончится

1. Уведомление: *"You've hit your usage limit"*
2. Agent не отвечает на новые запросы
3. Варианты: включить on-demand (доплата) или ждать сброса 28-го
4. **Auto тоже из пула Auto+Composer** — не «бесплатный навсегда»

### Реалистичные часы Agent на Pro

| Стиль работы | Часов в месяц (ориентир) |
|--------------|--------------------------|
| Текущий (длинные чаты, 1M+ токенов/шаг) | ~100–150 ч |
| Умеренный (короткие чаты, новая задача = новый чат) | ~300–500 ч |
| Agent только на правки + Ask на вопросы | 500+ ч |

**200+ часов Agent реально**, если снизить расход в 3–5 раз (см. раздел 3).

---

## 2. Анализ расхода (CSV usage-events-2026-05-28.csv)

### Главный вывод

| Источник | Токены | Доля |
|----------|--------|------|
| **IDE Agent (auto)** — локальный Agent в Cursor | ~11.5 млн | **~99.2%** |
| **Cloud Agent (gpt-5.5-high)** — cursor.com/agents | ~91 тыс. | ~0.8% |

**Cloud Agent почти не виноват.** Основной расход — **локальный Agent + Auto** в Cursor IDE.

### Самые «дорогие» шаги (один шаг Agent)

| Время (UTC) | Токены | Cache Read |
|-------------|--------|------------|
| 03:06 | 1 553 595 | 1 514 880 |
| 05:17 | 1 428 862 | 1 406 592 |
| 05:23 | 1 217 804 | 1 211 488 |
| 05:22 | 1 177 675 | 1 167 136 |
| 05:26 | 908 088 | 898 848 |

За **10 минут (05:17–05:27)** — ~4.9 млн токенов.

### Почему Agent так жрёт

1. **Длинный чат** — каждый шаг отправляет всю историю (Conversation 47.7K+)
2. **Огромный Cache Read** (800K–1.5M за шаг) — перечитывание контекста
3. **Много шагов подряд** — терминал, файлы, правки = десятки раундов
4. **Режим Agent**, не Ask — в 50–100 раз дороже одного вопроса

### Цены Auto-пула (официально)

| Тип токенов | Цена за 1M |
|-------------|------------|
| Input + Cache Write | $1.25 |
| Output | $6.00 |
| Cache Read | $0.25 |

---

## 3. Как снизить расход Cursor Agent

### Правила

1. **Ask** — вопросы и объяснения; **Agent** — только правки в коде
2. **Новая задача → новый чат**; Context > 30–40K → закрыть чат
3. **Модель Auto или Composer** — не GPT-5.5 High / Max / Premium
4. **Не запускать** несколько Agent параллельно
5. **On-demand держать выключенным**, если не хотите сюрpriзов на карте
6. Меньше `@`-контекста; `.cursorignore` для node_modules, vendor, логов

### Экономный режим на каждый день

```
1. Вопрос / разбор кода     → Ask, модель Auto
2. Нужна правка             → Agent, Auto, новый чат
3. Задача сделана           → закрыть чат
4. Context > 40K            → новый чат
5. Cloud Agent / High / Max → только для редких сложных задач
```

---

## 4. Альтернативы: локальные модели и дешёвые API

### Локально (Ollama + Continue/Cline) — ~$0

| RAM | Модель | Качество Agent |
|-----|--------|----------------|
| 8 GB | qwen2.5-coder:1.5b | Слабое |
| 16 GB | qwen2.5-coder:7b | Базовое |
| 16 GB + GPU | qwen2.5-coder:14b | Приемлемое |

**Agent на локальной 7B заметно слабее** Cursor/Cline+DeepSeek — для серьёзного agent не рекомендуется.

### Облачные альтернативы

| Сервис | Цена | Agent |
|--------|------|-------|
| **Cline + DeepSeek API** | ~$2–10/мес | ✅ Полный |
| **Cline + OpenRouter** | ~$5–10/мес | ✅ (если DeepSeek напрямую не платится) |
| **GLM Coding Plan** | $18/мес (за рубежом) | ✅ через API |
| **Windsurf** | $20/мес | ✅ Cascade (другой IDE) |
| **GitHub Copilot** | $10/мес | Ограниченный agent |

### GLM — акция $3/мес закончилась (2026)

Сейчас Lite ~$18/мес за рубежом; оплата часто через Alipay/WeChat.

---

## 5. Рекомендуемая схема: Cursor + Cline + DeepSeek

```
Cursor Pro ($20)     →  только Tab (автодополнение)
        +
Cline (бесплатно)    →  весь Agent: файлы, терминал, MCP, браузер
        +
DeepSeek API         →  ~$2–10/мес (или OpenRouter)
```

**Итого:** ~$22–30/мес вместо сжигания лимита Cursor за 3 часа Agent.

### Без Cursor Pro (если Tab не нужен)

```
VS Code (бесплатно) + Cline + DeepSeek/OpenRouter
```

---

## 6. Cline: установка и настройка в Cursor

### Установка

1. Cursor → `Ctrl+Shift+X` → Extensions
2. Поиск: **Cline** → Install
3. Перезагрузка при необходимости

### Как открыть Cline

| Способ | Действие |
|--------|----------|
| Иконка слева | Activity Bar → иконка Cline |
| Command Palette | `Ctrl+Shift+P` → `Cline: Open In New Tab` |
| Новая вкладка | `Cline: Focus on Cline View` |

**Cline — отдельная панель**, не Cursor Chat (`Ctrl+L`).

### Настройка API (DeepSeek)

1. Cline → ⚙️ Settings
2. **API Provider** → DeepSeek
3. Вставить API Key с [platform.deepseek.com](https://platform.deepseek.com)
4. Режим **Act** — для agent (правки, терминал)

### Выбор модели DeepSeek

| Режим Cline | Модель | Назначение |
|-------------|--------|------------|
| **Act** (основной) | **deepseek-v4-flash** ⭐ | Дёшево, 90% задач |
| Сложные задачи | **deepseek-v4-pro** | Рефакторинг, мультифайл, баги |
| **Plan** | **deepseek-v4-pro** | План без правок |

**Не использовать:** `deepseek-chat`, `deepseek-reasoner` — устаревают 24.07.2026.

### Схема Plan + Act

```
Plan  →  deepseek-v4-pro     «Составь план правок»
Act   →  deepseek-v4-flash   «Выполни план»
```

---

## 7. Cline vs Cursor Agent

### Cline в VS Code vs Cline в Cursor

**Один и тот же extension.** В Cursor дополнительно: Tab, `.cursorrules` подхватываются автоматически.

### Сравнение

| Функция | Cursor Agent | Cline |
|---------|--------------|-------|
| Правка файлов | ✅ | ✅ |
| Терминал | ✅ | ✅ |
| MCP | ✅ | ✅ |
| Браузер | ✅ (beta) | ✅ (Puppeteer) |
| Облачные agent'ы | ✅ | ❌ |
| Параллельные agent'ы | до 8 | ❌ |
| Стоимость | лимит Pro | свой API |
| `.cursorrules` | ✅ | ✅ (авто в Cursor) |

**Cursor + Cline ≈ 85–90% Cursor Agent** для coding-задач, не 100%.

### Локальная модель в Cursor (Ollama через BYOK)

- Работает **только Chat**, не Agent/Composer/Auto
- Для Agent лучше **Cline** в том же Cursor

---

## 8. Дебаг мобильной вёрстки через Cline Browser

### Что умеет

- Открыть localhost / OSPanel (`http://pay-tutor-uz.local/...`)
- Viewport: **Mobile 360×640**, Tablet 768×1024, Desktop
- Скриншот → agent «видит» layout
- Клики, скролл, правки CSS

### Настройка

Cline → ⚙️ **Browser Settings** → preset **Mobile (360×640)** или custom 375×667

### Пример промпта

```
Открой http://pay-tutor-uz.local/site_static/demo.html в mobile viewport 375×667.
Сделай скриншот. Проверь header и кнопки. Если проблемы — поправь styles.css.
```

### Ограничения

| Ожидание | Реальность |
|----------|------------|
| Откроет ваш Chrome | Нет — headless Puppeteer (если не MCP) |
| Как DevTools iPhone | Частично — только размер, не touch/OS |
| Console всегда полный | Иногда пустой — известный баг Cline |

### Усиление через MCP

- **Chrome DevTools for Agents** — viewport, Lighthouse, network
- **Apex Agent** — ваш открытый Chrome + DevTools

---

## 9. Оплата DeepSeek — проблемы и обход

### Ошибка «Timed out» при Top up $2

**Не «мало денег»** ($2.12 при $3 на карте хватает). Чаще:

1. Банк блокирует **международный online-платёж** (DeepSeek — иностранный мерчант)
2. **3D Secure** — не успели подтвердить SMS/push
3. Uzcard/Humo без international online
4. VPN / нестабильный интернет

### Что делать

1. Банк: включить international + online payments
2. Подтвердить 3DS сразу, не обновлять страницу
3. Без VPN, другой браузер / инкognito
4. **PayPal** на странице Top up (если есть)
5. Другая карта (Visa/Mastercard international)

### Обход: OpenRouter в Cline

Если DeepSeek напрямую не платится:

1. [openrouter.ai](https://openrouter.ai) → регистрация → API key
2. Cline → Provider → **OpenRouter**
3. Model: `deepseek/deepseek-v4-flash` или `deepseek/deepseek-v4-pro`

---

## 10. Полезные ссылки

| Ресурс | URL |
|--------|-----|
| Cursor Usage | https://cursor.com/dashboard/usage |
| Cursor Spending | https://cursor.com/dashboard/spending |
| Cursor Models & Pricing | https://cursor.com/docs/models-and-pricing |
| DeepSeek Platform | https://platform.deepseek.com |
| DeepSeek API Docs | https://api-docs.deepseek.com |
| OpenRouter | https://openrouter.ai |
| Cline in Cursor | https://cline.bot/blog/how-to-use-cline-from-cursor-or-windsurf |
| Cline GitHub | https://github.com/cline/cline |

---

## 11. Быстрые ответы (FAQ)

**Q: За 3 часа сняли $3 с карты?**  
A: Нет. Это included-лимит подписки, on-demand = $0.

**Q: Agent перестанет работать без денег?**  
A: Да, когда кончится пул Auto+Composer (on-demand выключен → ждать 28-го).

**Q: Auto спасёт после лимита?**  
A: Нет, Auto тоже из этого пула.

**Q: Cline = Cursor Agent?**  
A: ~85–90%, не идентично.

**Q: Какую модель DeepSeek в Cline?**  
A: **deepseek-v4-flash** по умолчанию, **v4-pro** для сложного.

**Q: 200 часов Agent на Pro?**  
A: При текущем стиле — впritык; с Cline + короткими чатами — да.

---

## 12. Чеклист «с понедельника»

- [ ] Cline установлен в Cursor
- [ ] API: DeepSeek или OpenRouter настроен
- [ ] Модель: `deepseek-v4-flash`, режим Act
- [ ] Cursor Agent **не использовать** для рутины
- [ ] Cursor Tab — для автодополнения
- [ ] Новый чат на каждую задачу
- [ ] On-demand в Cursor — Disabled
- [ ] Browser Cline: Mobile viewport для demo.html
- [ ] Экспорт CSV usage раз в неделю для контроля

---

*Документ собран из сессии 28.05.2026. При изменении тарифов Cursor/DeepSeek — сверяйтесь с официальными docs.*
