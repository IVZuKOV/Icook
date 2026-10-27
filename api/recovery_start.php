<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/identifier.php';

$data = body_json();
$input = trim((string) ($data['identifier'] ?? ''));

if ($input === '') {
    json_out(['error' => 'Введите email или телефон'], 422);
}

[$kind, $value] = iks_identify_input($input);
if ($kind === 'login') {
    json_out(['error' => 'Введите email или телефон, указанный при регистрации'], 422);
}

if (!rate_limit($pdo, 'recovery-start-ip:' . client_ip(), 20, 900)
    || !rate_limit($pdo, 'recovery-start:' . mb_strtolower($input), 5, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

[$kind, $rows] = iks_find_users_by_identifier($pdo, $input, 5);

if (!$rows) {
    json_out(['error' => $kind === 'email' ? 'Аккаунт с таким email не найден' : 'Аккаунт с таким телефоном не найден'], 404);
}
if (count($rows) > 1) {
    json_out(['error' => 'Этот телефон привязан к нескольким аккаунтам. Воспользуйтесь email'], 409);
}

if ($kind === 'email') {
    $email = $value;

    $pdo->prepare("UPDATE verify_codes SET used = 1 WHERE identifier = ? AND type = 'email'")->execute([$email]);
    $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $expires = (new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO verify_codes (identifier, code, type, expires_at) VALUES (?, ?, 'email', ?)")
        ->execute([$email, $code, $expires]);

    $subject = 'Восстановление доступа — Я готовлю';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $message = "Код для восстановления доступа: $code\n\n"
        . "Код действителен 10 минут. Если вы не запрашивали его — проигнорируйте письмо.";
    $headers = "From: noreply@icook.com.ru\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\n"
        . "Content-Transfer-Encoding: 8bit";

    if (!@mail($email, $encodedSubject, $message, $headers, '-fnoreply@icook.com.ru')) {
        $pdo->prepare('DELETE FROM verify_codes WHERE identifier = ? AND code = ?')->execute([$email, $code]);
        json_out(['error' => 'Не удалось отправить письмо. Попробуйте позже'], 503);
    }

    json_out([
        'success' => true,
        'channel' => 'email',
        'email_masked' => iks_mask_email($email),
    ]);
}

// Phone recovery: show a code, the user sends it to our inbound number.
$phone = iks_normalize_phone($value);
if ($phone === null) {
    json_out(['error' => 'Введите корректный номер телефона'], 422);
}
$configFile = '/etc/icook/sms_destination.php';
if (!is_file($configFile)) {
    json_out(['error' => 'Приём SMS временно не настроен'], 503);
}
require $configFile;

if (!defined('ICOOK_INBOUND_SMS_PHONE') || !is_string(ICOOK_INBOUND_SMS_PHONE)) {
    json_out(['error' => 'Приём SMS временно не настроен'], 503);
}

$destination = iks_normalize_phone(ICOOK_INBOUND_SMS_PHONE);
if ($destination === null) {
    json_out(['error' => 'Номер для приёма SMS настроен неверно'], 503);
}

$code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$challenge = bin2hex(random_bytes(32));
$challengeHash = hash('sha256', $challenge);
$expiresIn = 600;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

$pdo->beginTransaction();
$pdo->prepare("UPDATE sms_login_challenges SET status = 'expired' WHERE phone = ? AND status = 'pending'")
    ->execute([$phone]);
$pdo->prepare("INSERT INTO sms_login_challenges (challenge_hash, phone, code, status, ip, expires_at)
    VALUES (?, ?, ?, 'pending', ?, ?)")
    ->execute([$challengeHash, $phone, $code, client_ip(), $expiresAt]);
$pdo->commit();

json_out([
    'success' => true,
    'channel' => 'sms',
    'challenge' => $challenge,
    'code' => $code,
    'phone_masked' => iks_mask_phone($phone),
    'destination' => $destination,
    'destination_display' => iks_format_phone($destination),
    'expires_in' => $expiresIn,
]);
