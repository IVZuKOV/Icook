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
    $dishId = null;
    if (array_key_exists('dish_id', $_GET)) {
        $dishId = (int) $_GET['dish_id'];
        $dishOwner = $pdo->prepare('SELECT user_id FROM dishes WHERE id = ?');
        $dishOwner->execute([$dishId]);
        if ($dishId <= 0 || (int) $dishOwner->fetchColumn() !== $userId) {
            json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);
        }
    }
    $stmt = $pdo->prepare(
        'SELECT p.id, p.code, p.discount_percent, p.is_global, p.created_at, p.updated_at,
                GROUP_CONCAT(pd.dish_id ORDER BY pd.dish_id) AS dish_ids
         FROM promocodes p
         LEFT JOIN promocode_dishes pd ON pd.promocode_id = p.id
         WHERE p.cook_id = ? AND p.is_active = 1
           AND (? IS NOT NULL OR p.is_global = 1)
         GROUP BY p.id, p.code, p.discount_percent, p.is_global, p.created_at, p.updated_at
         ORDER BY p.code ASC, p.id ASC'
    );
    $stmt->execute([$userId, $dishId]);
    $promocodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($promocodes as &$promocode) {
        $promocode['is_global'] = (int) $promocode['is_global'];
        $promocode['dish_ids'] = $promocode['dish_ids'] === null
            ? []
            : array_map('intval', explode(',', $promocode['dish_ids']));
    }
    unset($promocode);
    json_out(['success' => true, 'promocodes' => $promocodes]);
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
    $onlyThisDish = !empty($data['only_this_dish']) || !empty($data['dish_id']);
    $targetDishId = (int) ($data['current_dish_id'] ?? $data['dish_id'] ?? 0);
    if ($onlyThisDish) {
        $dishOwner = $pdo->prepare('SELECT user_id FROM dishes WHERE id = ?');
        $dishOwner->execute([$targetDishId]);
        if ($targetDishId <= 0 || (int) $dishOwner->fetchColumn() !== $userId) {
            json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);
        }
    }

    $pdo->beginTransaction();
    if ($action === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO promocodes (cook_id, code, discount_percent, is_global)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $code, $percent, $onlyThisDish ? 0 : 1]);
        $id = (int) $pdo->lastInsertId();
        if ($onlyThisDish) {
            $pdo->prepare('INSERT INTO promocode_dishes (promocode_id, dish_id) VALUES (?, ?)')
                ->execute([$id, $targetDishId]);
        }
    } else {
        $id = (int) ($data['id'] ?? 0);
        $existing = $pdo->prepare('SELECT is_global FROM promocodes WHERE id = ? AND cook_id = ? AND is_active = 1 FOR UPDATE');
        $existing->execute([$id, $userId]);
        $existingIsGlobal = $existing->fetchColumn();
        if ($existingIsGlobal === false) {
            json_out(['success' => false, 'error' => 'Промокод не найден'], 404);
        }
        $stmt = $pdo->prepare(
            'UPDATE promocodes SET code = ?, discount_percent = ?, is_global = ?
             WHERE id = ? AND cook_id = ? AND is_active = 1'
        );
        $stmt->execute([$code, $percent, $onlyThisDish ? 0 : 1, $id, $userId]);
        if ($onlyThisDish) {
            if ((int) $existingIsGlobal === 1) {
                $snapshot = $pdo->prepare(
                    'INSERT IGNORE INTO promocode_dishes (promocode_id, dish_id)
                     SELECT ?, id FROM dishes WHERE user_id = ?'
                );
                $snapshot->execute([$id, $userId]);
            }
            $pdo->prepare('INSERT IGNORE INTO promocode_dishes (promocode_id, dish_id) VALUES (?, ?)')
                ->execute([$id, $targetDishId]);
        }
    }
    $pdo->commit();
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string) $error->getCode() === '23000') {
        json_out(['success' => false, 'error' => 'У вас уже есть промокод с таким названием'], 409);
    }
    error_log('Promocode save failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось сохранить промокод'], 500);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

json_out([
    'success' => true,
    'promocode' => ['id' => $id, 'code' => $code, 'discount_percent' => $percent, 'is_global' => $onlyThisDish ? 0 : 1],
]);
