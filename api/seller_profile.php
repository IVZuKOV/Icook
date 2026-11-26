<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/default_dish_images.php';

$sellerId = (int) ($_GET['seller_id'] ?? 0);

if ($sellerId <= 0) {
    json_out(['error' => 'Укажите seller_id']);
}

$stmt = $pdo->prepare(
    'SELECT id, name, phone, address, seller_description, is_seller, created_at,
            CASE WHEN avatar_public = 1 THEN avatar ELSE \'\' END AS avatar,
            CASE WHEN avatar_public = 1 THEN avatar_version ELSE \'\' END AS avatar_version,
            CASE WHEN avatar_public = 1 THEN avatar_crop ELSE NULL END AS avatar_crop
     FROM users WHERE id = ? AND is_seller = 1 LIMIT 1'
);
$stmt->execute([$sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
    json_out(['error' => 'Продавец не найден'], 404);
}

$stmt = $pdo->prepare(
    'SELECT d.*, c.name as category_name, inventory.remaining_count AS remaining_count FROM dishes d
     LEFT JOIN categories c ON d.category_id = c.id
     LEFT JOIN dish_inventory inventory ON inventory.dish_id = d.id
     WHERE d.user_id = ? AND d.is_available = 1
     ORDER BY d.created_at DESC'
);
$stmt->execute([$sellerId]);
$dishes = apply_default_dish_images($stmt->fetchAll());

json_out(['success' => true, 'seller' => $seller, 'dishes' => $dishes]);
