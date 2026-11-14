<?php
declare(strict_types=1);

/** Canonical display name and deduplication key. No connection credentials here. */
function ingredient_catalog_name(string $value): array
{
    $name = Normalizer::normalize($value, Normalizer::FORM_KC);
    if ($name === false) {
        throw new InvalidArgumentException('Некорректное название ингредиента');
    }
    $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name);
    $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', (string) $name));
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Название ингредиента должно содержать от 1 до 100 символов');
    }
    $key = str_replace('ё', 'е', mb_strtolower($name, 'UTF-8'));
    return ['name' => $name, 'key' => $key];
}

/** Must be called in the same transaction that saves the recipe. */
function ingredient_catalog_sync(PDO $pdo, int $recipeId, array $ingredients): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Ingredient synchronization requires a transaction');
    }
    $recipe = $pdo->prepare('SELECT id FROM recipes WHERE id = ? FOR UPDATE');
    $recipe->execute([$recipeId]);
    if (!$recipe->fetchColumn()) {
        throw new RuntimeException('Recipe missing during ingredient synchronization');
    }
    $names = [];
    foreach ($ingredients as $ingredient) {
        if (!is_array($ingredient) || !isset($ingredient['name']) || !is_string($ingredient['name'])) {
            throw new InvalidArgumentException('Некорректный ингредиент');
        }
        $normalized = ingredient_catalog_name($ingredient['name']);
        if (!array_key_exists($normalized['key'], $names)) {
            $names[$normalized['key']] = $normalized['name'];
        }
    }
    // Consistent order reduces lock contention for concurrently added names.
    ksort($names, SORT_STRING);
    $insert = $pdo->prepare('INSERT IGNORE INTO icook_ingredients.ingredients (name, normalized_name) VALUES (?, ?)');
    $find = $pdo->prepare('SELECT id FROM icook_ingredients.ingredients WHERE normalized_name = ?');
    $ids = [];
    foreach ($names as $key => $name) {
        $insert->execute([$name, (string) $key]);
        $find->execute([(string) $key]);
        $id = (int) $find->fetchColumn();
        if ($id <= 0) {
            throw new RuntimeException('Ingredient was not recorded');
        }
        $ids[] = $id;
    }
    $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
    $link = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, ingredient_id) VALUES (?, ?)');
    foreach ($ids as $id) {
        $link->execute([$recipeId, $id]);
    }
}

function ingredient_catalog_suggestions(PDO $pdo, string $query, int $limit = 10): array
{
    $key = $query === '' ? '' : ingredient_catalog_name($query)['key'];
    $prefix = strtr($key, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $stmt = $pdo->prepare(
        "SELECT i.id, i.name, COALESCE(popularity.recipe_count, 0) AS recipe_count
         FROM icook_ingredients.ingredients i
         LEFT JOIN (
             SELECT ri.ingredient_id, COUNT(*) AS recipe_count
             FROM recipe_ingredients ri
             INNER JOIN recipes r ON r.id = ri.recipe_id AND r.is_available = 1
             GROUP BY ri.ingredient_id
         ) popularity ON popularity.ingredient_id = i.id
         WHERE (i.normalized_name LIKE ? ESCAPE '!' OR i.normalized_name LIKE ? ESCAPE '!')
         ORDER BY COALESCE(popularity.recipe_count, 0) DESC, i.seed_priority DESC, i.name ASC, i.id ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $prefix, PDO::PARAM_STR);
    $stmt->bindValue(2, '% ' . $prefix, PDO::PARAM_STR);
    $stmt->bindValue(3, max(1, min(15, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return array_map(static function (array $row): array {
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'recipe_count' => (int) $row['recipe_count']];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
