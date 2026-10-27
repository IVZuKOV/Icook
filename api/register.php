<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$data = body_json();
$name = trim((string) ($data['name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$phone = trim((string) ($data['phone'] ?? ''));

if ($name === '' || $email === '' || $password === '') {
    json_out(['error' => 'Заполните обязательные поля']);
}
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    json_out(['error' => 'Имя должно быть от 2 до 100 символов']);
}
if (!valid_email($email)) {
    json_out(['error' => 'Некорректный email']);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов']);
}
if (mb_strlen($phone) > 30) {
    json_out(['error' => 'Слишком длинный номер телефона']);
}

if (!rate_limit($pdo, 'register-ip:' . client_ip(), 10, 3600)) {
    json_out(['error' => 'Слишком много регистраций. Попробуйте позже'], 429);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_out(['error' => 'Пользователь с таким email уже зарегистрирован']);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password, phone, is_seller) VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    json_out(['success' => true, 'name' => $name, 'email' => $email]);
} catch (PDOException $e) {
    error_log('Register failed: ' . $e->getMessage());
    json_out(['error' => 'Ошибка регистрации. Попробуйте позже.'], 500);
}
