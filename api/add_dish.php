<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/category_path.php';
require_once __DIR__ . '/lib/dish_ingredients.php';
require_once __DIR__ . '/lib/ingredients.php';

$userId = require_login();

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$price = (float) ($_POST['price'] ?? 0);
$weight = trim((string) ($_POST['weight'] ?? ''));
$discount = filter_var($_POST['discount_percent'] ?? 0, FILTER_VALIDATE_INT);
$categoryId = resolve_dish_category($pdo, $_POST);
try {
    $ingredients = dish_ingredients_parse($_POST['ingredients'] ?? null);
} catch (InvalidArgumentException $error) {
    json_out(['error' => $error->getMessage()]);
}

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
if ($categoryId === null) {
    json_out(['error' => 'Выберите основную категорию и подкатегорию']);
}
if ($discount === false || $discount < 0 || $discount > 90) {
    json_out(['error' => 'Скидка должна быть от 0 до 90%']);
}

$image = null;
if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $image = save_dish_image($_FILES['image']);
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()]);
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO dishes (user_id, category_id, title, description, price, weight, image, discount_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $categoryId, $title, $description, $price, $weight, $image, $discount]);
    $dishId = (int) $pdo->lastInsertId();
    dish_ingredients_replace($pdo, $dishId, $ingredients);
    dish_ingredients_catalog_sync($pdo, $ingredients);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

json_out(['success' => true, 'id' => $dishId, 'message' => 'Блюдо добавлено']);
