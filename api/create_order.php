<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];
$payment_method = trim($data['payment_method'] ?? '');
$delivery_address = trim($data['delivery_address'] ?? '');

if (empty($items)) {
    echo json_encode(['error' => 'Корзина пуста']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($delivery_address === '') {
    $st = $pdo->prepare("SELECT address FROM users WHERE id = ?");
    $st->execute([$user_id]);
    $delivery_address = (string)($st->fetchColumn() ?: '');
}
if ($delivery_address === '') {
    echo json_encode(['error' => 'Укажите адрес доставки']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    foreach ($items as $item) {
        $dish_id = intval($item['id'] ?? 0);
        $qty = intval($item['qty'] ?? 1);
        
        if ($dish_id <= 0 || $qty <= 0) continue;
        
        // Получаем блюдо и продавца
        $stmt = $pdo->prepare("SELECT d.id, d.title, d.price, d.user_id as seller_id FROM dishes d WHERE d.id = ? AND d.is_available = 1");
        $stmt->execute([$dish_id]);
        $dish = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dish) continue;
        
        $total_price = floatval($dish['price']) * $qty;
        
        $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, seller_id, dish_id, quantity, total_price, status, payment_method, delivery_address) VALUES (?, ?, ?, ?, ?, 'new', ?, ?)");
        $stmt->execute([$user_id, $dish['seller_id'], $dish_id, $qty, $total_price, $payment_method, $delivery_address]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Заказ оформлен']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка оформления заказа']);
}
?>
