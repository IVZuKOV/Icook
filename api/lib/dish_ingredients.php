<?php
declare(strict_types=1);

function dish_ingredients_parse(mixed $value): array
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }
    if (!is_array($value) || count($value) < 1 || count($value) > 40) {
        throw new InvalidArgumentException('Добавьте от 1 до 40 ингредиентов');
    }

    $ingredients = [];
    foreach ($value as $ingredient) {
        if (!is_array($ingredient)) {
            throw new InvalidArgumentException('Некорректная строка состава');
        }
        $name = trim((string) ($ingredient['name'] ?? ''));
        $amount = $ingredient['amount_grams'] ?? null;
        $energy = $ingredient['energy_kj_per_100g'] ?? null;
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Укажите название каждого ингредиента (до 100 символов)');
        }
        if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 100000) {
            throw new InvalidArgumentException('Укажите корректный вес каждого ингредиента в граммах');
        }
        if (!is_numeric($energy) || (float) $energy < 0 || (float) $energy > 10000) {
            throw new InvalidArgumentException('Укажите кДж на 100 г для каждого ингредиента (0 для воды и соли)');
        }
        $ingredients[] = [
            'name' => $name,
            'amount_grams' => round((float) $amount, 2),
            'energy_kj_per_100g' => round((float) $energy, 2),
        ];
    }
    return $ingredients;
}

function dish_ingredients_replace(PDO $pdo, int $dishId, array $ingredients): void
{
    $pdo->prepare('DELETE FROM dish_ingredients WHERE dish_id = ?')->execute([$dishId]);
    $insert = $pdo->prepare(
        'INSERT INTO dish_ingredients (dish_id, ingredient_name, amount_grams, energy_kj_per_100g, position)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($ingredients as $index => $ingredient) {
        $insert->execute([
            $dishId,
            $ingredient['name'],
            $ingredient['amount_grams'],
            $ingredient['energy_kj_per_100g'],
            $index,
        ]);
    }
}

/** Add dish ingredient names to the shared unique dictionary, inside the save transaction. */
function dish_ingredients_catalog_sync(PDO $pdo, array $ingredients): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Dish ingredient catalog sync requires a transaction');
    }
    $names = [];
    foreach ($ingredients as $ingredient) {
        $normalized = ingredient_catalog_name((string) ($ingredient['name'] ?? ''));
        $names[$normalized['key']] = $normalized['name'];
    }
    ksort($names, SORT_STRING);
    $insert = $pdo->prepare('INSERT IGNORE INTO icook_ingredients.ingredients (name, normalized_name) VALUES (?, ?)');
    $find = $pdo->prepare('SELECT id FROM icook_ingredients.ingredients WHERE normalized_name = ?');
    foreach ($names as $key => $name) {
        $insert->execute([$name, $key]);
        $find->execute([$key]);
        if ((int) $find->fetchColumn() <= 0) {
            throw new RuntimeException('Ingredient was not recorded in the shared catalog');
        }
    }
}

function dish_ingredients_group(PDO $pdo, array $dishIds): array
{
    $dishIds = array_values(array_unique(array_filter(array_map('intval', $dishIds), static fn (int $id): bool => $id > 0)));
    if (!$dishIds) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT dish_id, ingredient_name, amount_grams, energy_kj_per_100g
         FROM dish_ingredients
         WHERE dish_id IN (' . implode(',', array_fill(0, count($dishIds), '?')) . ')
         ORDER BY dish_id, position, id'
    );
    $stmt->execute($dishIds);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['dish_id'];
        $grouped[$id][] = [
            'name' => (string) $row['ingredient_name'],
            'amount_grams' => (float) $row['amount_grams'],
            'energy_kj_per_100g' => (float) $row['energy_kj_per_100g'],
        ];
    }
    foreach ($grouped as &$ingredients) {
        $totalGrams = 0.0;
        $totalEnergy = 0.0;
        foreach ($ingredients as $ingredient) {
            $totalGrams += $ingredient['amount_grams'];
            $totalEnergy += $ingredient['amount_grams'] * $ingredient['energy_kj_per_100g'] / 100;
        }
        $ingredients['_nutrition'] = [
            'weight_grams' => round($totalGrams, 2),
            'energy_kj' => round($totalEnergy, 2),
            'energy_kj_per_100g' => $totalGrams > 0 ? round($totalEnergy * 100 / $totalGrams, 2) : 0,
        ];
    }
    unset($ingredients);
    return $grouped;
}
