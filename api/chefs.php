<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$search = mb_substr($search, 0, 100);
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));
$sellerId = max(0, (int) ($_GET['seller_id'] ?? 0));
$page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 10)));
$where = 'u.is_seller = 1';
$params = [];
if ($sellerId > 0) { $where .= ' AND u.id = ?'; $params[] = $sellerId; }
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.seller_description LIKE ? OR u.city LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryId > 0) {
    $where .= ' AND EXISTS (SELECT 1 FROM dishes cd WHERE cd.user_id = u.id AND cd.is_available = 1 AND cd.category_id = ?)';
    $params[] = $categoryId;
}
try {
    $count = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = (int) ceil($total / $limit);
    $page = min($page, max(1, $pages));
    // Только публичные данные: без телефона, email, адреса и координат.
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.city, u.country,
                LEFT(u.seller_description, 500) AS description,
                (SELECT COUNT(*) FROM dishes d WHERE d.user_id = u.id AND d.is_available = 1) AS dish_count
         FROM users u WHERE $where ORDER BY u.name ASC, u.id ASC LIMIT ? OFFSET ?"
    );
    $index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($index, ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    json_out(['success' => true, 'chefs' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page]);
} catch (Throwable $error) {
    error_log('Chefs listing failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось загрузить поваров. Попробуйте ещё раз.'], 500);
}
