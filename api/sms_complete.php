<?php
declare(strict_types=1);

/**
 * Обратный SMS-вход, шаг 3: завершение.
 * Если телефон найден — сбрасываем пароль существующему аккаунту,
 * иначе регистрируем нового пользователя (имя + пароль, email необязателен).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/phone.php';
require_once __DIR__ . '/mailer.php';

$data = body_json();
$challenge = trim((string) ($data['challenge'] ?? ''));
$password = (string) ($data['password'] ?? '');
$name = trim(strip_tags((string) ($data['name'] ?? '')));
$email = strtolower(trim((string) ($data['email'] ?? '')));

if (!preg_match('/^[a-f0-9]{64}$/D', $challenge)) {
    json_out(['error' => 'Некорректная проверка'], 422);
}
if (strlen($password) < 8 || strlen($password) > 200) {
    json_out(['error' => 'Пароль должен быть от 8 до 200 символов'], 422);
}
if (!rate_limit($pdo, 'sms-complete:ip:' . client_ip(), 12, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$challengeHash = hash('sha256', $challenge);
$pdo->beginTransaction();

$stmt = $pdo->prepare("SELECT id, phone, status, expires_at
    FROM sms_login_challenges
    WHERE challenge_hash = ?
    LIMIT 1
    FOR UPDATE");
$stmt->execute([$challengeHash]);
$row = $stmt->fetch();

if (!$row || $row['status'] !== 'verified' || strtotime((string) $row['expires_at']) <= time()) {
    $pdo->rollBack();
    json_out(['error' => 'Подтверждение недействительно или истекло'], 403);
}

$user = iks_find_user_by_phone($pdo, (string) $row['phone'], true);
$mode = $user ? 'reset' : 'register';

if ($mode === 'reset') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([$hash, (int) $user['id']]);
    $userId = (int) $user['id'];
    $userName = (string) $user['name'];
    $userEmail = $user['email'] !== null ? (string) $user['email'] : null;
} else {
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $pdo->rollBack();
        json_out(['error' => 'Имя должно быть от 2 до 100 символов'], 422);
    }
    // В таблице icook users.email объявлен NOT NULL, поэтому при регистрации
    // email обязателен — как и в обычной регистрации через register.php.
    if ($email === '' || !valid_email($email)) {
        $pdo->rollBack();
        json_out(['error' => 'Укажите корректный email'], 422);
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        $pdo->rollBack();
        json_out(['error' => 'Этот email уже используется'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, email, phone, password, is_seller) VALUES (?, ?, ?, ?, 0)")
        ->execute([$name, $email, (string) $row['phone'], $hash]);
    $userId = (int) $pdo->lastInsertId();
    $userName = $name;
    $userEmail = $email;
}

$pdo->prepare("UPDATE sms_login_challenges SET status = 'used', used_at = NOW() WHERE id = ?")
    ->execute([(int) $row['id']]);
$pdo->commit();

// Уведомляем владельца: сброс пароля по SMS не должен проходить незаметно.
if ($mode === 'reset' && $userEmail !== null && $userEmail !== '') {
    $safeName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars(client_ip(), ENT_QUOTES, 'UTF-8');
    sendMail(
        $userEmail,
        'ICook: пароль изменён по SMS',
        '<p>Здравствуйте, ' . $safeName . '!</p>'
        . '<p>Пароль от вашего аккаунта изменён ' . date('d.m.Y H:i') . ' через подтверждение по SMS.</p>'
        . '<p>IP: ' . $safeIp . '</p>'
        . '<p>Если это были не вы — сразу смените пароль и ответьте на это письмо.</p>'
    );
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $userName;
$_SESSION['user_email'] = $userEmail;

json_out([
    'success' => true,
    'registered' => $mode === 'register',
    'reset' => $mode === 'reset',
    'id' => $userId,
    'name' => $userName,
    'email' => $userEmail,
    'phone' => (string) $row['phone'],
]);
