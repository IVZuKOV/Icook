<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();
$password = (string) ($data['password'] ?? '');

if ($password === '') {
    json_out(['error' => 'Введите пароль']);
}
if (!rate_limit($pdo, 'delete-account:' . $userId . ':' . client_ip(), 5, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    json_out(['error' => 'Пользователь не найден'], 404);
}
if (!password_verify($password, (string) $user['password'])) {
    json_out(['error' => 'Неверный пароль'], 403);
}

$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

$_SESSION = [];
session_destroy();

json_out(['success' => true, 'message' => 'Аккаунт удалён']);
