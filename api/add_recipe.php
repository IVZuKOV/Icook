<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ingredients.php';

$userId = require_login();

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));

if ($title === '') {
    json_out(['error' => 'Введите название рецепта']);
}
if (mb_strlen($title) > 150) {
    json_out(['error' => 'Название слишком длинное']);
}
if (mb_strlen($description) > 10000) {
    json_out(['error' => 'Слишком длинное описание']);
}

// --- Ингредиенты: JSON-массив [{name, qty, unit}] ---
$ingredients = json_decode((string) ($_POST['ingredients'] ?? '[]'), true);
if (!is_array($ingredients)) {
    json_out(['error' => 'Некорректный формат ингредиентов']);
}
if (count($ingredients) > 50) {
    json_out(['error' => 'Слишком много ингредиентов (макс. 50)']);
}
$units = ['гр', 'кг', 'л', 'мл', 'ст.ложки', 'ч.ложки', 'шт.', 'ед.'];
$ingOut = [];
foreach ($ingredients as $ing) {
    $name = trim((string) ($ing['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    if (mb_strlen($name) > 100) {
        json_out(['error' => 'Название ингредиента слишком длинное']);
    }
    $qty = trim((string) ($ing['qty'] ?? ''));
    if (mb_strlen($qty) > 20) {
        $qty = mb_substr($qty, 0, 20);
    }
    $unit = (string) ($ing['unit'] ?? 'гр');
    if (!in_array($unit, $units, true)) {
        $unit = 'гр';
    }
    $ingOut[] = ['name' => $name, 'qty' => $qty, 'unit' => $unit];
}

// --- Шаги: JSON-массив [{text, photoCount}], фото — файлы step_photos[] по порядку ---
$steps = json_decode((string) ($_POST['steps'] ?? '[]'), true);
if (!is_array($steps)) {
    json_out(['error' => 'Некорректный формат шагов']);
}
if (count($steps) > 50) {
    json_out(['error' => 'Слишком много шагов (макс. 50)']);
}

// нормализуем $_FILES['step_photos'] в список одиночных файлов
$stepFiles = [];
$files = $_FILES['step_photos'] ?? null;
if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
    foreach ($files['name'] as $i => $n) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $stepFiles[] = [
            'name' => (string) $files['name'][$i],
            'tmp_name' => (string) $files['tmp_name'][$i],
            'error' => (int) $files['error'][$i],
            'size' => (int) $files['size'][$i],
        ];
    }
}

$stepOut = [];
$fileIdx = 0;
foreach ($steps as $st) {
    $text = trim((string) ($st['text'] ?? ''));
    if (mb_strlen($text) > 2000) {
        json_out(['error' => 'Текст шага слишком длинный (макс. 2000 символов)']);
    }
    // несколько фото на шаг: photoCount файлов подряд из общего списка
    $count = (int) ($st['photoCount'] ?? (!empty($st['hasPhoto']) ? 1 : 0));
    if ($count < 0) {
        $count = 0;
    }
    if ($count > 20) {
        $count = 20;
    }
    $images = [];
    for ($k = 0; $k < $count; $k++) {
        if (!isset($stepFiles[$fileIdx])) {
            break;
        }
        try {
            $images[] = save_recipe_image($stepFiles[$fileIdx]);
        } catch (RuntimeException $e) {
            json_out(['error' => 'Фото шага: ' . $e->getMessage()]);
        }
        $fileIdx++;
    }
    // пустые шаги (нет текста и нет фото) не сохраняем
    if ($text === '' && !$images) {
        continue;
    }
    $stepOut[] = ['text' => $text, 'image' => $images[0] ?? null, 'images' => $images];
}

// главное фото
$image = null;
if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $image = save_recipe_image($_FILES['image']);
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()]);
    }
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO recipes (user_id, title, description, ingredients, steps, image) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId, $title, $description,
        json_encode($ingOut, JSON_UNESCAPED_UNICODE),
        json_encode($stepOut, JSON_UNESCAPED_UNICODE), $image,
    ]);
    $recipeId = (int) $pdo->lastInsertId();
    ingredient_catalog_sync($pdo, $recipeId, $ingOut);
    $pdo->commit();
    json_out(['success' => true, 'id' => $recipeId, 'message' => 'Рецепт добавлен']);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // These files were created by this request and are not referenced by a saved recipe.
    $created = $image ? [$image] : [];
    foreach ($stepOut as $step) foreach ($step['images'] ?? [] as $file) $created[] = $file;
    foreach (array_unique($created) as $file) @unlink('/var/www/html/icook' . $file);
    error_log('Add recipe failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось сохранить рецепт. Попробуйте ещё раз.'], 500);
}
