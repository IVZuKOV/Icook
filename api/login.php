<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    json_out(['error' => 'Заполните все поля']);
}

// Защита от перебора: по IP и отдельно по e-mail.
if (!rate_limit($pdo, 'login-ip:' . client_ip(), 20, 900)
    || !rate_limit($pdo, 'login-email:' . mb_strtolower($email), 10, 900)) {
    json_out(['error' => 'Слишком много попыток входа. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, (string) $user['password'])) {
    if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
    }

    // Новая сессия после входа — защита от фиксации сессии.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    json_out(['success' => true, 'name' => $user['name']]);
}

// Одинаковый ответ, чтобы не раскрывать существование аккаунта.
json_out(['error' => 'Неверный email или пароль'], 401);
