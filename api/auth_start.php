<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$email = strtolower(trim((string)($data['email'] ?? '')));

if ($email === '' || !valid_email($email)) {
    json_out(['error' => 'Укажите корректный email']);
}

if (!rate_limit($pdo, 'authstart-ip:' . client_ip(), 10, 900)
    || !rate_limit($pdo, 'authstart-email:' . $email, 5, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
$exists = (bool)$user;

$pdo->exec('DELETE FROM verify_codes WHERE expires_at < NOW()');
$pdo->prepare('UPDATE verify_codes SET used = 1 WHERE identifier = ? AND type = ?')->execute([$email, 'email']);

$code = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$expires = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
$stmt = $pdo->prepare('INSERT INTO verify_codes (identifier, code, type, expires_at) VALUES (?, ?, ?, ?)');
$stmt->execute([$email, $code, 'email', $expires]);

$subject = 'Код для входа — Я готовлю';
$message = "Код для входа в личный кабинет: $code\n\nКод действителен 10 минут. Если вы не запрашивали его — проигнорируйте письмо.";
$headers = "From: noreply@icook.com.ru\r\nContent-Type: text/plain; charset=utf-8";

@mail($email, $subject, $message, $headers);

json_out(['success' => true, 'mode' => $exists ? 'login' : 'register']);
