<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();

$data = $_POST;
if (empty($data)) {
    $data = body_json();
}
$recipeId = (int) ($data['recipe_id'] ?? 0);
$action = (string) ($data['action'] ?? 'update');

$check = $pdo->prepare('SELECT * FROM recipes WHERE id = ? AND user_id = ?');
$check->execute([$recipeId, $userId]);
$recipe = $check->fetch();

if (!$recipe) {
    json_out(['error' => 'Рецепт не найден'], 404);
}

function remove_recipe_file(?string $path): void
{
    if ($path) {
        @unlink('/var/www/html/icook' . $path);
    }
}

if ($action === 'delete') {
    remove_recipe_file($recipe['image']);
    foreach (json_decode($recipe['steps'] ?? '[]', true) ?: [] as $st) {
        remove_recipe_file($st['image'] ?? null);
    }
    $pdo->prepare('DELETE FROM recipes WHERE id = ? AND user_id = ?')->execute([$recipeId, $userId]);
    json_out(['success' => true, 'message' => 'Рецепт удалён']);
}

if ($action === 'toggle') {
    $new = $recipe['is_available'] ? 0 : 1;
    $pdo->prepare('UPDATE recipes SET is_available = ? WHERE id = ? AND user_id = ?')
        ->execute([$new, $recipeId, $userId]);
    json_out(['success' => true, 'is_available' => $new]);
}

// --- update ---
$title = trim((string) ($data['title'] ?? $recipe['title']));
$description = trim((string) ($data['description'] ?? $recipe['description']));

if ($title === '' || mb_strlen($title) > 150) {
    json_out(['error' => 'Некорректное название']);
}
if (mb_strlen($description) > 10000) {
    json_out(['error' => 'Слишком длинное описание']);
}

// ингредиенты
$ingOut = null;
if (array_key_exists('ingredients', $data)) {
    $ingredients = json_decode((string) $data['ingredients'], true);
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
    $ingOut = json_encode($ingOut, JSON_UNESCAPED_UNICODE);
}

// шаги: [{text, hasPhoto}] + файлы step_photos[]; существующие фото приходят как {image}
$stepOut = null;
$oldStepImages = array_values(array_filter(array_map(
    fn($s) => (string) ($s['image'] ?? ''),
    json_decode($recipe['steps'] ?? '[]', true) ?: []
)));
if (array_key_exists('steps', $data)) {
    $steps = json_decode((string) $data['steps'], true);
    if (!is_array($steps)) {
        json_out(['error' => 'Некорректный формат шагов']);
    }
    if (count($steps) > 50) {
        json_out(['error' => 'Слишком много шагов (макс. 50)']);
    }

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
        $image = null;
        if (!empty($st['hasPhoto']) && isset($stepFiles[$fileIdx])) {
            try {
                $image = save_recipe_image($stepFiles[$fileIdx]);
            } catch (RuntimeException $e) {
                json_out(['error' => 'Фото шага: ' . $e->getMessage()]);
            }
            $fileIdx++;
        } elseif (!empty($st['image'])) {
            // существующее фото шага (оставляем, только если оно было у этого рецепта)
            $existing = (string) $st['image'];
            if (in_array($existing, $oldStepImages, true)) {
                $image = $existing;
            }
        }
        if ($text === '' && $image === null) {
            continue;
        }
        $stepOut[] = ['text' => $text, 'image' => $image];
    }
    // удаляем фото шагов, которые убрали или заменили
    $kept = array_values(array_filter(array_map(fn($s) => (string) ($s['image'] ?? ''), $stepOut)));
    foreach ($oldStepImages as $old) {
        if ($old !== '' && !in_array($old, $kept, true)) {
            remove_recipe_file($old);
        }
    }
    $stepOut = json_encode($stepOut, JSON_UNESCAPED_UNICODE);
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

if ($image) {
    remove_recipe_file($recipe['image']);
    $pdo->prepare(
        'UPDATE recipes SET title=?, description=?, ingredients=COALESCE(?, ingredients), steps=COALESCE(?, steps), image=? WHERE id=? AND user_id=?'
    )->execute([$title, $description, $ingOut, $stepOut, $image, $recipeId, $userId]);
} else {
    $pdo->prepare(
        'UPDATE recipes SET title=?, description=?, ingredients=COALESCE(?, ingredients), steps=COALESCE(?, steps) WHERE id=? AND user_id=?'
    )->execute([$title, $description, $ingOut, $stepOut, $recipeId, $userId]);
}

json_out(['success' => true, 'message' => 'Рецепт обновлён']);
