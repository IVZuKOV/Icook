<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    json_out(['error' => 'Нет токена']);
}
if (!rate_limit($pdo, 'verify-token:' . client_ip(), 30, 3600)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

// Токен НЕ удаляем: он ещё нужен для set_new_password.php.
$stmt = $pdo->prepare('SELECT email FROM email_verifications WHERE token = ? AND expires_at > NOW() LIMIT 1');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    json_out(['error' => 'Ссылка недействительна или истекла']);
}

json_out(['success' => true, 'email' => $row['email']]);
