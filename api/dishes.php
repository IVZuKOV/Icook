<?php
header('Content-Type: application/json');
require 'db.php';

$category_id = intval($_GET['category_id'] ?? 0);
$seller_id = intval($_GET['seller_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$sql = "SELECT d.*, u.name as seller_name, c.name as category_name 
        FROM dishes d 
        LEFT JOIN users u ON d.user_id = u.id 
        LEFT JOIN categories c ON d.category_id = c.id 
        WHERE d.is_available = 1";

$params = [];

if ($category_id) {
    $sql .= " AND d.category_id = ?";
    $params[] = $category_id;
}
if ($seller_id) {
    $sql .= " AND d.user_id = ?";
    $params[] = $seller_id;
}
if ($search) {
    $sql .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dishes d WHERE d.is_available = 1" . 
    ($category_id ? " AND d.category_id = $category_id" : "") .
    ($seller_id ? " AND d.user_id = $seller_id" : ""));
$countStmt->execute();
$total = $countStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'dishes' => $dishes,
    'total' => (int)$total,
    'pages' => ceil($total / $limit),
    'page' => $page
]);
?>
