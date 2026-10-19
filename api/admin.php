<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

// Проверка роли админа
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Нет доступа']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // === ПОЛУЧЕНИЕ ДАННЫХ ===
    case 'users':
        $stmt = $pdo->query("SELECT id, name, email, phone, address, country, city, lat, lng, search_radius, role, is_seller, seller_description, created_at FROM users ORDER BY id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'dishes':
        $stmt = $pdo->query("SELECT d.id, d.title, d.price, d.weight, d.is_available, d.created_at, u.name as seller_name, c.name as category_name 
            FROM dishes d LEFT JOIN users u ON d.user_id = u.id LEFT JOIN categories c ON d.category_id = c.id ORDER BY d.id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'orders':
        $stmt = $pdo->query("SELECT o.id, o.status, o.total_price as total, o.created_at, u.name as buyer_name, s.name as seller_name 
            FROM orders o LEFT JOIN users u ON o.buyer_id = u.id LEFT JOIN users s ON o.seller_id = s.id ORDER BY o.id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'categories':
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order, id");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'stats':
        $stats = [];
        foreach (['users' => 'users', 'dishes' => 'dishes', 'orders' => 'orders', 'categories' => 'categories'] as $key => $table) {
            $stats[$key] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        }
        try {
            $stats['reviews_total'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
            $stats['reviews_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
            $stats['reviews_approved'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn();
            $stats['reviews_rejected'] = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'rejected'")->fetchColumn();
        } catch (PDOException $e) {
            $stats['reviews_total'] = 0;
            $stats['reviews_pending'] = 0;
            $stats['reviews_approved'] = 0;
            $stats['reviews_rejected'] = 0;
        }
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    // === РЕДАКТИРОВАНИЕ ===
    case 'update_user':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }
        $fields = []; $params = [];
        foreach (['name','email','phone','address','country','city','lat','lng','search_radius','role','is_seller','seller_description'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        // Если передан новый пароль — хешируем
        if (!empty($data['new_password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['new_password'], PASSWORD_BCRYPT);
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        echo json_encode(['success' => true]);
        break;

    case 'update_dish':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }
        $fields = []; $params = [];
        foreach (['title','price','weight','is_available','category_id'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare("UPDATE dishes SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_dish':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM dishes WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'update_order_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $status = $data['status'] ?? '';
        if ($id && $status) {
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
        }
        echo json_encode(['success' => true]);
        break;


    case 'view_user':
        $uid = intval($_GET['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }
        // Данные пользователя
        $stmt = $pdo->prepare("SELECT id, name, email, phone, address, country, city, lat, lng, search_radius, role, is_seller, seller_description, created_at FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { echo json_encode(['success' => false, 'error' => 'Пользователь не найден']); break; }
        // Блюда пользователя
        $stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM dishes d LEFT JOIN categories c ON d.category_id = c.id WHERE d.user_id = ? ORDER BY d.id DESC");
        $stmt->execute([$uid]);
        $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Заказы покупателя
        $stmt = $pdo->prepare("SELECT o.*, s.name as seller_name FROM orders o LEFT JOIN users s ON o.seller_id = s.id WHERE o.buyer_id = ? ORDER BY o.id DESC");
        $stmt->execute([$uid]);
        $buyer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Заказы продавца
        $stmt = $pdo->prepare("SELECT o.*, b.name as buyer_name FROM orders o LEFT JOIN users b ON o.buyer_id = b.id WHERE o.seller_id = ? ORDER BY o.id DESC");
        $stmt->execute([$uid]);
        $seller_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'user' => $user, 'dishes' => $dishes, 'buyer_orders' => $buyer_orders, 'seller_orders' => $seller_orders]);
        break;


    case 'update_user_dish':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $uid = intval($data['user_id'] ?? 0);
        if (!$id || !$uid) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }
        // Проверяем что блюдо принадлежит этому пользователю
        $check = $pdo->prepare("SELECT id FROM dishes WHERE id = ? AND user_id = ?");
        $check->execute([$id, $uid]);
        if (!$check->fetch()) { echo json_encode(['success' => false, 'error' => 'Блюдо не найдено']); break; }
        $fields = []; $params = [];
        foreach (['title','description','price','weight','is_available','category_id'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare("UPDATE dishes SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_user_dish':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $uid = intval($data['user_id'] ?? 0);
        if ($id && $uid) {
            $pdo->prepare("DELETE FROM dishes WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_all_user_dishes':
        $data = json_decode(file_get_contents('php://input'), true);
        $uid = intval($data['user_id'] ?? 0);
        if ($uid) {
            $pdo->prepare("DELETE FROM dishes WHERE user_id = ?")->execute([$uid]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'update_user_order':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $uid = intval($data['user_id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }
        $fields = []; $params = [];
        foreach (['status','total_price','comment'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if ($fields) {
            $params[] = $id;
            $pdo->prepare("UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_user_order':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_all_user_orders':
        $data = json_decode(file_get_contents('php://input'), true);
        $uid = intval($data['user_id'] ?? 0);
        $type = $data['type'] ?? 'buyer'; // buyer или seller
        if ($uid) {
            if ($type === 'seller') {
                $pdo->prepare("DELETE FROM orders WHERE seller_id = ?")->execute([$uid]);
            } else {
                $pdo->prepare("DELETE FROM orders WHERE buyer_id = ?")->execute([$uid]);
            }
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
?>
