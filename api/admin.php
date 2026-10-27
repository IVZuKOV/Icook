<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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
            'SELECT d.id, d.title, d.price, d.weight, d.is_available, d.created_at,
                    u.name as seller_name, c.name as category_name
             FROM dishes d
             LEFT JOIN users u ON d.user_id = u.id
             LEFT JOIN categories c ON d.category_id = c.id
             ORDER BY d.id DESC'
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
        foreach (['users' => 'users', 'dishes' => 'dishes', 'orders' => 'orders', 'categories' => 'categories'] as $key => $table) {
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
        $fields = [];
        $params = [];
        foreach (['title', 'price', 'weight', 'is_available', 'category_id'] as $f) {
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
