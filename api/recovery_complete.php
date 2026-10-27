<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirm = (string) ($data['confirm'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
    json_out(['error' => 'Ссылка восстановления недействительна'], 422);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов'], 422);
}
if ($password !== $confirm) {
    json_out(['error' => 'Пароли не совпадают'], 422);
}

if (!rate_limit($pdo, 'recovery-complete-ip:' . client_ip(), 20, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$tokenHash = hash('sha256', $token);
$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id, name, email FROM users
    WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1 FOR UPDATE');
$stmt->execute([$tokenHash]);
$user = $stmt->fetch();

if (!$user) {
    $pdo->rollBack();
    json_out(['error' => 'Срок восстановления истёк. Запросите код заново'], 403);
}

$pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
$pdo->commit();

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = (string) $user['name'];
$_SESSION['user_email'] = $user['email'] !== null ? (string) $user['email'] : null;

json_out([
    'success' => true,
    'name' => (string) $user['name'],
    'email' => $user['email'] !== null ? (string) $user['email'] : null,
]);
