<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$dishId = (int) ($_GET['dish_id'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT r.rating, r.review_text, r.created_at, u.name AS buyer_name
         FROM dish_reviews r JOIN users u ON u.id = r.user_id
         WHERE r.dish_id = ? ORDER BY r.created_at DESC LIMIT 100'
    );
    $stmt->execute([$dishId]);
    json_out(['success' => true, 'reviews' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

$userId = require_login();
$data = body_json();
$rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
$text = trim((string) ($data['review_text'] ?? ''));
if ($dishId <= 0 || $rating === false || $rating < 1 || $rating > 5 || mb_strlen($text) < 10 || mb_strlen($text) > 2000) {
    json_out(['success' => false, 'error' => 'Укажите оценку и отзыв от 10 до 2000 символов']);
}
$order = $pdo->prepare(
    "SELECT o.id FROM orders o JOIN dishes d ON d.id = o.dish_id
     WHERE o.dish_id = ? AND o.buyer_id = ? AND o.status = 'delivered' LIMIT 1"
);
$order->execute([$dishId, $userId]);
$orderId = $order->fetchColumn();
if (!$orderId) json_out(['success' => false, 'error' => 'Оставить отзыв могут только покупатели, получившие блюдо'], 403);
try {
    $pdo->prepare('INSERT INTO dish_reviews (dish_id, order_id, user_id, rating, review_text) VALUES (?, ?, ?, ?, ?)')
        ->execute([$dishId, $orderId, $userId, $rating, $text]);
} catch (PDOException $error) {
    if ((string) $error->getCode() === '23000') json_out(['success' => false, 'error' => 'Вы уже оставили отзыв на это блюдо'], 409);
    throw $error;
}
json_out(['success' => true]);
