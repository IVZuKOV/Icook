<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/promocodes.php';

$userId = require_login();
$owner = $pdo->prepare('SELECT is_seller FROM users WHERE id = ?');
$owner->execute([$userId]);
if ((int) $owner->fetchColumn() !== 1) {
    json_out(['success' => false, 'error' => 'Промокоды доступны только поварам'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT id, code, discount_percent, created_at, updated_at
         FROM promocodes WHERE cook_id = ? AND is_active = 1 ORDER BY code ASC, id ASC'
    );
    $stmt->execute([$userId]);
    json_out(['success' => true, 'promocodes' => $stmt->fetchAll()]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = body_json();
$action = (string) ($data['action'] ?? '');
if ($action === 'delete') {
    $id = (int) ($data['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM promocodes WHERE id = ? AND cook_id = ?');
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() < 1) {
        json_out(['success' => false, 'error' => 'Промокод не найден'], 404);
    }
    json_out(['success' => true, 'deleted_id' => $id]);
}

if (!in_array($action, ['create', 'update'], true)) {
    json_out(['success' => false, 'error' => 'Неизвестное действие'], 400);
}

try {
    $code = normalize_promocode((string) ($data['code'] ?? ''));
} catch (InvalidArgumentException $error) {
    json_out(['success' => false, 'error' => $error->getMessage()], 400);
}

$percent = filter_var($data['discount_percent'] ?? null, FILTER_VALIDATE_INT);
if ($percent === false || $percent < 1 || $percent > 100) {
    json_out(['success' => false, 'error' => 'Скидка должна быть целым числом от 1 до 100'], 400);
}

try {
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO promocodes (cook_id, code, discount_percent) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $code, $percent]);
        $id = (int) $pdo->lastInsertId();
    } else {
        $id = (int) ($data['id'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE promocodes SET code = ?, discount_percent = ?
             WHERE id = ? AND cook_id = ? AND is_active = 1'
        );
        $stmt->execute([$code, $percent, $id, $userId]);
        if ($stmt->rowCount() < 1) {
            $check = $pdo->prepare('SELECT id FROM promocodes WHERE id = ? AND cook_id = ? AND is_active = 1');
            $check->execute([$id, $userId]);
            if (!$check->fetchColumn()) {
                json_out(['success' => false, 'error' => 'Промокод не найден'], 404);
            }
        }
    }
} catch (PDOException $error) {
    if ((string) $error->getCode() === '23000') {
        json_out(['success' => false, 'error' => 'У вас уже есть промокод с таким названием'], 409);
    }
    error_log('Promocode save failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось сохранить промокод'], 500);
}

json_out([
    'success' => true,
    'promocode' => ['id' => $id, 'code' => $code, 'discount_percent' => $percent],
]);
