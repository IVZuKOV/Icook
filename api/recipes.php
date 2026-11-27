<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/default_dish_images.php';

$authorId = (int) ($_GET['author_id'] ?? 0);
$categoryId = (int) ($_GET['category_id'] ?? 0);
$recipeIds = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))), static fn (int $id): bool => $id > 0))), 0, 100);
$search = trim((string) ($_GET['search'] ?? ''));
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = $recipeIds ? min(100, count($recipeIds)) : max(1, min(10, (int) ($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// Владелец видит и скрытые свои рецепты (для управления), остальные — только опубликованные.
$showHidden = ($authorId > 0 && isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $authorId);
$where = $showHidden ? '1=1' : 'r.is_available = 1';
$params = [];

if ($authorId) {
    $where .= ' AND r.user_id = ?';
    $params[] = $authorId;
}
if ($categoryId > 0) {
    $where .= ' AND r.category_id IN (SELECT id FROM categories WHERE id = ? OR parent_id = ? OR parent_id IN (SELECT id FROM categories WHERE parent_id = ?))';
    array_push($params, $categoryId, $categoryId, $categoryId);
}
if ($recipeIds) {
    $where .= ' AND r.id IN (' . implode(',', array_fill(0, count($recipeIds), '?')) . ')';
    array_push($params, ...$recipeIds);
}
if ($search !== '') {
    $where .= ' AND (r.title LIKE ? OR r.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT r.*, u.name as author_name,
                CASE WHEN u.avatar_public = 1 THEN u.avatar ELSE '' END AS author_avatar,
                CASE WHEN u.avatar_public = 1 THEN u.avatar_version ELSE '' END AS author_avatar_version,
                CASE WHEN u.avatar_public = 1 THEN u.avatar_crop ELSE NULL END AS author_avatar_crop,
                c.name AS category_name
        FROM recipes r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE $where
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $value) {
    $stmt->bindValue($i++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();

$recipes = $stmt->fetchAll();
$recipes = apply_default_dish_images($recipes);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM recipes r WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

json_out([
    'success' => true,
    'recipes' => $recipes,
    'total' => $total,
    'pages' => (int) ceil($total / $limit),
    'page' => $page,
]);
