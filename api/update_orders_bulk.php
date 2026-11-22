<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();
$status = (string) ($data['status'] ?? '');
$rawIds = $data['order_ids'] ?? [];
$allowed = ['confirmed', 'cooking', 'ready', 'in_transit', 'delivered', 'cancelled'];

if (!in_array($status, $allowed, true)) {
    json_out(['error' => 'Выберите корректный статус']);
}
if (!is_array($rawIds) || count($rawIds) === 0 || count($rawIds) > 1000) {
    json_out(['error' => 'Выберите от 1 до 1000 заказов']);
}

$orderIds = array_values(array_unique(array_filter(array_map(
    static fn ($id): int => filter_var($id, FILTER_VALIDATE_INT) !== false ? (int) $id : 0,
    $rawIds
), static fn (int $id): bool => $id > 0)));

if (count($orderIds) !== count($rawIds)) {
    json_out(['error' => 'Список заказов содержит некорректные или повторяющиеся номера']);
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$pdo->beginTransaction();

try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND id IN ($placeholders)");
    $check->execute(array_merge([$userId], $orderIds));
    if ((int) $check->fetchColumn() !== count($orderIds)) {
        $pdo->rollBack();
        json_out(['error' => 'Некоторые заказы не найдены или недоступны'], 404);
    }

    $update = $pdo->prepare("UPDATE orders SET status = ? WHERE seller_id = ? AND id IN ($placeholders)");
    $update->execute(array_merge([$status, $userId], $orderIds));
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$statusNames = [
    'confirmed' => 'Подтверждён',
    'cooking' => 'Готовится',
    'ready' => 'Готов',
    'in_transit' => 'В пути',
    'delivered' => 'Доставлен',
    'cancelled' => 'Отменён',
];

json_out([
    'success' => true,
    'updated' => count($orderIds),
    'message' => 'Обновлено заказов: ' . count($orderIds) . '. Статус: ' . $statusNames[$status],
]);
