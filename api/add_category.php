<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$email = $_SESSION['user_email'] ?? '';
if (!$email) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$parentId = $data['parent_id'] ?? null;
$icon = trim($data['icon'] ?? '🍴');

if (!$name) {
    echo json_encode(['error' => 'Введите название категории']);
    exit;
}

if (mb_strlen($name) > 100) {
    echo json_encode(['error' => 'Название слишком длинное']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, icon, is_custom, created_by, sort_order) VALUES (?, ?, ?, 1, ?, 99)");
$stmt->execute([$name, $parentId, $icon, $user['id']]);

echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Категория добавлена']);
?>
