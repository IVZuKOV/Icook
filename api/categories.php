<?php
header('Content-Type: application/json');
require 'db.php';

$stmt = $pdo->query("SELECT id, parent_id, name, icon, is_custom FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'categories' => $categories]);
?>
