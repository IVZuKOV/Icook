<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$email       = $_SESSION['user_email'] ?? '';
$oldPassword = $data['old_password'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (!$email) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['error' => 'Новый пароль минимум 8 символов']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Пользователь не найден']);
    exit;
}

if (!password_verify($oldPassword, $user['password'])) {
    echo json_encode(['error' => 'Неверный текущий пароль']);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hash, $email]);

echo json_encode(['success' => true, 'message' => 'Пароль успешно изменён']);
?>