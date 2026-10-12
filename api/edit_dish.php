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

$data = json_decode(file_get_contents('php://input'), true);
$dish_id = intval($data['dish_id'] ?? 0);
$action = $data['action'] ?? 'update';

$check = $pdo->prepare("SELECT * FROM dishes WHERE id = ? AND user_id = ?");
$check->execute([$dish_id, $user['id']]);
$dish = $check->fetch(PDO::FETCH_ASSOC);

if (!$dish) {
    echo json_encode(['error' => 'Блюдо не найдено']);
    exit;
}

if ($action === 'delete') {
    if ($dish['image']) {
        @unlink('/var/www/html/icook' . $dish['image']);
    }
    $pdo->prepare("DELETE FROM dishes WHERE id = ?")->execute([$dish_id]);
    echo json_encode(['success' => true, 'message' => 'Блюдо удалено']);
    exit;
}

if ($action === 'toggle') {
    $new = $dish['is_available'] ? 0 : 1;
    $pdo->prepare("UPDATE dishes SET is_available = ? WHERE id = ?")->execute([$new, $dish_id]);
    echo json_encode(['success' => true, 'is_available' => $new]);
    exit;
}

$title = trim($data['title'] ?? $dish['title']);
$description = trim($data['description'] ?? $dish['description']);
$price = floatval($data['price'] ?? $dish['price']);
$weight = trim($data['weight'] ?? $dish['weight']);
$category_id = intval($data['category_id'] ?? $dish['category_id']) ?: null;

$pdo->prepare("UPDATE dishes SET title=?, description=?, price=?, weight=?, category_id=? WHERE id=? AND user_id=?")
    ->execute([$title, $description, $price, $weight, $category_id, $dish_id, $user['id']]);

echo json_encode(['success' => true, 'message' => 'Блюдо обновлено']);
?>
