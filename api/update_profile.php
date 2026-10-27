<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$userId = require_login();
$data = body_json();

if (!$data) {
    json_out(['success' => false, 'error' => 'Нет данных']);
}

$fields = [];
$params = [];

// Имя
if (isset($data['name'])) {
    $name = trim((string) $data['name']);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        json_out(['success' => false, 'error' => 'Имя должно быть от 2 до 100 символов']);
    }
    $fields[] = 'name = ?';
    $params[] = $name;
}

// E-mail: меняем только по текущему паролю и с проверкой уникальности.
if (isset($data['email'])) {
    $email = trim((string) $data['email']);
    if (!valid_email($email)) {
        json_out(['success' => false, 'error' => 'Некорректный email']);
    }
    $stmt = $pdo->prepare('SELECT email, password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $current = $stmt->fetch();
    if (!$current) {
        json_out(['success' => false, 'error' => 'Пользователь не найден'], 404);
    }
    if (mb_strtolower($email) !== mb_strtolower((string) $current['email'])) {
        $password = (string) ($data['current_password'] ?? '');
        if ($password === '' || !password_verify($password, (string) $current['password'])) {
            json_out(['success' => false, 'error' => 'Для смены email укажите текущий пароль'], 403);
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            json_out(['success' => false, 'error' => 'Этот email уже используется']);
        }
        $fields[] = 'email = ?';
        $params[] = $email;
        $_SESSION['user_email'] = $email;
    }
}

$textLimits = ['country' => 100, 'city' => 100, 'address' => 500, 'lang' => 10];
foreach ($textLimits as $key => $max) {
    if (isset($data[$key])) {
        $value = trim((string) $data[$key]);
        if (mb_strlen($value) > $max) {
            json_out(['success' => false, 'error' => 'Слишком длинное значение поля ' . $key]);
        }
        $fields[] = "$key = ?";
        $params[] = $value;
    }
}

foreach (['lat' => [-90, 90], 'lng' => [-180, 180]] as $key => $range) {
    if (isset($data[$key]) && $data[$key] !== '') {
        $value = (float) $data[$key];
        if ($value < $range[0] || $value > $range[1]) {
            json_out(['success' => false, 'error' => 'Некорректные координаты']);
        }
        $fields[] = "$key = ?";
        $params[] = $value;
    }
}

if (isset($data['search_radius'])) {
    $radius = (int) $data['search_radius'];
    if ($radius < 1 || $radius > 100) {
        json_out(['success' => false, 'error' => 'Радиус поиска должен быть от 1 до 100 км']);
    }
    $fields[] = 'search_radius = ?';
    $params[] = $radius;
}

if (!$fields) {
    json_out(['success' => false, 'error' => 'Нет полей для обновления']);
}

$params[] = $userId;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
$stmt->execute($params);

json_out(['success' => true, 'message' => 'Профиль обновлён']);
