<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$price = (float) ($_POST['price'] ?? 0);
$weight = trim((string) ($_POST['weight'] ?? ''));
$categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;

if ($title === '') {
    json_out(['error' => 'Введите название блюда']);
}
if (mb_strlen($title) > 150) {
    json_out(['error' => 'Название слишком длинное']);
}
if (mb_strlen($description) > 3000 || mb_strlen($weight) > 50) {
    json_out(['error' => 'Слишком длинное описание']);
}
if ($price <= 0 || $price > 10000000) {
    json_out(['error' => 'Укажите цену']);
}

$image = null;
if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $image = save_dish_image($_FILES['image']);
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()]);
    }
}

$stmt = $pdo->prepare(
    'INSERT INTO dishes (user_id, category_id, title, description, price, weight, image) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$userId, $categoryId, $title, $description, $price, $weight, $image]);

json_out(['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Блюдо добавлено']);
