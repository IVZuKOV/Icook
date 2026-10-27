<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$items = $data['items'] ?? [];
$paymentMethod = trim((string) ($data['payment_method'] ?? ''));
$deliveryAddress = trim((string) ($data['delivery_address'] ?? ''));

if (!is_array($items) || count($items) === 0) {
    json_out(['error' => 'Корзина пуста']);
}
if (count($items) > 50) {
    json_out(['error' => 'Слишком много позиций в заказе']);
}
if (mb_strlen($paymentMethod) > 30 || mb_strlen($deliveryAddress) > 500) {
    json_out(['error' => 'Некорректные данные заказа']);
}

if ($deliveryAddress === '') {
    $stmt = $pdo->prepare('SELECT address FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $deliveryAddress = trim((string) ($stmt->fetchColumn() ?: ''));
}
if ($deliveryAddress === '') {
    json_out(['error' => 'Укажите адрес доставки']);
}

try {
    $pdo->beginTransaction();

    $dishStmt = $pdo->prepare(
        'SELECT id, price, user_id AS seller_id FROM dishes WHERE id = ? AND is_available = 1'
    );
    $insert = $pdo->prepare(
        "INSERT INTO orders (buyer_id, seller_id, dish_id, quantity, total_price, status, payment_method, delivery_address)
         VALUES (?, ?, ?, ?, ?, 'new', ?, ?)"
    );

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $dishId = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 1);
        if ($dishId <= 0) {
            continue;
        }
        $qty = max(1, min($qty, 50));

        $dishStmt->execute([$dishId]);
        $dish = $dishStmt->fetch();
        if (!$dish) {
            continue;
        }

        $totalPrice = round((float) $dish['price'] * $qty, 2);
        $insert->execute([
            $userId,
            (int) $dish['seller_id'],
            $dishId,
            $qty,
            $totalPrice,
            $paymentMethod,
            $deliveryAddress,
        ]);
    }

    $pdo->commit();
    json_out(['success' => true, 'message' => 'Заказ оформлен']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Create order failed: ' . $e->getMessage());
    json_out(['error' => 'Ошибка оформления заказа'], 500);
}
