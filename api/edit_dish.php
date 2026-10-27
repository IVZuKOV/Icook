<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();

$data = $_POST;
if (empty($data)) {
    $data = body_json();
}
$dishId = (int) ($data['dish_id'] ?? 0);
$action = (string) ($data['action'] ?? 'update');

$check = $pdo->prepare('SELECT * FROM dishes WHERE id = ? AND user_id = ?');
$check->execute([$dishId, $userId]);
$dish = $check->fetch();

if (!$dish) {
    json_out(['error' => 'Блюдо не найдено'], 404);
}

if ($action === 'delete') {
    if ($dish['image']) {
        @unlink('/var/www/html/icook' . $dish['image']);
    }
    $pdo->prepare('DELETE FROM dishes WHERE id = ? AND user_id = ?')->execute([$dishId, $userId]);
    json_out(['success' => true, 'message' => 'Блюдо удалено']);
}

if ($action === 'toggle') {
    $new = $dish['is_available'] ? 0 : 1;
    $pdo->prepare('UPDATE dishes SET is_available = ? WHERE id = ? AND user_id = ?')
        ->execute([$new, $dishId, $userId]);
    json_out(['success' => true, 'is_available' => $new]);
}

$title = trim((string) ($data['title'] ?? $dish['title']));
$description = trim((string) ($data['description'] ?? $dish['description']));
$price = (float) ($data['price'] ?? $dish['price']);
$weight = trim((string) ($data['weight'] ?? $dish['weight']));
$categoryId = (int) ($data['category_id'] ?? $dish['category_id']) ?: null;

if ($title === '' || mb_strlen($title) > 150) {
    json_out(['error' => 'Некорректное название']);
}
if (mb_strlen($description) > 3000 || mb_strlen($weight) > 50) {
    json_out(['error' => 'Слишком длинное описание']);
}
if ($price <= 0 || $price > 10000000) {
    json_out(['error' => 'Некорректная цена']);
}

$image = null;
if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $image = save_dish_image($_FILES['image']);
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()]);
    }
}

if ($image) {
    if ($dish['image']) {
        @unlink('/var/www/html/icook' . $dish['image']);
    }
    $pdo->prepare(
        'UPDATE dishes SET title=?, description=?, price=?, weight=?, category_id=?, image=? WHERE id=? AND user_id=?'
    )->execute([$title, $description, $price, $weight, $categoryId, $image, $dishId, $userId]);
} else {
    $pdo->prepare(
        'UPDATE dishes SET title=?, description=?, price=?, weight=?, category_id=? WHERE id=? AND user_id=?'
    )->execute([$title, $description, $price, $weight, $categoryId, $dishId, $userId]);
}

json_out(['success' => true, 'message' => 'Блюдо обновлено']);
