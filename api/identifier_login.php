<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/identifier.php';

$data = body_json();
$input = trim((string) ($data['identifier'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($input === '' || $password === '') {
    json_out(['error' => 'Заполните все поля'], 422);
}

if (!rate_limit($pdo, 'idlogin-ip:' . client_ip(), 30, 900)
    || !rate_limit($pdo, 'idlogin:' . mb_strtolower($input), 10, 900)) {
    json_out(['error' => 'Слишком много попыток входа. Попробуйте позже'], 429);
}

[$kind, $rows] = iks_find_users_by_identifier($pdo, $input, 10);

// Constant-ish timing: verify against a dummy hash when nothing matches.
$matched = null;
$hits = 0;
if (!$rows) {
    password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
} else {
    foreach ($rows as $row) {
        if (password_verify($password, (string) $row['password'])) {
            $matched = $row;
            $hits++;
        }
    }
}

if ($hits !== 1 || !$matched) {
    json_out(['error' => 'Неверный логин или пароль'], 401);
}

if (password_needs_rehash((string) $matched['password'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $matched['id']]);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $matched['id'];
$_SESSION['user_name'] = (string) $matched['name'];
$_SESSION['user_email'] = $matched['email'] !== null ? (string) $matched['email'] : null;

json_out([
    'success' => true,
    'name' => (string) $matched['name'],
    'email' => $matched['email'] !== null ? (string) $matched['email'] : null,
]);
