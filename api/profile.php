<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$user = current_user($pdo);

if (!$user) {
    json_out(['error' => 'Пользователь не найден'], 404);
}

json_out(['success' => true, 'user' => [
    'id' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? '',
    'country' => $user['country'] ?? 'Россия',
    'city' => $user['city'] ?? '',
    'address' => $user['address'] ?? '',
    'lat' => $user['lat'] ?? '',
    'lng' => $user['lng'] ?? '',
    'search_radius' => $user['search_radius'] ?? 10,
    'seller_description' => $user['seller_description'] ?? '',
    'lang' => $user['lang'] ?? 'ru',
    'is_seller' => (int) ($user['is_seller'] ?? 1),
]]);
