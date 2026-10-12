<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Пользователь не найден']);
    exit;
}

// Миграция: гарантируем is_seller=1 для всех
if (!isset($user['is_seller']) || $user['is_seller'] != 1) {
    $pdo->prepare("UPDATE users SET is_seller = 1 WHERE id = ?")->execute([$user['id']]);
    $user['is_seller'] = 1;
}

// Миграция: если нет колонки phone - добавляем
try {
    $pdo->query("SELECT phone FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT '' AFTER email");
}

echo json_encode(['success' => true, 'user' => [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? '',
    'seller_description' => $user['seller_description'] ?? '',
    'is_seller' => intval($user['is_seller']),
]]);
?>
