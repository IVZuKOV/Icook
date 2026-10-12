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

$role = $_GET['role'] ?? 'buyer';

if ($role === 'seller') {
    $sql = "SELECT o.*, d.title as dish_title, d.image as dish_image, u.name as buyer_name, u.phone as buyer_phone
            FROM orders o
            LEFT JOIN dishes d ON o.dish_id = d.id
            LEFT JOIN users u ON o.buyer_id = u.id
            WHERE o.seller_id = ?
            ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
} else {
    $sql = "SELECT o.*, d.title as dish_title, d.image as dish_image, u.name as seller_name, u.phone as seller_phone
            FROM orders o
            LEFT JOIN dishes d ON o.dish_id = d.id
            LEFT JOIN users u ON o.seller_id = u.id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
}

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success' => true, 'orders' => $orders]);
?>
