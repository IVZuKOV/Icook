<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/identifier.php';

$data = body_json();
$input = trim((string) ($data['identifier'] ?? ''));

if ($input === '') {
    json_out(['error' => 'Введите логин, телефон или email'], 422);
}
if (mb_strlen($input) > 150) {
    json_out(['error' => 'Слишком длинный ввод'], 422);
}

if (!rate_limit($pdo, 'idlookup-ip:' . client_ip(), 40, 900)
    || !rate_limit($pdo, 'idlookup:' . mb_strtolower($input), 20, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

[$kind, $rows] = iks_find_users_by_identifier($pdo, $input);

if (!$rows) {
    $message = $kind === 'login'
        ? 'Логин не найден'
        : ($kind === 'email' ? 'Аккаунт с таким email не найден' : 'Аккаунт с таким телефоном не найден');
    json_out(['error' => $message], 404);
}

if (count($rows) > 1) {
    $what = $kind === 'login' ? 'логином' : 'телефоном';
    json_out(['error' => 'Найдено несколько аккаунтов с таким ' . $what . '. Войдите по email'], 409);
}

json_out([
    'success' => true,
    'kind' => $kind,
    // Имя показываем только при входе по логину.
    'greeting' => $kind === 'login' ? (string) $rows[0]['name'] : null,
]);
