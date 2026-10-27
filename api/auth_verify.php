<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$email = strtolower(trim((string)($data['email'] ?? '')));
$code = trim((string)($data['code'] ?? ''));
$password = (string)($data['password'] ?? '');
$confirm = (string)($data['confirm'] ?? '');

if ($email === '' || !valid_email($email)) {
    json_out(['error' => 'Некорректный email']);
}
if (!preg_match('/^[0-9]{4}$/', $code)) {
    json_out(['error' => 'Неверный код']);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов']);
}
if ($password !== $confirm) {
    json_out(['error' => 'Пароли не совпадают']);
}
if (!rate_limit($pdo, 'authverify-ip:' . client_ip(), 15, 900)
    || !rate_limit($pdo, 'authverify-email:' . $email, 8, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT id FROM verify_codes WHERE identifier = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
$stmt->execute([$email, $code, 'email']);
$rec = $stmt->fetch();
if (!$rec) {
    json_out(['error' => 'Неверный или просроченный код']);
}
$pdo->prepare('UPDATE verify_codes SET used = 1 WHERE id = ?')->execute([$rec['id']]);

$stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($user) {
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, (int)$user['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $email;
    json_out(['success' => true, 'mode' => 'login', 'name' => $user['name'], 'email' => $email]);
    exit;
} else {
    $name = explode('@', $email)[0];
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, is_seller, role) VALUES (?, ?, ?, 1, ?)');
    $stmt->execute([$name, $email, $hash, 'user']);
    $uid = (int)$pdo->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    json_out(['success' => true, 'mode' => 'register', 'name' => $name, 'email' => $email]);
    exit;
}
