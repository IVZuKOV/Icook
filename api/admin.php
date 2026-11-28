<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/category_path.php';
require_once __DIR__ . '/lib/dish_ingredients.php';
require_once __DIR__ . '/lib/ingredients.php';
require_once __DIR__ . '/lib/promocodes.php';

$admin = require_admin($pdo);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

$allowedRoles = ['user', 'admin', 'superuser'];
$allowedOrderStatuses = ['new', 'pending', 'confirmed', 'cooking', 'ready', 'delivered', 'completed', 'cancelled'];

switch ($action) {
    case 'users':
        $stmt = $pdo->query(
            'SELECT id, name, email, phone, address, country, city, lat, lng, search_radius, role, is_seller,
                    seller_description, created_at
             FROM users ORDER BY id DESC'
        );
        json_out(['success' => true, 'data' => $stmt->fetchAll()]);

    case 'dishes':
        $stmt = $pdo->query(
            'SELECT d.id, d.title, d.description, d.price, d.weight, d.discount_percent, d.is_available, d.category_id, d.created_at,
                    (SELECT GROUP_CONCAT(CONCAT(p.code, \' (\', p.discount_percent, \'%)\') ORDER BY p.code SEPARATOR \', \')
                     FROM promocodes p WHERE p.cook_id = d.user_id AND p.is_active = 1) AS promocodes,
                    (SELECT GROUP_CONCAT(CONCAT(i.ingredient_name, \' (\', i.amount_grams, \' г; \', i.energy_kj_per_100g, \' кДж/100 г)\') ORDER BY i.position SEPARATOR \', \')
                     FROM dish_ingredients i WHERE i.dish_id = d.id) AS ingredients,
                    u.name as seller_name, c.name as category_name
             FROM dishes d
             LEFT JOIN users u ON d.user_id = u.id
             LEFT JOIN categories c ON d.category_id = c.id
             ORDER BY d.id DESC'
        );
        json_out(['success' => true, 'data' => $stmt->fetchAll()]);

    case 'dish_details':
        $dishId = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM dishes WHERE id = ?');
        $stmt->execute([$dishId]);
        $dish = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dish) {
            json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);
        }
        $categoryPath = [];
        $categoryId = (int) ($dish['category_id'] ?? 0);
        $categoryLookup = $pdo->prepare('SELECT parent_id FROM categories WHERE id = ?');
        while ($categoryId > 0 && count($categoryPath) < 3) {
            $categoryLookup->execute([$categoryId]);
            $parentId = $categoryLookup->fetchColumn();
            if ($parentId === false) {
                break;
            }
            array_unshift($categoryPath, $categoryId);
            $categoryId = $parentId === null ? 0 : (int) $parentId;
        }
        $dish['main_category_id'] = $categoryPath[0] ?? null;
        $dish['subcategory_id'] = $categoryPath[1] ?? null;
        $dish['subsubcategory_id'] = $categoryPath[2] ?? null;
        $ingredients = $pdo->prepare(
            'SELECT ingredient_name AS name, amount_grams, energy_kj_per_100g
             FROM dish_ingredients WHERE dish_id = ? ORDER BY position, id'
        );
        $ingredients->execute([$dishId]);
        $dish['ingredients'] = $ingredients->fetchAll(PDO::FETCH_ASSOC);
        $promocodes = $pdo->prepare(
            'SELECT id, code, discount_percent FROM promocodes
             WHERE cook_id = ? AND is_active = 1 ORDER BY code, id'
        );
        $promocodes->execute([(int) $dish['user_id']]);
        $dish['promocodes'] = $promocodes->fetchAll(PDO::FETCH_ASSOC);
        json_out(['success' => true, 'dish' => $dish]);

    case 'dish_promocodes':
        $data = body_json();
        $dishId = (int) ($_GET['dish_id'] ?? $data['dish_id'] ?? 0);
        $owner = $pdo->prepare('SELECT user_id FROM dishes WHERE id = ?');
        $owner->execute([$dishId]);
        $cookId = (int) $owner->fetchColumn();
        if ($dishId <= 0 || $cookId <= 0) {
            json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $stmt = $pdo->prepare(
                'SELECT id, code, discount_percent FROM promocodes
                 WHERE cook_id = ? AND is_active = 1 ORDER BY code, id'
            );
            $stmt->execute([$cookId]);
            json_out(['success' => true, 'promocodes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        $promoAction = (string) ($data['action'] ?? '');
        $promoId = (int) ($data['id'] ?? 0);
        if ($promoAction === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM promocodes WHERE id = ? AND cook_id = ?');
            $stmt->execute([$promoId, $cookId]);
            if ($stmt->rowCount() < 1) {
                json_out(['success' => false, 'error' => 'Промокод не найден'], 404);
            }
            json_out(['success' => true]);
        }
        if (!in_array($promoAction, ['create', 'update'], true)) {
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
            if ($promoAction === 'create') {
                $stmt = $pdo->prepare('INSERT INTO promocodes (cook_id, code, discount_percent) VALUES (?, ?, ?)');
                $stmt->execute([$cookId, $code, $percent]);
                $promoId = (int) $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE promocodes SET code = ?, discount_percent = ?
                     WHERE id = ? AND cook_id = ? AND is_active = 1'
                );
                $stmt->execute([$code, $percent, $promoId, $cookId]);
                if ($stmt->rowCount() < 1) {
                    $check = $pdo->prepare('SELECT id FROM promocodes WHERE id = ? AND cook_id = ? AND is_active = 1');
                    $check->execute([$promoId, $cookId]);
                    if (!$check->fetchColumn()) {
                        json_out(['success' => false, 'error' => 'Промокод не найден'], 404);
                    }
                }
            }
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                json_out(['success' => false, 'error' => 'У повара уже есть такой промокод'], 409);
            }
            throw $error;
        }
        json_out(['success' => true, 'promocode' => ['id' => $promoId, 'code' => $code, 'discount_percent' => $percent]]);

    case 'recipes':
        $stmt = $pdo->query(
            'SELECT r.id, r.title, r.is_available, r.created_at, u.name AS author_name
             FROM recipes r
             LEFT JOIN users u ON r.user_id = u.id
             ORDER BY r.id DESC'
        );
        json_out(['success' => true, 'data' => $stmt->fetchAll()]);

    case 'orders':
        $stmt = $pdo->query(
            'SELECT o.id, o.status, o.total_price as total, o.created_at,
                    u.name as buyer_name, s.name as seller_name
             FROM orders o
             LEFT JOIN users u ON o.buyer_id = u.id
             LEFT JOIN users s ON o.seller_id = s.id
             ORDER BY o.id DESC'
        );
        json_out(['success' => true, 'data' => $stmt->fetchAll()]);

    case 'categories':
        $stmt = $pdo->query('SELECT * FROM categories ORDER BY sort_order, id');
        json_out(['success' => true, 'data' => $stmt->fetchAll()]);

    case 'stats':
        $stats = [];
        foreach (['users' => 'users', 'dishes' => 'dishes', 'recipes' => 'recipes', 'orders' => 'orders', 'categories' => 'categories'] as $key => $table) {
            $stats[$key] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        }
        try {
            $stats['reviews_total'] = (int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
            $stats['reviews_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
            $stats['reviews_approved'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn();
            $stats['reviews_rejected'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'rejected'")->fetchColumn();
        } catch (PDOException $e) {
            $stats['reviews_total'] = 0;
            $stats['reviews_pending'] = 0;
            $stats['reviews_approved'] = 0;
            $stats['reviews_rejected'] = 0;
        }
        json_out(['success' => true, 'data' => $stats]);

    case 'update_user':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            json_out(['success' => false, 'error' => 'Нет ID']);
        }

        $fields = [];
        $params = [];
        foreach (['name', 'email', 'phone', 'address', 'country', 'city', 'lat', 'lng', 'search_radius', 'seller_description'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (array_key_exists('role', $data)) {
            if (!in_array((string) $data['role'], $allowedRoles, true)) {
                json_out(['success' => false, 'error' => 'Некорректная роль']);
            }
            $fields[] = 'role = ?';
            $params[] = (string) $data['role'];
        }
        if (array_key_exists('is_seller', $data)) {
            $fields[] = 'is_seller = ?';
            $params[] = (int) $data['is_seller'] ? 1 : 0;
        }
        if (!empty($data['new_password'])) {
            $newPassword = (string) $data['new_password'];
            if (strlen($newPassword) < 8 || strlen($newPassword) > 200) {
                json_out(['success' => false, 'error' => 'Пароль должен быть от 8 до 200 символов']);
            }
            $fields[] = 'password = ?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }
        json_out(['success' => true]);

    case 'update_dish':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            json_out(['success' => false, 'error' => 'Нет ID']);
        }
        if (array_key_exists('ingredients', $data)) {
            $title = trim((string) ($data['title'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $price = (float) ($data['price'] ?? 0);
            $weight = trim((string) ($data['weight'] ?? ''));
            $discount = filter_var($data['discount_percent'] ?? 0, FILTER_VALIDATE_INT);
            $categoryId = resolve_dish_category($pdo, $data);
            try {
                $ingredients = dish_ingredients_parse($data['ingredients']);
            } catch (InvalidArgumentException $error) {
                json_out(['success' => false, 'error' => $error->getMessage()], 400);
            }
            if ($title === '' || mb_strlen($title) > 150) {
                json_out(['success' => false, 'error' => 'Некорректное название'], 400);
            }
            if (mb_strlen($description) > 3000 || mb_strlen($weight) > 50) {
                json_out(['success' => false, 'error' => 'Слишком длинное описание или вес'], 400);
            }
            if ($price <= 0 || $price > 10000000) {
                json_out(['success' => false, 'error' => 'Некорректная цена'], 400);
            }
            if ($categoryId === null) {
                json_out(['success' => false, 'error' => 'Выберите категорию и подкатегорию'], 400);
            }
            if ($discount === false || $discount < 0 || $discount > 90) {
                json_out(['success' => false, 'error' => 'Скидка должна быть от 0 до 90%'], 400);
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'UPDATE dishes SET title = ?, description = ?, price = ?, weight = ?, category_id = ?, discount_percent = ?
                     WHERE id = ?'
                )->execute([$title, $description, $price, $weight, $categoryId, $discount, $id]);
                dish_ingredients_replace($pdo, $id, $ingredients);
                dish_ingredients_catalog_sync($pdo, $ingredients);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
            json_out(['success' => true]);
        }
        $fields = [];
        $params = [];
        foreach (['title', 'description', 'price', 'weight', 'discount_percent', 'is_available', 'category_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare('UPDATE dishes SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }
        json_out(['success' => true]);

    case 'delete_dish':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM dishes WHERE id = ?')->execute([$id]);
        }
        json_out(['success' => true]);

    case 'update_recipe_status':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        $isAvailable = (int) ($data['is_available'] ?? -1);
        if ($id <= 0 || !in_array($isAvailable, [0, 1], true)) {
            json_out(['success' => false, 'error' => 'Некорректные данные']);
        }
        $stmt = $pdo->prepare('UPDATE recipes SET is_available = ? WHERE id = ?');
        $stmt->execute([$isAvailable, $id]);
        json_out(['success' => true]);

    case 'update_order_status':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        $status = (string) ($data['status'] ?? '');
        if (!$id || !in_array($status, $allowedOrderStatuses, true)) {
            json_out(['success' => false, 'error' => 'Некорректный статус']);
        }
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        json_out(['success' => true]);

    case 'view_user':
        $uid = (int) ($_GET['user_id'] ?? 0);
        if ($uid <= 0) {
            json_out(['success' => false, 'error' => 'Нет ID']);
        }
        $stmt = $pdo->prepare(
            'SELECT id, name, email, phone, address, country, city, lat, lng, search_radius, role, is_seller,
                    seller_description, created_at
             FROM users WHERE id = ?'
        );
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) {
            json_out(['success' => false, 'error' => 'Пользователь не найден'], 404);
        }

        $stmt = $pdo->prepare(
            'SELECT d.*, c.name as category_name FROM dishes d
             LEFT JOIN categories c ON d.category_id = c.id
             WHERE d.user_id = ? ORDER BY d.id DESC'
        );
        $stmt->execute([$uid]);
        $dishes = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT o.*, s.name as seller_name FROM orders o
             LEFT JOIN users s ON o.seller_id = s.id
             WHERE o.buyer_id = ? ORDER BY o.id DESC'
        );
        $stmt->execute([$uid]);
        $buyerOrders = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT o.*, b.name as buyer_name FROM orders o
             LEFT JOIN users b ON o.buyer_id = b.id
             WHERE o.seller_id = ? ORDER BY o.id DESC'
        );
        $stmt->execute([$uid]);
        $sellerOrders = $stmt->fetchAll();

        json_out([
            'success' => true,
            'user' => $user,
            'dishes' => $dishes,
            'buyer_orders' => $buyerOrders,
            'seller_orders' => $sellerOrders,
        ]);

    case 'update_user_dish':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        $uid = (int) ($data['user_id'] ?? 0);
        if ($id <= 0 || $uid <= 0) {
            json_out(['success' => false, 'error' => 'Нет ID']);
        }
        $check = $pdo->prepare('SELECT id FROM dishes WHERE id = ? AND user_id = ?');
        $check->execute([$id, $uid]);
        if (!$check->fetch()) {
            json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);
        }
        $fields = [];
        $params = [];
        foreach (['title', 'description', 'price', 'weight', 'is_available', 'category_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare('UPDATE dishes SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }
        json_out(['success' => true]);

    case 'delete_user_dish':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        $uid = (int) ($data['user_id'] ?? 0);
        if ($id > 0 && $uid > 0) {
            $pdo->prepare('DELETE FROM dishes WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        }
        json_out(['success' => true]);

    case 'delete_all_user_dishes':
        $data = body_json();
        $uid = (int) ($data['user_id'] ?? 0);
        if ($uid > 0) {
            $pdo->prepare('DELETE FROM dishes WHERE user_id = ?')->execute([$uid]);
        }
        json_out(['success' => true]);

    case 'update_user_order':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            json_out(['success' => false, 'error' => 'Нет ID']);
        }
        $fields = [];
        $params = [];
        foreach (['status', 'total_price', 'comment'] as $f) {
            if (array_key_exists($f, $data)) {
                if ($f === 'status' && !in_array((string) $data['status'], $allowedOrderStatuses, true)) {
                    json_out(['success' => false, 'error' => 'Некорректный статус']);
                }
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare('UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }
        json_out(['success' => true]);

    case 'delete_user_order':
        $data = body_json();
        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        }
        json_out(['success' => true]);

    case 'delete_all_user_orders':
        $data = body_json();
        $uid = (int) ($data['user_id'] ?? 0);
        $type = (string) ($data['type'] ?? 'buyer');
        if ($uid > 0) {
            if ($type === 'seller') {
                $pdo->prepare('DELETE FROM orders WHERE seller_id = ?')->execute([$uid]);
            } else {
                $pdo->prepare('DELETE FROM orders WHERE buyer_id = ?')->execute([$uid]);
            }
        }
        json_out(['success' => true]);

    default:
        json_out(['success' => false, 'error' => 'Неизвестное действие'], 400);
}
