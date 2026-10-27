<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$orderId = (int) ($data['order_id'] ?? 0);
$status = (string) ($data['status'] ?? '');

$allowed = ['confirmed', 'cooking', 'ready', 'delivered', 'cancelled'];
if (!in_array($status, $allowed, true)) {
    json_out(['error' => 'Неверный статус']);
}
if ($orderId <= 0) {
    json_out(['error' => 'Заказ не найден'], 404);
}

$check = $pdo->prepare('SELECT id FROM orders WHERE id = ? AND seller_id = ?');
$check->execute([$orderId, $userId]);

if (!$check->fetch()) {
    json_out(['error' => 'Заказ не найден'], 404);
}

$pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND seller_id = ?')
    ->execute([$status, $orderId, $userId]);

$statusNames = [
    'confirmed' => 'Подтверждён',
    'cooking' => 'Готовится',
    'ready' => 'Готов',
    'delivered' => 'Доставлен',
    'cancelled' => 'Отменён',
];

json_out(['success' => true, 'message' => 'Статус: ' . $statusNames[$status]]);
