<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/dish_ingredients.php';
require_once __DIR__ . '/lib/default_dish_images.php';

$dishId = (int) ($_GET['id'] ?? 0);
if ($dishId <= 0) json_out(['success' => false, 'error' => 'Блюдо не найдено'], 404);

$stmt = $pdo->prepare(
    "SELECT d.*, u.name AS seller_name, u.created_at AS seller_created_at,
            CASE WHEN u.avatar_public = 1 THEN u.avatar ELSE '' END AS seller_avatar,
            CASE WHEN u.avatar_public = 1 THEN u.avatar_version ELSE '' END AS seller_avatar_version,
            CASE WHEN u.avatar_public = 1 THEN u.avatar_crop ELSE NULL END AS seller_avatar_crop,
            c.name AS category_name
     FROM dishes d JOIN users u ON u.id = d.user_id
     LEFT JOIN categories c ON c.id = d.category_id
     WHERE d.id = ? AND (d.is_available = 1 OR d.user_id = ?)"
);
$stmt->execute([$dishId, (int) ($_SESSION['user_id'] ?? 0)]);
$dish = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dish) json_out(['success' => false, 'error' => 'Блюдо не найдено или больше недоступно'], 404);

$dish = apply_default_dish_images([$dish])[0];
$ingredients = dish_ingredients_group($pdo, [$dishId])[$dishId] ?? [];
$dish['nutrition'] = $ingredients['_nutrition'] ?? null;
unset($ingredients['_nutrition']);
$dish['ingredients'] = $ingredients;

$reviewsStmt = $pdo->prepare(
    "SELECT r.rating, r.review_text, r.created_at, u.name AS buyer_name
     FROM dish_reviews r JOIN users u ON u.id = r.user_id
     WHERE r.dish_id = ? ORDER BY r.created_at DESC LIMIT 100"
);
$reviewsStmt->execute([$dishId]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
$orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'delivered'");
$orders->execute([(int) $dish['user_id']]);
$sellerOrders = (int) $orders->fetchColumn();
$sellerReviews = $pdo->prepare('SELECT COUNT(*) AS total, AVG(r.rating) AS average FROM dish_reviews r JOIN dishes d ON d.id = r.dish_id WHERE d.user_id = ?');
$sellerReviews->execute([(int) $dish['user_id']]);
$sellerRating = $sellerReviews->fetch(PDO::FETCH_ASSOC);

$similarStmt = $pdo->prepare(
    "SELECT DISTINCT d.*, u.name AS seller_name, c.name AS category_name
     FROM dishes d
     LEFT JOIN users u ON u.id = d.user_id
     LEFT JOIN categories c ON c.id = d.category_id
     LEFT JOIN dish_ingredients i ON i.dish_id = d.id
     WHERE d.is_available = 1 AND d.id <> ? AND
       (d.title LIKE ? OR i.ingredient_name IN
         (SELECT ingredient_name FROM dish_ingredients WHERE dish_id = ?))
     ORDER BY d.created_at DESC LIMIT 100"
);
$similarStmt->execute([$dishId, '%' . (string) $dish['title'] . '%', $dishId]);
$similar = apply_default_dish_images($similarStmt->fetchAll(PDO::FETCH_ASSOC));
$chefStmt = $pdo->prepare(
    'SELECT id, title, image, discount_percent FROM dishes WHERE user_id = ? AND is_available = 1 AND id <> ? ORDER BY created_at DESC'
);
$chefStmt->execute([(int) $dish['user_id'], $dishId]);
$chefDishes = apply_default_dish_images($chefStmt->fetchAll(PDO::FETCH_ASSOC));

json_out([
    'success' => true,
    'dish' => $dish,
    'reviews' => $reviews,
    'seller' => [
        'id' => (int) $dish['user_id'],
        'name' => $dish['seller_name'],
        'created_at' => $dish['seller_created_at'],
        'rating' => (float) ($sellerRating['average'] ?? 0),
        'reviews_count' => (int) ($sellerRating['total'] ?? 0),
        'orders_count' => $sellerOrders,
    ],
    'similar' => $similar,
    'chef_dishes' => $chefDishes,
]);
