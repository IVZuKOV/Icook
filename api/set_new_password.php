<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$input = body_json();
$token = (string) ($input['token'] ?? '');
$password = (string) ($input['password'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    json_out(['error' => 'Ссылка недействительна или истекла']);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов']);
}
if (!rate_limit($pdo, 'set-password:' . client_ip(), 10, 3600)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT email FROM email_verifications WHERE token = ? AND expires_at > NOW() LIMIT 1');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    json_out(['error' => 'Ссылка недействительна или истекла']);
}

$pdo->prepare('UPDATE users SET password = ? WHERE email = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['email']]);

// Токен одноразовый: удаляем все токены для этого адреса.
$pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$row['email']]);

json_out(['success' => true, 'message' => 'Пароль успешно изменён']);
