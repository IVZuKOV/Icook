<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/identifier.php';

$data = body_json();
$challenge = trim((string) ($data['challenge'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirm = (string) ($data['confirm'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/D', $challenge)) {
    json_out(['error' => 'Подтверждение недействительно'], 422);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов'], 422);
}
if ($password !== $confirm) {
    json_out(['error' => 'Пароли не совпадают'], 422);
}

if (!rate_limit($pdo, 'recovery-sms-ip:' . client_ip(), 20, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$challengeHash = hash('sha256', $challenge);
$pdo->beginTransaction();

$stmt = $pdo->prepare("SELECT id, phone, status, expires_at FROM sms_login_challenges
    WHERE challenge_hash = ? LIMIT 1 FOR UPDATE");
$stmt->execute([$challengeHash]);
$row = $stmt->fetch();

if (!$row || $row['status'] !== 'verified' || strtotime((string) $row['expires_at']) <= time()) {
    $pdo->rollBack();
    json_out(['error' => 'Подтверждение недействительно или истекло'], 403);
}

[$kind, $users] = iks_find_users_by_identifier($pdo, (string) $row['phone'], 5);
if (!$users) {
    $pdo->rollBack();
    json_out(['error' => 'Аккаунт с таким телефоном не найден'], 404);
}
if (count($users) > 1) {
    $pdo->rollBack();
    json_out(['error' => 'Этот телефон привязан к нескольким аккаунтам. Воспользуйтесь email'], 409);
}
$user = $users[0];

$pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
$pdo->prepare("UPDATE sms_login_challenges SET status = 'used', used_at = NOW() WHERE id = ?")
    ->execute([(int) $row['id']]);
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
