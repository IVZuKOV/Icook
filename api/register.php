<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$phone = trim($data['phone'] ?? '');

if (!$name || !$email || !$password) {
    echo json_encode(['error' => 'Заполните обязательные поля']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Пользователь с таким email уже зарегистрирован']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, is_seller) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$name, $email, $hash, $phone]);

    $userId = $pdo->lastInsertId();
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;

    echo json_encode(['success' => true, 'name' => $name, 'email' => $email]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка регистрации. Попробуйте позже.']);
}
?>
