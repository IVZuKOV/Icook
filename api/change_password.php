<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

$oldPassword = (string) ($data['old_password'] ?? '');
$newPassword = (string) ($data['new_password'] ?? '');

if (strlen($newPassword) < 8 || strlen($newPassword) > 200) {
    json_out(['error' => 'Новый пароль должен быть от 8 до 200 символов']);
}
if (!rate_limit($pdo, 'change-password:' . $userId . ':' . client_ip(), 10, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    json_out(['error' => 'Пользователь не найден'], 404);
}
if (!password_verify($oldPassword, (string) $user['password'])) {
    json_out(['error' => 'Неверный текущий пароль'], 403);
}

$pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
    ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

session_regenerate_id(true);

json_out(['success' => true, 'message' => 'Пароль успешно изменён']);
