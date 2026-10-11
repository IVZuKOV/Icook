<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

$email    = $_SESSION['user_email'] ?? '';
$password = $data['password'] ?? '';

if (!$email) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

if (!$password) {
    echo json_encode(['error' => 'Введите пароль']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Пользователь не найден']);
    exit;
}

if (!password_verify($password, $user['password'])) {
    echo json_encode(['error' => 'Неверный пароль']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
$stmt->execute([$email]);

session_destroy();

echo json_encode(['success' => true, 'message' => 'Аккаунт удалён']);
?>