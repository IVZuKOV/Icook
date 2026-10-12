<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$email = $_SESSION['user_email'] ?? '';
if (!$email) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');
$description = trim($data['description'] ?? '');

if (!$phone) {
    echo json_encode(['error' => 'Укажите телефон']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET is_seller = 1, phone = ?, address = ?, seller_description = ? WHERE email = ?");
$stmt->execute([$phone, $address, $description, $email]);

echo json_encode(['success' => true, 'message' => 'Вы теперь продавец!']);
?>
