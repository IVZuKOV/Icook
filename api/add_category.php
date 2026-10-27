<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$name = trim((string) ($data['name'] ?? ''));
$parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
$icon = trim((string) ($data['icon'] ?? '🍴'));

if ($name === '') {
    json_out(['error' => 'Введите название категории']);
}
if (mb_strlen($name) > 100) {
    json_out(['error' => 'Название слишком длинное']);
}
if (mb_strlen($icon) > 16) {
    $icon = '🍴';
}
if ($parentId !== null && $parentId <= 0) {
    $parentId = null;
}

$stmt = $pdo->prepare(
    'INSERT INTO categories (name, parent_id, icon, is_custom, created_by, sort_order) VALUES (?, ?, ?, 1, ?, 99)'
);
$stmt->execute([$name, $parentId, $icon, $userId]);

json_out(['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Категория добавлена']);
