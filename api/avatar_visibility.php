<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$userId = require_login();
$data = body_json();
$visibility = filter_var($data['avatar_public'] ?? null, FILTER_VALIDATE_INT);

if ($visibility !== 0 && $visibility !== 1) {
    json_out(['success' => false, 'error' => 'Некорректное значение видимости аватара'], 400);
}

$stmt = $pdo->prepare('UPDATE users SET avatar_public = ? WHERE id = ?');
$stmt->execute([$visibility, $userId]);

json_out(['success' => true, 'avatar_public' => $visibility]);
