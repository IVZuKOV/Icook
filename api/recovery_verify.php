<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$email = mb_strtolower(trim((string) ($data['email'] ?? '')));
$code = trim((string) ($data['code'] ?? ''));

if ($email === '' || !valid_email($email)) {
    json_out(['error' => 'Некорректный email'], 422);
}
if (!preg_match('/^[0-9]{4}$/', $code)) {
    json_out(['error' => 'Введите 4-значный код'], 422);
}

if (!rate_limit($pdo, 'recovery-verify-ip:' . client_ip(), 30, 900)
    || !rate_limit($pdo, 'recovery-verify:' . $email, 10, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare("SELECT id FROM verify_codes
    WHERE identifier = ? AND code = ? AND type = 'email' AND used = 0 AND expires_at > NOW()
    ORDER BY id DESC LIMIT 1");
$stmt->execute([$email, $code]);
$rec = $stmt->fetch();
if (!$rec) {
    json_out(['error' => 'Неверный или просроченный код'], 401);
}

$pdo->prepare('UPDATE verify_codes SET used = 1 WHERE id = ?')->execute([(int) $rec['id']]);

$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE LOWER(email) = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    json_out(['error' => 'Аккаунт с таким email не найден'], 404);
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expires = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');
$pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
    ->execute([$tokenHash, $expires, (int) $user['id']]);

json_out([
    'success' => true,
    'token' => $token,
    'name' => (string) $user['name'],
    'email' => (string) $user['email'],
]);
