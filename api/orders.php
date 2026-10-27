<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$role = (string) ($_GET['role'] ?? 'buyer');

if ($role === 'seller') {
    $sql = "SELECT o.*, d.title as dish_title, d.image as dish_image, u.name as buyer_name, u.phone as buyer_phone
            FROM orders o
            LEFT JOIN dishes d ON o.dish_id = d.id
            LEFT JOIN users u ON o.buyer_id = u.id
            WHERE o.seller_id = ?
            ORDER BY o.created_at DESC";
} else {
    $sql = "SELECT o.*, d.title as dish_title, d.image as dish_image, u.name as seller_name, u.phone as seller_phone
            FROM orders o
            LEFT JOIN dishes d ON o.dish_id = d.id
            LEFT JOIN users u ON o.seller_id = u.id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);

json_out(['success' => true, 'orders' => $stmt->fetchAll()]);
