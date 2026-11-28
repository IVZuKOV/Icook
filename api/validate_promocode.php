<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/promocodes.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$data = body_json();
try {
    $code = normalize_promocode((string) ($data['code'] ?? ''));
} catch (InvalidArgumentException $error) {
    json_out(['success' => false, 'error' => $error->getMessage()], 400);
}
$items = $data['items'] ?? [];
if (!is_array($items) || count($items) < 1 || count($items) > 50) {
    json_out(['success' => false, 'error' => 'В корзине нет подходящих блюд'], 400);
}

$qtyByDish = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $dishId = (int) ($item['id'] ?? 0);
    if ($dishId > 0) {
        $qtyByDish[$dishId] = max(1, min(50, (int) ($qtyByDish[$dishId] ?? 0) + (int) ($item['qty'] ?? 1)));
    }
}
if (!$qtyByDish) {
    json_out(['success' => false, 'error' => 'В корзине нет подходящих блюд'], 400);
}

$placeholders = implode(',', array_fill(0, count($qtyByDish), '?'));
$dishStmt = $pdo->prepare(
    "SELECT d.id, d.user_id AS cook_id, d.price, d.discount_percent, u.name AS cook_name
     FROM dishes d JOIN users u ON u.id = d.user_id
     WHERE d.id IN ($placeholders) AND d.is_available = 1"
);
$dishStmt->execute(array_keys($qtyByDish));
$sellerTotals = [];
$cartDishesById = [];
$sellerNames = [];
foreach ($dishStmt->fetchAll() as $dish) {
    $cookId = (int) $dish['cook_id'];
    $dishId = (int) $dish['id'];
    $salePrice = round((float) $dish['price'] * (1 - (int) $dish['discount_percent'] / 100), 2);
    $cartDishesById[$dishId] = [
        'cook_id' => $cookId,
        'subtotal' => round($salePrice * $qtyByDish[$dishId], 2),
    ];
    $sellerTotals[$cookId] = ($sellerTotals[$cookId] ?? 0) + $cartDishesById[$dishId]['subtotal'];
    $sellerNames[$cookId] = (string) $dish['cook_name'];
}
if (!$sellerTotals) {
    json_out(['success' => false, 'error' => 'Блюда из корзины недоступны'], 400);
}

$sellerIds = array_keys($sellerTotals);
$sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
$stmt = $pdo->prepare(
    "SELECT id, cook_id, code, discount_percent, is_global
     FROM promocodes
     WHERE code = ? AND cook_id IN ($sellerPlaceholders) AND is_active = 1"
);
$stmt->execute(array_merge([$code], $sellerIds));
$matches = $stmt->fetchAll();
if (count($matches) !== 1) {
    $message = count($matches) > 1
        ? 'Этот код подходит нескольким поварам в корзине. Попросите у повара уникальный код.'
        : 'Промокод не найден или в корзине нет блюд этого повара.';
    json_out(['success' => false, 'error' => $message], 422);
}

$promo = $matches[0];
$cookId = (int) $promo['cook_id'];
if (!promocode_belongs_to_cook($promo, $cookId)) {
    json_out(['success' => false, 'error' => 'Промокод не подходит к товарам в корзине'], 422);
}

$isGlobal = (int) $promo['is_global'] === 1;
if ($isGlobal) {
    $eligibleDishIds = array_keys(array_filter(
        $cartDishesById,
        static fn (array $dish): bool => $dish['cook_id'] === $cookId
    ));
} else {
    $scopeStmt = $pdo->prepare('SELECT dish_id FROM promocode_dishes WHERE promocode_id = ?');
    $scopeStmt->execute([(int) $promo['id']]);
    $allowedDishIds = array_fill_keys(array_map('intval', $scopeStmt->fetchAll(PDO::FETCH_COLUMN)), true);
    $eligibleDishIds = array_keys(array_filter(
        $cartDishesById,
        static fn (array $dish, int $dishId): bool => $dish['cook_id'] === $cookId && isset($allowedDishIds[$dishId]),
        ARRAY_FILTER_USE_BOTH
    ));
    if (!$eligibleDishIds) {
        json_out(['success' => false, 'error' => 'Промокод не подходит к блюдам в корзине.'], 422);
    }
}
$subtotal = 0.0;
foreach ($eligibleDishIds as $eligibleDishId) {
    $subtotal += $cartDishesById[$eligibleDishId]['subtotal'];
}
$subtotal = round($subtotal, 2);
$percent = (int) $promo['discount_percent'];
json_out([
    'success' => true,
    'promocode' => [
        'id' => (int) $promo['id'],
        'code' => (string) $promo['code'],
        'cook_id' => $cookId,
        'cook_name' => $sellerNames[$cookId] ?? 'Повар',
        'discount_percent' => $percent,
        'is_global' => $isGlobal,
        'subtotal' => $subtotal,
        'discount_amount' => promocode_discount_amount($subtotal, $percent),
        'eligible_dish_ids' => array_values($eligibleDishIds),
    ],
]);
