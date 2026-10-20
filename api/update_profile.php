<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Нет данных']);
    exit;
}

// Миграция: гарантируем наличие колонок гео-профиля
$migrations = [
    "country" => "ALTER TABLE users ADD COLUMN country VARCHAR(100) DEFAULT 'Россия' AFTER phone",
    "city" => "ALTER TABLE users ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER country",
    "address" => "ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL AFTER city",
    "lat" => "ALTER TABLE users ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER address",
    "lng" => "ALTER TABLE users ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat",
    "search_radius" => "ALTER TABLE users ADD COLUMN search_radius INT DEFAULT 10 AFTER lng",
];
foreach ($migrations as $col => $sql) {
    try {
        $pdo->query("SELECT $col FROM users LIMIT 1");
    } catch (PDOException $e) {
        try { $pdo->exec($sql); } catch (PDOException $e2) {}
    }
}

$fields = [];
$params = [];

$allowed = [
    'name' => 'name',
    'email' => 'email',
    'country' => 'country',
    'city' => 'city',
    'address' => 'address',
    'lat' => 'lat',
    'lng' => 'lng',
    'search_radius' => 'search_radius'
];

foreach ($allowed as $key => $column) {
    if (isset($data[$key])) {
        $fields[] = "$column = ?";
        $params[] = $data[$key];
    }
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Нет полей для обновления']);
    exit;
}

$params[] = $user_id;
$sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true, 'message' => 'Профиль обновлён']);
?>
