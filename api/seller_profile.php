<?php
header('Content-Type: application/json');
require 'db.php';

$seller_id = intval($_GET['seller_id'] ?? 0);

if (!$seller_id) {
    echo json_encode(['error' => 'Укажите seller_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, phone, address, seller_description, is_seller, created_at FROM users WHERE id = ? AND is_seller = 1");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    echo json_encode(['error' => 'Продавец не найден']);
    exit;
}

$dishes = $pdo->prepare("SELECT d.*, c.name as category_name FROM dishes d LEFT JOIN categories c ON d.category_id = c.id WHERE d.user_id = ? AND d.is_available = 1 ORDER BY d.created_at DESC");
$dishes->execute([$seller_id]);

echo json_encode([
    'success' => true,
    'seller' => $seller,
    'dishes' => $dishes->fetchAll(PDO::FETCH_ASSOC)
]);
?>
