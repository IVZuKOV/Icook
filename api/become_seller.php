<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$phone = trim((string) ($data['phone'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));

if ($phone === '') {
    json_out(['error' => 'Укажите телефон']);
}
if (mb_strlen($phone) > 30 || mb_strlen($address) > 500 || mb_strlen($description) > 2000) {
    json_out(['error' => 'Слишком длинное значение поля']);
}

$stmt = $pdo->prepare(
    'UPDATE users SET is_seller = 1, phone = ?, address = ?, seller_description = ? WHERE id = ?'
);
$stmt->execute([$phone, $address, $description, $userId]);

json_out(['success' => true, 'message' => 'Вы теперь продавец!']);
