<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$authorId = (int) ($_GET['author_id'] ?? 0);
$search = trim((string) ($_GET['search'] ?? ''));
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 12)));
$offset = ($page - 1) * $limit;

// Владелец видит и скрытые свои рецепты (для управления), остальные — только опубликованные.
$showHidden = ($authorId > 0 && isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $authorId);
$where = $showHidden ? '1=1' : 'r.is_available = 1';
$params = [];

if ($authorId) {
    $where .= ' AND r.user_id = ?';
    $params[] = $authorId;
}
if ($search !== '') {
    $where .= ' AND (r.title LIKE ? OR r.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT r.*, u.name as author_name
        FROM recipes r
        LEFT JOIN users u ON r.user_id = u.id
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
