<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Пользователь не найден']);
    exit;
}

// Миграция: гарантируем is_seller=1 для всех
if (!isset($user['is_seller']) || $user['is_seller'] != 1) {
    $pdo->prepare("UPDATE users SET is_seller = 1 WHERE id = ?")->execute([$user['id']]);
    $user['is_seller'] = 1;
}

// Миграция: если нет колонки phone - добавляем
try {
    $pdo->query("SELECT phone FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT '' AFTER email");
}

// Миграция: колонки гео-профиля
$migrations = [
    "country" => "ALTER TABLE users ADD COLUMN country VARCHAR(100) DEFAULT 'Россия' AFTER phone",
    "city" => "ALTER TABLE users ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER country",
    "address" => "ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL AFTER city",
    "lat" => "ALTER TABLE users ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER address",
    "lng" => "ALTER TABLE users ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat",
    "search_radius" => "ALTER TABLE users ADD COLUMN search_radius INT DEFAULT 10 AFTER lng",
    "seller_description" => "ALTER TABLE users ADD COLUMN seller_description TEXT DEFAULT NULL",
    "lang" => "ALTER TABLE users ADD COLUMN lang VARCHAR(10) DEFAULT 'ru' AFTER search_radius",
];
foreach ($migrations as $col => $sql) {
    try {
        $pdo->query("SELECT $col FROM users LIMIT 1");
    } catch (PDOException $e) {
        try { $pdo->exec($sql); } catch (PDOException $e2) {}
    }
}

// Перечитываем пользователя после возможных миграций
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'user' => [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? '',
    'country' => $user['country'] ?? 'Россия',
    'city' => $user['city'] ?? '',
    'address' => $user['address'] ?? '',
    'lat' => $user['lat'] ?? '',
    'lng' => $user['lng'] ?? '',
    'search_radius' => $user['search_radius'] ?? 10,
    'seller_description' => $user['seller_description'] ?? '',
    'lang' => $user['lang'] ?? 'ru',
    'is_seller' => intval($user['is_seller'] ?? 1),
]]);
?>
