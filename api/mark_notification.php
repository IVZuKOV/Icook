<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$notifId = (int) ($data['id'] ?? 0);
$markAll = (bool) ($data['mark_all'] ?? false);

if ($markAll) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
    json_out(['success' => true, 'message' => 'Все отмечены']);
}

if ($notifId > 0) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notifId, $userId]);
    json_out(['success' => true, 'message' => 'Отмечено']);
}

json_out(['success' => false, 'error' => 'Нет параметров'], 400);
