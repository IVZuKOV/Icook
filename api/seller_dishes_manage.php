<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT d.id, d.title, d.price, d.weight, d.image, d.is_available,
                d.discount_percent, c.name AS category_name,
                inventory.remaining_count
         FROM dishes d
         LEFT JOIN categories c ON c.id = d.category_id
         LEFT JOIN dish_inventory inventory ON inventory.dish_id = d.id
         WHERE d.user_id = ?
         ORDER BY d.created_at DESC, d.id DESC'
    );
    $stmt->execute([$userId]);
    json_out(['success' => true, 'dishes' => $stmt->fetchAll()]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'Метод не поддерживается'], 405);
}

$data = body_json();
$action = (string) ($data['action'] ?? '');

if ($action === 'availability') {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', is_array($data['dish_ids'] ?? null) ? $data['dish_ids'] : []),
        static fn (int $id): bool => $id > 0
    )));
    $available = filter_var($data['is_available'] ?? null, FILTER_VALIDATE_INT);
    if (!$ids || count($ids) > 500 || !in_array($available, [0, 1], true)) {
        json_out(['error' => 'Выберите блюда и укажите корректный статус'], 422);
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE dishes SET is_available = ? WHERE user_id = ? AND id IN ($placeholders)");
    $stmt->execute(array_merge([$available, $userId], $ids));
    json_out(['success' => true, 'updated' => $stmt->rowCount()]);
}

if ($action === 'stock') {
    $items = $data['items'] ?? null;
    if (!is_array($items) || !$items || count($items) > 500) {
        json_out(['error' => 'Нет остатков для сохранения'], 422);
    }
    $pdo->beginTransaction();
    try {
        $findDish = $pdo->prepare('SELECT id FROM dishes WHERE id = ? AND user_id = ? FOR UPDATE');
        $saveStock = $pdo->prepare(
            'INSERT INTO dish_inventory (dish_id, remaining_count) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE remaining_count = VALUES(remaining_count)'
        );
        $deleteStock = $pdo->prepare('DELETE FROM dish_inventory WHERE dish_id = ?');
        $saved = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dishId = (int) ($item['dish_id'] ?? 0);
            $rawRemaining = $item['remaining_count'] ?? null;
            if ($dishId <= 0) {
                throw new InvalidArgumentException('Одно из блюд не найдено.');
            }
            if ($rawRemaining === null || $rawRemaining === '') {
                $remaining = null;
            } else {
                $validatedRemaining = filter_var($rawRemaining, FILTER_VALIDATE_INT);
                if ($validatedRemaining === false) {
                    throw new InvalidArgumentException('Укажите целое неотрицательное количество.');
                }
                $remaining = (int) $validatedRemaining;
            }
            if ($remaining !== null && ($remaining < 0 || $remaining > 1000000)) {
                throw new InvalidArgumentException('Остаток должен быть от 0 до 1 000 000.');
            }
            $findDish->execute([$dishId, $userId]);
            if (!$findDish->fetchColumn()) {
                throw new InvalidArgumentException('Одно из блюд не найдено.');
            }
            if ($remaining === null) {
                $deleteStock->execute([$dishId]);
            } else {
                $saveStock->execute([$dishId, $remaining]);
            }
            $saved++;
        }
        $pdo->commit();
        json_out(['success' => true, 'saved' => $saved]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof InvalidArgumentException) {
            json_out(['error' => $error->getMessage()], 422);
        }
        throw $error;
    }
}

if ($action === 'stock_adjust') {
    $dishId = (int) ($data['dish_id'] ?? 0);
    $delta = filter_var($data['delta'] ?? null, FILTER_VALIDATE_INT);
    if ($dishId <= 0 || !in_array($delta, [-1, 1], true)) {
        json_out(['error' => 'Некорректное изменение остатка'], 422);
    }
    $pdo->beginTransaction();
    try {
        $dish = $pdo->prepare('SELECT id FROM dishes WHERE id = ? AND user_id = ? FOR UPDATE');
        $dish->execute([$dishId, $userId]);
        if (!$dish->fetchColumn()) {
            $pdo->rollBack();
            json_out(['error' => 'Блюдо не найдено'], 404);
        }
        $stock = $pdo->prepare('SELECT remaining_count FROM dish_inventory WHERE dish_id = ? FOR UPDATE');
        $stock->execute([$dishId]);
        $current = $stock->fetchColumn();
        if ($current === false) {
            if ($delta < 0) {
                $pdo->commit();
                json_out(['success' => true, 'remaining_count' => null]);
            }
            $pdo->prepare('INSERT INTO dish_inventory (dish_id, remaining_count) VALUES (?, 1)')->execute([$dishId]);
            $remaining = 1;
        } else {
            $current = (int) $current;
            if ($delta < 0 && $current === 0) {
                $pdo->prepare('DELETE FROM dish_inventory WHERE dish_id = ?')->execute([$dishId]);
                $pdo->commit();
                json_out(['success' => true, 'remaining_count' => null]);
            }
            if ($delta > 0 && $current >= 1000000) {
                $pdo->rollBack();
                json_out(['error' => 'Максимальный остаток — 1 000 000'], 422);
            }
            $remaining = max(0, min(1000000, $current + $delta));
            $pdo->prepare('UPDATE dish_inventory SET remaining_count = ? WHERE dish_id = ?')->execute([$remaining, $dishId]);
        }
        $pdo->commit();
        json_out(['success' => true, 'remaining_count' => $remaining]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

if ($action === 'stock_unlimited') {
    $dishId = (int) ($data['dish_id'] ?? 0);
    if ($dishId <= 0) {
        json_out(['error' => 'Блюдо не найдено'], 422);
    }
    $check = $pdo->prepare('SELECT id FROM dishes WHERE id = ? AND user_id = ?');
    $check->execute([$dishId, $userId]);
    if (!$check->fetchColumn()) {
        json_out(['error' => 'Блюдо не найдено'], 404);
    }
    $pdo->prepare('DELETE FROM dish_inventory WHERE dish_id = ?')->execute([$dishId]);
    json_out(['success' => true, 'remaining_count' => null]);
}

json_out(['error' => 'Неизвестное действие'], 400);
