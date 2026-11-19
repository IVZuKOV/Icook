<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once '/etc/icook/config.php';
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$hasColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$hasColumn->execute([$database, 'recipes', 'category_id']);
if (!(int) $hasColumn->fetchColumn()) {
    $pdo->exec('ALTER TABLE recipes ADD COLUMN category_id INT NULL AFTER user_id, ADD INDEX idx_recipes_category_id (category_id)');
}
$hasIndex = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
);
$hasIndex->execute([$database, 'recipes', 'idx_recipes_category_id']);
if (!(int) $hasIndex->fetchColumn()) {
    $pdo->exec('ALTER TABLE recipes ADD INDEX idx_recipes_category_id (category_id)');
}
$hasConstraint = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
);
$hasConstraint->execute([$database, 'recipes', 'fk_recipes_category']);
if (!(int) $hasConstraint->fetchColumn()) {
    $pdo->exec(
        'ALTER TABLE recipes ADD CONSTRAINT fk_recipes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL'
    );
}

$findCategory = $pdo->prepare(
    'SELECT id FROM categories WHERE name = ? AND ' . 'parent_id IS NULL'
);
$findChildCategory = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND parent_id = ?');
$categoryIdForPath = static function (array $path) use ($findCategory, $findChildCategory): ?int {
    $parentId = null;
    foreach ($path as $name) {
        if ($parentId === null) {
            $findCategory->execute([$name]);
            $id = $findCategory->fetchColumn();
        } else {
            $findChildCategory->execute([$name, $parentId]);
            $id = $findChildCategory->fetchColumn();
        }
        if ($id === false) return null;
        $parentId = (int) $id;
    }
    return $parentId;
};
$rules = [
    ['~медовик|чизкейк|наполеон|торт~iu', ['Торты']],
    ['~блин|олад|сырник|каша|омлет|яичниц~iu', ['Завтраки']],
    ['~суп|борщ|щи|уха|окрош|рамен|том[ -]?ям|фо[ -]?бо~iu', ['Супы']],
    ['~салат|цезарь|оливье~iu', ['Салаты']],
    ['~суши|ролл|сашими|нигири|онигири~iu', ['Суши и роллы']],
    ['~пицц~iu', ['Пицца']],
    ['~паст[аы]|лазан|спагет|карбонар|болоньез~iu', ['Паста']],
    ['~бургер~iu', ['Бургеры']],
    ['~шашлык|гриль|кебаб~iu', ['Шашлык и гриль']],
    ['~хачапури|пирог|пирож|хлеб|круассан|самса|киш~iu', ['Выпечка']],
    ['~кофе|капучино|латте|чай|лимонад|смузи|компот|сок~iu', ['Напитки']],
    ['~лосос|рыб|кревет|кальмар|мидии|морепродукт~iu', ['Рыба и морепродукты']],
    ['~котлет~iu', ['Обеды', 'Домашняя кухня', 'Котлеты']],
    ['~куриц|свинин|говядин|баран|утк|мясн|мясо~iu', ['Мясные блюда']],
    ['~глазур|десерт|эклер|печень|пирожн|конфет|шоколад|вафл|морожен~iu', ['Десерты']],
];
$pdo->beginTransaction();
$pendingRecipes = $pdo->query('SELECT id, title FROM recipes WHERE category_id IS NULL ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$assignCategory = $pdo->prepare('UPDATE recipes SET category_id = ? WHERE id = ? AND category_id IS NULL');
$categorized = 0;
foreach ($pendingRecipes as $recipe) {
    foreach ($rules as [$pattern, $path]) {
        if (!preg_match($pattern, (string) $recipe['title'])) continue;
        $categoryId = $categoryIdForPath($path);
        if ($categoryId !== null) {
            $assignCategory->execute([$categoryId, (int) $recipe['id']]);
            $categorized += $assignCategory->rowCount();
        }
        break;
    }
}
if (getenv('ICOOK_RECIPE_CATEGORY_BACKFILL_DRY_RUN') === '1') {
    $pdo->rollBack();
    echo "Recipe category column is ready; dry run would classify $categorized recipes.\n";
} else {
    $pdo->commit();
    echo "Recipe category column is ready; automatically classified $categorized existing recipes by title.\n";
}
