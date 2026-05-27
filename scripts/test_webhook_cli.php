<?php
// Тестовый запуск обработчика вебхуков в CLI
require_once __DIR__ . '/../src/bootstrap.php';

// Подставляем тестовые POST-данные
$_POST['pg_order_id'] = 'cli_test_order_' . bin2hex(random_bytes(3));
$_POST['pg_result'] = '1';
$_POST['pg_amount'] = '5000';
$_POST['pg_sig'] = 'test_sig';

// Создадим тестовый заказ, чтобы webhook смог обновить его
$db = new \App\Database();
$pdo = $db->getPdo();
$orderId = $_POST['pg_order_id'];
$chatId = 999999999; // тестовый чатId
$stmt = $pdo->prepare('INSERT INTO orders (order_id, chat_id, amount, status, product_type) VALUES (?,?,?,?,?)');
 $stmt->execute([$orderId, $chatId, (int)$_POST['pg_amount'], 'pending', 'lessons']);

// Включаем вывод результата обработчика
ob_start();
require __DIR__ . '/../public/payment_webhook.php';
$output = ob_get_clean();

echo "Handler output:\n";
echo $output;

echo "\nOrder status in DB:\n";
$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
print_r($order);
