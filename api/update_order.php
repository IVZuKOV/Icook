<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$email = $_SESSION['user_email'] ?? '';
if (!$email) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$data = json_decode(file_get_contents('php://input'), true);
$order_id = intval($data['order_id'] ?? 0);
$status = $data['status'] ?? '';

$allowed = ['confirmed','cooking','ready','delivered','cancelled'];
if (!in_array($status, $allowed)) {
    echo json_encode(['error' => 'Неверный статус']);
    exit;
}

$check = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND seller_id = ?");
$check->execute([$order_id, $user['id']]);
$order = $check->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['error' => 'Заказ не найден']);
    exit;
}

$pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $order_id]);

$statusNames = [
    'confirmed' => 'Подтверждён',
    'cooking' => 'Готовится',
    'ready' => 'Готов',
    'delivered' => 'Доставлен',
    'cancelled' => 'Отменён'
];

echo json_encode(['success' => true, 'message' => 'Статус: ' . $statusNames[$status]]);
?>
