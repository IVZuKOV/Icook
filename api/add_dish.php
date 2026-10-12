<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$weight = trim($_POST['weight'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0) ?: null;

if (!$title) {
    echo json_encode(['error' => 'Введите название блюда']);
    exit;
}
if ($price <= 0) {
    echo json_encode(['error' => 'Укажите цену']);
    exit;
}

$image = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['error' => 'Только JPG, PNG, WEBP']);
        exit;
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'Фото максимум 5 МБ']);
        exit;
    }
    $dir = '/var/www/html/icook/uploads/dishes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = 'dish_' . time() . '_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename);
    $image = '/uploads/dishes/' . $filename;
}

$stmt = $pdo->prepare("INSERT INTO dishes (user_id, category_id, title, description, price, weight, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $category_id, $title, $description, $price, $weight, $image]);

echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Блюдо добавлено']);
?>
