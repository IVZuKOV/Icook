<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$input = body_json();
$email = trim((string) ($input['email'] ?? ''));

if ($email === '' || !valid_email($email)) {
    json_out(['error' => 'Некорректный email']);
}

if (!rate_limit($pdo, 'send-verify-ip:' . client_ip(), 10, 3600)
    || !rate_limit($pdo, 'send-verify-email:' . mb_strtolower($email), 3, 3600)) {
    json_out(['error' => 'Слишком много запросов. Попробуйте позже'], 429);
}

$token = bin2hex(random_bytes(32));
$expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

$pdo->exec('DELETE FROM email_verifications WHERE expires_at < NOW()');
$stmt = $pdo->prepare(
    'INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE token = ?, expires_at = ?'
);
$stmt->execute([$email, $token, $expires, $token, $expires]);

$link = 'https://icook.com.ru/verify_email.html?token=' . urlencode($token);
$subject = 'Подтверждение регистрации — Я готовлю';
$message = "Привет! Подтверди свой email, перейдя по ссылке:\n\n$link\n\nСсылка действительна 24 часа.";
$headers = "From: noreply@icook.com.ru\r\nContent-Type: text/plain; charset=utf-8";

mail($email, $subject, $message, $headers);

json_out(['success' => true, 'message' => 'Письмо отправлено']);
