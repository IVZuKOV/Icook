<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$stmt = $pdo->query('SELECT id, parent_id, name, icon, is_custom FROM categories ORDER BY sort_order, name');
json_out(['success' => true, 'categories' => $stmt->fetchAll()]);
