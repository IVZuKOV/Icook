<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ingredients.php';

$recipeFilesToRemove = [];
$newRecipeImages = [];

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
    global $recipeFilesToRemove;
    if ($path) {
        $recipeFilesToRemove[] = $path;
    }
}

function flush_recipe_files(): void
{
    global $recipeFilesToRemove;
    foreach (array_unique($recipeFilesToRemove) as $path) {
        @unlink('/var/www/html/icook' . $path);
    }
}

if ($action === 'delete') {
    remove_recipe_file($recipe['image']);
    foreach (json_decode($recipe['steps'] ?? '[]', true) ?: [] as $st) {
        remove_recipe_file($st['image'] ?? null);
        foreach ((array) ($st['images'] ?? []) as $im) {
            remove_recipe_file($im ?: null);
        }
    }
    $pdo->prepare('DELETE FROM recipes WHERE id = ? AND user_id = ?')->execute([$recipeId, $userId]);
    flush_recipe_files();
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
    $ingOut = json_encode($ingOut, JSON_UNESCAPED_UNICODE);
}

// шаги: [{text, images:[прежние], photoCount}] + файлы step_photos[]; старые фото сверяем со списком прежних
$stepOut = null;
$oldStepImages = [];
foreach (json_decode($recipe['steps'] ?? '[]', true) ?: [] as $s) {
    if (!empty($s['image'])) {
        $oldStepImages[] = (string) $s['image'];
    }
    foreach ((array) ($s['images'] ?? []) as $im) {
        if (!empty($im)) {
            $oldStepImages[] = (string) $im;
        }
    }
}
$oldStepImages = array_values(array_unique($oldStepImages));
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
        // прежние фото шага (оставляем, только если они были у этого рецепта)
        $images = [];
        foreach ((array) ($st['images'] ?? []) as $u) {
            $existing = (string) $u;
            if ($existing !== '' && in_array($existing, $oldStepImages, true) && !in_array($existing, $images, true)) {
                $images[] = $existing;
            }
        }
        // старый формат: одиночное image
        if (!$images && !empty($st['image'])) {
            $existing = (string) $st['image'];
            if (in_array($existing, $oldStepImages, true)) {
                $images[] = $existing;
            }
        }
        // новые фото шага: photoCount файлов подряд
        $count = (int) ($st['photoCount'] ?? (!empty($st['hasPhoto']) ? 1 : 0));
        if ($count < 0) {
            $count = 0;
        }
        if ($count > 20) {
            $count = 20;
        }
        for ($k = 0; $k < $count; $k++) {
            if (!isset($stepFiles[$fileIdx])) {
                break;
            }
            try {
                $uploaded = save_recipe_image($stepFiles[$fileIdx]);
                $images[] = $uploaded;
                $newRecipeImages[] = $uploaded;
            } catch (RuntimeException $e) {
                json_out(['error' => 'Фото шага: ' . $e->getMessage()]);
            }
            $fileIdx++;
        }
        if ($text === '' && !$images) {
            continue;
        }
        $stepOut[] = ['text' => $text, 'image' => $images[0] ?? null, 'images' => $images];
    }
    // удаляем фото шагов, которые убрали или заменили
    $kept = [];
    foreach ($stepOut as $s) {
        foreach ((array) ($s['images'] ?? []) as $im) {
            if (!empty($im)) {
                $kept[] = (string) $im;
            }
        }
    }
    foreach ($oldStepImages as $old) {
        if ($old !== '' && !in_array($old, $kept, true)) {
            remove_recipe_file($old);
        }
    }
    $stepOut = json_encode($stepOut, JSON_UNESCAPED_UNICODE);
}

// главное фото
$image = null;
$removeImage = !empty($data['remove_image']);
if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $image = save_recipe_image($_FILES['image']);
        $newRecipeImages[] = $image;
    } catch (RuntimeException $e) {
        json_out(['error' => $e->getMessage()]);
    }
}

try {
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM recipes WHERE id = ? AND user_id = ? FOR UPDATE');
    $lock->execute([$recipeId, $userId]);
    if (!$lock->fetchColumn()) throw new RuntimeException('Recipe no longer available');
    if ($image) {
        remove_recipe_file($recipe['image']);
        $pdo->prepare(
            'UPDATE recipes SET title=?, description=?, ingredients=COALESCE(?, ingredients), steps=COALESCE(?, steps), image=? WHERE id=? AND user_id=?'
        )->execute([$title, $description, $ingOut, $stepOut, $image, $recipeId, $userId]);
    } elseif ($removeImage) {
        remove_recipe_file($recipe['image']);
        $pdo->prepare(
            'UPDATE recipes SET title=?, description=?, ingredients=COALESCE(?, ingredients), steps=COALESCE(?, steps), image=NULL WHERE id=? AND user_id=?'
        )->execute([$title, $description, $ingOut, $stepOut, $recipeId, $userId]);
    } else {
        $pdo->prepare(
            'UPDATE recipes SET title=?, description=?, ingredients=COALESCE(?, ingredients), steps=COALESCE(?, steps) WHERE id=? AND user_id=?'
        )->execute([$title, $description, $ingOut, $stepOut, $recipeId, $userId]);
    }
    if ($ingOut !== null) {
        ingredient_catalog_sync($pdo, $recipeId, json_decode($ingOut, true, 512, JSON_THROW_ON_ERROR));
    }
    $pdo->commit();
    // Never remove existing recipe photos before both databases have committed.
    flush_recipe_files();
    json_out(['success' => true, 'message' => 'Рецепт обновлён']);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach (array_unique($newRecipeImages) as $file) @unlink('/var/www/html/icook' . $file);
    error_log('Edit recipe failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось сохранить рецепт. Попробуйте ещё раз.'], 500);
}
