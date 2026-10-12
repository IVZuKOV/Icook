<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Некорректный email']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Пользователь с таким email не найден']);
    exit;
}

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

$pdo->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");
$stmt = $pdo->prepare("INSERT INTO email_verifications (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=?, expires_at=?");
$stmt->execute([$email, $token, $expires, $token, $expires]);

$link = "https://icook.com.ru/reset_password.html?token=$token";
$subject = "Сброс пароля — Я готовлю";
$message = "Вы запросили сброс пароля. Перейдите по ссылке:\n\n$link\n\nСсылка действительна 1 час.\n\nЕсли вы не запрашивали сброс — проигнорируйте это письмо.";
$headers = "From: noreply@icook.ru\r\nContent-Type: text/plain; charset=utf-8";

mail($email, $subject, $message, $headers);

echo json_encode(['success' => true, 'message' => 'Письмо для сброса пароля отправлено']);
?>
