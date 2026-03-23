# Telegram Bot MVP: Course Sales & FreedomPay Integration

## 1. Project Overview
This is an MVP of a Telegram bot written in PHP. The bot sells German language courses, calculates the total price based on the number of lessons, and generates a payment link via FreedomPay.

## 2. Tech Stack & Constraints
- **Language:** PHP 8.2+
- **Database:** SQLite (for zero-configuration setup). File should be `database.sqlite`.
- **Framework:** Pure PHP (or Laravel if specifically requested by the user). No complex Telegram libraries, use native `curl` or `file_get_contents` for Telegram API calls to keep it simple.
- **Architecture:** 
  - `index.php` (Main entry point for Telegram Webhook).
  - `Database.php` (PDO SQLite connection).
  - `FreedomPay.php` (Service for generating payment links).
- **Security (Crucial):** Use PDO prepared statements to prevent SQL injection. Validate Telegram Webhook requests.

## 3. Database Schema (SQLite)
Table: `users`
- `chat_id` (INTEGER PRIMARY KEY)
- `username` (TEXT)
- `state` (TEXT) - to track conversation flow (e.g., 'waiting_for_lessons_count')
- `selected_course` (TEXT)
- `course_price` (INTEGER)

Table: `orders`
- `order_id` (TEXT PRIMARY KEY) - UUID or unique string
- `chat_id` (INTEGER)
- `amount` (INTEGER)
- `status` (TEXT) - 'pending', 'paid'

## 4. Telegram Bot Flow (State Machine)
1. **Command `/start`**: 
   - Insert/Update user in `users` table. Set `state` to `null`.
   - Send greeting text.
   - Send Inline Keyboard with 3 buttons:
     - "Индивидуальные (200,000 UZS)" -> callback: `course_individual`
     - "Парные (150,000 UZS)" -> callback: `course_pair`
     - "Доступ в канал (300,000 UZS)" -> callback: `course_channel`

2. **Handling Callbacks (Course Selection)**:
   - If `course_individual` or `course_pair`:
     - Update user `state` to `waiting_for_lessons_count`.
     - Update user `selected_course` and `course_price`.
     - Send message: "Введите количество уроков (цифрой, например: 5):"
   - If `course_channel`:
     - Calculate total = 300,000. Create order.
     - Send Invoice message with "Pay 300,000 UZS" button (Link to FreedomPay).

3. **Handling Text Input (Lessons Count)**:
   - Check if user `state` == `waiting_for_lessons_count`.
   - Validate input: Must be numeric and > 0.
   - Calculate Total = input * `course_price`.
   - Update user `state` to `null`.
   - Create record in `orders` table with status `pending`.
   - Generate FreedomPay link.
   - Send message: "Сумма к оплате: {Total} UZS" with Inline URL Button: "Оплатить 💳".

## 5. FreedomPay Integration (Mock/MVP Level)
Endpoint: `https://api.freedompay.uz/init_payment.php`
Required parameters to generate link:
- `pg_merchant_id` = 'TEST_MERCHANT'
- `pg_amount` = {calculated_amount}
- `pg_currency` = 'UZS'
- `pg_order_id` = {generated_order_id}
- `pg_description` = 'German Lessons'
- `pg_salt` = random string
- `pg_sig` = md5 hash (mock it for now: `md5('test')` if testing, or write the real signature generation logic).

*Requirement for AI:* Write the `generatePaymentLink($orderId, $amount)` function.

## 6. Payment Callback (Webhook)
Create a file `payment_webhook.php`.
- Accepts POST request from FreedomPay.
- Checks `pg_result`. If 1 (success):
  - Update `orders` status to 'paid'.
  - Send Telegram message to `chat_id`: "✅ Оплата прошла успешно! Спасибо."
  - Output XML `<response><pg_status>ok</pg_status></response>`