<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/promocodes.php';

$userId = require_login();
$data = body_json();

$items = $data['items'] ?? [];
$paymentMethod = trim((string) ($data['payment_method'] ?? ''));
$deliveryAddress = trim((string) ($data['delivery_address'] ?? ''));
$deliveryLat = $data['delivery_lat'] ?? null;
$deliveryLng = $data['delivery_lng'] ?? null;
$promoCodes = $data['promocodes'] ?? [];

if (!is_array($items) || count($items) === 0) {
    json_out(['error' => 'Корзина пуста']);
}
if (count($items) > 50) {
    json_out(['error' => 'Слишком много позиций в заказе']);
}
if (mb_strlen($paymentMethod) > 30 || mb_strlen($deliveryAddress) > 500) {
    json_out(['error' => 'Некорректные данные заказа']);
}
if (($deliveryLat === null) !== ($deliveryLng === null)) {
    json_out(['error' => 'Некорректные координаты адреса доставки'], 400);
}
if ($deliveryLat !== null) {
    if (!is_numeric($deliveryLat) || !is_numeric($deliveryLng)
        || (float) $deliveryLat < -90 || (float) $deliveryLat > 90
        || (float) $deliveryLng < -180 || (float) $deliveryLng > 180
        || $deliveryAddress === '' || mb_strlen($deliveryAddress) > 450) {
        json_out(['error' => 'Некорректные координаты или слишком длинный адрес доставки'], 400);
    }
    $deliveryAddress = trim($deliveryAddress) . ' (координаты: '
        . number_format((float) $deliveryLat, 6, '.', '') . ', '
        . number_format((float) $deliveryLng, 6, '.', '') . ')';
}
if (!is_array($promoCodes) || count($promoCodes) > 20) {
    json_out(['error' => 'Некорректный список промокодов']);
}

if ($deliveryAddress === '') {
    $stmt = $pdo->prepare('SELECT address FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $deliveryAddress = trim((string) ($stmt->fetchColumn() ?: ''));
}
if ($deliveryAddress === '') {
    json_out(['error' => 'Укажите адрес доставки']);
}

try {
    $pdo->beginTransaction();
    $qtyByDish = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $dishId = (int) ($item['id'] ?? 0);
        if ($dishId <= 0) {
            continue;
        }
        $qtyByDish[$dishId] = max(1, min(50, (int) ($qtyByDish[$dishId] ?? 0) + (int) ($item['qty'] ?? 1)));
    }
    if (!$qtyByDish) {
        json_out(['error' => 'В корзине нет действительных блюд'], 400);
    }

    $dishIds = array_keys($qtyByDish);
    $dishPlaceholders = implode(',', array_fill(0, count($dishIds), '?'));
    $dishStmt = $pdo->prepare(
        "SELECT d.id, d.price, d.discount_percent, d.user_id AS seller_id,
                inventory.remaining_count
         FROM dishes d
         LEFT JOIN dish_inventory inventory ON inventory.dish_id = d.id
         WHERE d.id IN ($dishPlaceholders) AND d.is_available = 1 FOR UPDATE"
    );
    $dishStmt->execute($dishIds);
    $dishesById = [];
    $dishSubtotalsByCook = [];
    foreach ($dishStmt->fetchAll() as $dish) {
        $dishId = (int) $dish['id'];
        $cookId = (int) $dish['seller_id'];
        $dish['quantity'] = $qtyByDish[$dishId];
        if ($dish['remaining_count'] !== null && $dish['quantity'] > (int) $dish['remaining_count']) {
            $pdo->rollBack();
            $remaining = (int) $dish['remaining_count'];
            $dishTitleStmt = $pdo->prepare('SELECT title FROM dishes WHERE id = ?');
            $dishTitleStmt->execute([$dishId]);
            $dishTitle = (string) ($dishTitleStmt->fetchColumn() ?: 'Блюдо');
            $message = $remaining === 0
                ? 'Блюдо «' . $dishTitle . '» закончилось на сегодня. Попробуйте заказать завтра или договоритесь о предзаказе с поваром.'
                : 'Для блюда «' . $dishTitle . '» осталось только ' . $remaining . ' шт. Уменьшите количество или договоритесь о предзаказе с поваром.';
            json_out([
                'error' => $message,
                'code' => 'out_of_stock',
                'dish_id' => $dishId,
                'remaining_count' => $remaining,
            ], 409);
        }
        $dish['sale_price'] = round((float) $dish['price'] * (1 - (int) $dish['discount_percent'] / 100), 2);
        $dish['line_subtotal'] = round($dish['sale_price'] * $dish['quantity'], 2);
        $dishesById[$dishId] = $dish;
        $dishSubtotalsByCook[$cookId][] = $dish['line_subtotal'];
    }
    if (count($dishesById) !== count($qtyByDish)) {
        json_out(['error' => 'Одно или несколько блюд больше недоступны. Обновите корзину.'], 409);
    }

    $discountPercentByCook = [];
    $discountDishIdsByCook = [];
    $seenCodes = [];
    $promoIdByCook = [];
    $promoCodeByCook = [];
    $sellerIds = array_keys($dishSubtotalsByCook);
    $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
    foreach ($promoCodes as $rawCode) {
        try {
            $code = normalize_promocode((string) $rawCode);
        } catch (InvalidArgumentException $error) {
            json_out(['error' => $error->getMessage()], 422);
        }
        if (isset($seenCodes[$code])) {
            json_out(['error' => 'Один и тот же промокод указан несколько раз'], 422);
        }
        $seenCodes[$code] = true;
        $promoStmt = $pdo->prepare(
            "SELECT id, cook_id, code, discount_percent, is_global FROM promocodes
             WHERE code = ? AND cook_id IN ($sellerPlaceholders) AND is_active = 1 FOR UPDATE"
        );
        $promoStmt->execute(array_merge([$code], $sellerIds));
        $matches = $promoStmt->fetchAll();
        if (count($matches) !== 1) {
            $message = count($matches) > 1
                ? 'Промокод подходит нескольким поварам. Удалите его и примените уникальный код.'
                : 'Промокод не найден или не подходит к блюдам в корзине.';
            json_out(['error' => $message], 422);
        }
        $promo = $matches[0];
        $cookId = (int) $promo['cook_id'];
        if (!promocode_belongs_to_cook($promo, $cookId) || isset($discountPercentByCook[$cookId])) {
            json_out(['error' => 'Можно применить не более одного промокода одного повара'], 422);
        }
        if ((int) $promo['is_global'] === 1) {
            $discountDishIdsByCook[$cookId] = null;
        } else {
            $scopeStmt = $pdo->prepare('SELECT dish_id FROM promocode_dishes WHERE promocode_id = ?');
            $scopeStmt->execute([(int) $promo['id']]);
            $allowedDishIds = array_fill_keys(array_map('intval', $scopeStmt->fetchAll(PDO::FETCH_COLUMN)), true);
            $eligibleDishIds = [];
            foreach ($dishesById as $dishId => $dish) {
                if ((int) $dish['seller_id'] === $cookId && isset($allowedDishIds[(int) $dishId])) {
                    $eligibleDishIds[] = (int) $dishId;
                }
            }
            if (!$eligibleDishIds) {
                json_out(['error' => 'Промокод не подходит к блюдам в корзине.'], 422);
            }
            $discountDishIdsByCook[$cookId] = array_fill_keys($eligibleDishIds, true);
        }
        $discountPercentByCook[$cookId] = (int) $promo['discount_percent'];
        $promoIdByCook[$cookId] = (int) $promo['id'];
        $promoCodeByCook[$cookId] = $code;
    }

    $lineTotalsByDish = [];
    foreach ($dishesById as $dishId => $dish) {
        $lineTotalsByDish[$dishId] = $dish['line_subtotal'];
    }
    $discountTotal = 0.0;
    foreach ($discountPercentByCook as $cookId => $percent) {
        $eligibleDishIds = [];
        foreach ($dishesById as $dishId => $dish) {
            if ((int) $dish['seller_id'] === (int) $cookId
                && ($discountDishIdsByCook[$cookId] === null || isset($discountDishIdsByCook[$cookId][(int) $dishId]))) {
                $eligibleDishIds[] = (int) $dishId;
            }
        }
        $lineSubtotals = array_map(
            static fn (int $dishId): float => (float) $dishesById[$dishId]['line_subtotal'],
            $eligibleDishIds
        );
        $allocation = promocode_discount_lines($lineSubtotals, $percent);
        $discountTotal += $allocation['discount'];
        foreach ($eligibleDishIds as $index => $dishId) {
            $lineTotalsByDish[$dishId] = $allocation['line_totals'][$index];
        }
    }

    $insert = $pdo->prepare(
        "INSERT INTO orders (buyer_id, seller_id, dish_id, quantity, total_price, status, payment_method, delivery_address)
         VALUES (?, ?, ?, ?, ?, 'new', ?, ?)"
    );
    foreach ($dishesById as $dishId => $dish) {
        $insert->execute([
            $userId,
            (int) $dish['seller_id'],
            $dishId,
            (int) $dish['quantity'],
            (float) $lineTotalsByDish[$dishId],
            $paymentMethod,
            $deliveryAddress,
        ]);
    }

    $decrementInventory = $pdo->prepare(
        'UPDATE dish_inventory SET remaining_count = remaining_count - ?
         WHERE dish_id = ? AND remaining_count >= ?'
    );
    foreach ($dishesById as $dishId => $dish) {
        if ($dish['remaining_count'] === null) {
            continue;
        }
        $quantity = (int) $dish['quantity'];
        $decrementInventory->execute([$quantity, $dishId, $quantity]);
        if ($decrementInventory->rowCount() !== 1) {
            throw new RuntimeException('Dish stock changed while the order was being created');
        }
    }

    $pdo->commit();
    json_out([
        'success' => true,
        'message' => 'Заказ оформлен',
        'discount_amount' => round($discountTotal, 2),
        'applied_promocodes' => array_values(array_map(
            static fn (int $cookId, int $percent): array => [
                'id' => $promoIdByCook[$cookId],
                'code' => $promoCodeByCook[$cookId],
                'cook_id' => $cookId,
                'discount_percent' => $percent,
            ],
            array_map('intval', array_keys($discountPercentByCook)),
            array_values($discountPercentByCook)
        )),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Create order failed: ' . $e->getMessage());
    json_out(['error' => 'Ошибка оформления заказа'], 500);
}
