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
