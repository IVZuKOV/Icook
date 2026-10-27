<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$input = body_json();
$email = trim((string) ($input['email'] ?? ''));

$generic = ['success' => true, 'message' => 'Если такой email зарегистрирован, письмо для сброса пароля отправлено'];

if ($email === '' || !valid_email($email)) {
    json_out(['error' => 'Некорректный email']);
}

// Ограничение частоты, чтобы не рассылали письма чужим адресам.
if (!rate_limit($pdo, 'reset-ip:' . client_ip(), 10, 3600)
    || !rate_limit($pdo, 'reset-email:' . mb_strtolower($email), 3, 3600)) {
    json_out(['error' => 'Слишком много запросов. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Ответ одинаковый независимо от существования аккаунта (нет перечисления пользователей).
if (!$user) {
    json_out($generic);
}

$token = bin2hex(random_bytes(32));
$expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

$pdo->exec('DELETE FROM email_verifications WHERE expires_at < NOW()');
$stmt = $pdo->prepare(
    'INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE token = ?, expires_at = ?'
);
$stmt->execute([$email, $token, $expires, $token, $expires]);

$link = 'https://icook.com.ru/reset_password.html?token=' . urlencode($token);
$subject = 'Сброс пароля — Я готовлю';
$message = "Вы запросили сброс пароля. Перейдите по ссылке:\n\n$link\n\nСсылка действительна 1 час.\n\nЕсли вы не запрашивали сброс — проигнорируйте это письмо.";
$headers = "From: noreply@icook.com.ru\r\nContent-Type: text/plain; charset=utf-8";

mail($email, $subject, $message, $headers);

json_out($generic);
