<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['error' => 'Заполните все поля']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    // Миграция: если is_seller не установлен или 0 - ставим 1
    if (!isset($user['is_seller']) || $user['is_seller'] != 1) {
        $pdo->prepare("UPDATE users SET is_seller = 1 WHERE id = ?")->execute([$user['id']]);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    echo json_encode(['success' => true, 'name' => $user['name']]);
} else {
    echo json_encode(['error' => 'Неверный email или пароль']);
}
?>
