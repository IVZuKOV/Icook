<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/category_path.php';
require_once __DIR__ . '/lib/dish_ingredients.php';
require_once __DIR__ . '/lib/ingredients.php';

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
$discount = filter_var($data['discount_percent'] ?? $dish['discount_percent'] ?? 0, FILTER_VALIDATE_INT);
$categoryId = resolve_dish_category($pdo, $data);
try {
    $ingredients = dish_ingredients_parse($data['ingredients'] ?? null);
} catch (InvalidArgumentException $error) {
    json_out(['error' => $error->getMessage()]);
}

if ($title === '' || mb_strlen($title) > 150) {
    json_out(['error' => 'Некорректное название']);
}
if (mb_strlen($description) > 3000 || mb_strlen($weight) > 50) {
    json_out(['error' => 'Слишком длинное описание']);
}
if ($price <= 0 || $price > 10000000) {
    json_out(['error' => 'Некорректная цена']);
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
    if ($image) {
        $pdo->prepare(
            'UPDATE dishes SET title=?, description=?, price=?, weight=?, category_id=?, image=?, discount_percent=? WHERE id=? AND user_id=?'
        )->execute([$title, $description, $price, $weight, $categoryId, $image, $discount, $dishId, $userId]);
    } else {
        $pdo->prepare(
            'UPDATE dishes SET title=?, description=?, price=?, weight=?, category_id=?, discount_percent=? WHERE id=? AND user_id=?'
        )->execute([$title, $description, $price, $weight, $categoryId, $discount, $dishId, $userId]);
    }
    dish_ingredients_replace($pdo, $dishId, $ingredients);
    dish_ingredients_catalog_sync($pdo, $ingredients);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
if ($image && $dish['image']) {
    @unlink('/var/www/html/icook' . $dish['image']);
}

json_out(['success' => true, 'message' => 'Блюдо обновлено']);
