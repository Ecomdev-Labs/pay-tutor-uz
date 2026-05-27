<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Database;

$db = new Database();
$pdo = $db->getPdo();

$email = $argv[1] ?? 'admin@example.com';
$pass = $argv[2] ?? 'password123';
$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT OR IGNORE INTO web_users (email,password_hash,role) VALUES (?,?,?);');
$stmt->execute([$email,$hash,'admin']);

if ($stmt->rowCount() > 0) {
    echo "Created admin: $email\n";
} else {
    echo "Admin already exists or insertion ignored for: $email\n";
}
