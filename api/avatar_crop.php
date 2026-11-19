<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$userId = require_login();
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_out(['success' => false, 'error' => 'Некорректные параметры кадра'], 400);
}

$zoom = filter_var($payload['zoom'] ?? null, FILTER_VALIDATE_FLOAT);
$x = filter_var($payload['x'] ?? null, FILTER_VALIDATE_FLOAT);
$y = filter_var($payload['y'] ?? null, FILTER_VALIDATE_FLOAT);
if ($zoom === false || $x === false || $y === false
    || $zoom < 1 || $zoom > 3 || abs($x) > 1 || abs($y) > 1) {
    json_out(['success' => false, 'error' => 'Недопустимые параметры кадра'], 400);
}

$crop = json_encode(['zoom' => $zoom, 'x' => $x, 'y' => $y], JSON_THROW_ON_ERROR);
$stmt = $pdo->prepare('UPDATE users SET avatar_crop = ? WHERE id = ? AND avatar IS NOT NULL AND avatar <> \'\'');
$stmt->execute([$crop, $userId]);
if ($stmt->rowCount() < 1) {
    $check = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $check->execute([$userId]);
    if (!$check->fetchColumn()) {
        json_out(['success' => false, 'error' => 'Сначала загрузите фото профиля'], 404);
    }
}

json_out(['success' => true, 'avatar_crop' => ['zoom' => $zoom, 'x' => $x, 'y' => $y]]);
