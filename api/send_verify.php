<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Некорректный email']);
    exit;
}

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

$pdo->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");
$stmt = $pdo->prepare("INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=?, expires_at=?");
$stmt->execute([$email, $token, $expires, $token, $expires]);

$link = "https://icook.com.ru/verify_email.html?token=$token";
$subject = "Подтверждение регистрации — Я готовлю";
$message = "Привет! Подтверди свой email, перейдя по ссылке:\n\n$link\n\nСсылка действительна 24 часа.";
$headers = "From: noreply@icook.ru\r\nContent-Type: text/plain; charset=utf-8";

mail($email, $subject, $message, $headers);

echo json_encode(['success' => true, 'message' => 'Письмо отправлено']);
?>
