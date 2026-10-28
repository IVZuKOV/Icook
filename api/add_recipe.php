<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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
$units = ['гр', 'кг', 'л', 'мл'];
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

// --- Шаги: JSON-массив [{text, hasPhoto}], фото — файлы step_photos[] по порядку ---
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
    $hasPhoto = !empty($st['hasPhoto']) && isset($stepFiles[$fileIdx]);
    $image = null;
    if ($hasPhoto) {
        try {
            $image = save_recipe_image($stepFiles[$fileIdx]);
        } catch (RuntimeException $e) {
            json_out(['error' => 'Фото шага: ' . $e->getMessage()]);
        }
        $fileIdx++;
    }
    // пустые шаги (нет текста и нет фото) не сохраняем
    if ($text === '' && $image === null) {
        continue;
    }
    $stepOut[] = ['text' => $text, 'image' => $image];
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

$stmt = $pdo->prepare(
    'INSERT INTO recipes (user_id, title, description, ingredients, steps, image) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $userId,
    $title,
    $description,
    json_encode($ingOut, JSON_UNESCAPED_UNICODE),
    json_encode($stepOut, JSON_UNESCAPED_UNICODE),
    $image,
]);

json_out(['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Рецепт добавлен']);
