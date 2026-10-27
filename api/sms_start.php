<?php
declare(strict_types=1);

/**
 * Обратный SMS-вход, шаг 1: создаём проверку и показываем пользователю код,
 * который он должен отправить на наш номер.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/phone.php';

$data = body_json();
$rawPhone = trim((string) ($data['phone'] ?? ''));
$phone = iks_normalize_phone($rawPhone);

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

if (!rate_limit($pdo, 'sms-start:phone:' . $phone, 3, 900)
    || !rate_limit($pdo, 'sms-start:ip:' . client_ip(), 12, 900)) {
    json_out(['error' => 'Слишком много попыток. Попробуйте позже'], 429);
}

$code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$challenge = bin2hex(random_bytes(32));
$challengeHash = hash('sha256', $challenge);
$expiresIn = 600;
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

$pdo->beginTransaction();
$pdo->prepare("UPDATE sms_login_challenges SET status = 'expired' WHERE phone = ? AND status = 'pending'")
    ->execute([$phone]);
$stmt = $pdo->prepare("INSERT INTO sms_login_challenges
    (challenge_hash, phone, code, status, ip, expires_at)
    VALUES (?, ?, ?, 'pending', ?, ?)");
$stmt->execute([$challengeHash, $phone, $code, client_ip(), $expiresAt]);
$pdo->commit();

json_out([
    'success' => true,
    'challenge' => $challenge,
    'phone_masked' => iks_mask_phone($phone),
    'code' => $code,
    'destination' => $destination,
    'destination_display' => iks_format_phone($destination),
    'expires_in' => $expiresIn,
]);
