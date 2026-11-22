<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/default_dish_images.php';

$categoryId = (int) ($_GET['category_id'] ?? 0);
$sellerId = (int) ($_GET['seller_id'] ?? 0);
$dishIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))), static fn (int $id): bool => $id > 0)));
$search = trim((string) ($_GET['search'] ?? ''));
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = $dishIds ? min(100, count($dishIds)) : 12;
$offset = ($page - 1) * $limit;

// Владелец видит и скрытые свои блюда (для управления), остальные — только доступные.
$showHidden = ($sellerId > 0 && isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $sellerId);
$where = $showHidden ? '1=1' : 'd.is_available = 1';
$params = [];

if ($sellerId) {
    $where .= ' AND d.user_id = ?';
    $params[] = $sellerId;
}
if ($categoryId) {
    $where .= ' AND d.category_id IN (SELECT id FROM categories WHERE id = ? OR parent_id = ? OR parent_id IN (SELECT id FROM categories WHERE parent_id = ?))';
    array_push($params, $categoryId, $categoryId, $categoryId);
}
if ($dishIds) {
    $where .= ' AND d.id IN (' . implode(',', array_fill(0, count($dishIds), '?')) . ')';
    array_push($params, ...$dishIds);
}
if ($search !== '') {
    $where .= ' AND (d.title LIKE ? OR d.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT d.*, u.name as seller_name,
                CASE WHEN u.avatar_public = 1 THEN u.avatar ELSE '' END AS seller_avatar,
                CASE WHEN u.avatar_public = 1 THEN u.avatar_version ELSE '' END AS seller_avatar_version,
                CASE WHEN u.avatar_public = 1 THEN u.avatar_crop ELSE NULL END AS seller_avatar_crop,
                c.name as category_name
        FROM dishes d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $value) {
    $stmt->bindValue($i++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();

$dishes = $stmt->fetchAll();
$dishes = apply_default_dish_images($dishes);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dishes d WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

json_out([
    'success' => true,
    'dishes' => $dishes,
    'total' => $total,
    'pages' => (int) ceil($total / $limit),
    'page' => $page,
]);
