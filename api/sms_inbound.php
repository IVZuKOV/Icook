<?php
declare(strict_types=1);

/**
 * Вебхук для SMS-форвардера на телефоне.
 *
 * Авторизация (любой из вариантов):
 *   - HMAC: X-IKS-Timestamp: <unix>, X-IKS-Signature: hex(hmac_sha256(secret, ts + "." + body))
 *   - токен: X-IKS-SMS-Token: <ICOOK_INBOUND_SMS_SECRET> (обратная совместимость)
 * Принимаемый JSON:  {"from":"+7...","text":"1234"}
 * Принимаемая форма: from=+7...&text=1234
 * Приложение может присылать sender/message/content вместо from/text.
 *
 * Этот файл намеренно НЕ подключает bootstrap.php: приходит не из браузера,
 * поэтому проверка Origin (CSRF) тут не нужна — доступ защищён секретом.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/db.php';
require_once __DIR__ . '/lib/phone.php';

$configFile = '/etc/icook/sms_inbound.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'SMS inbox is not configured']);
    exit;
}
require $configFile;

$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 8192) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload too large']);
    exit;
}

/** Действующие секреты: текущий + предыдущий на время ротации. */
function icook_sms_secrets(): array
{
    $secrets = [];
    if (defined('ICOOK_INBOUND_SMS_SECRET') && is_string(ICOOK_INBOUND_SMS_SECRET)) {
        $secrets[] = ICOOK_INBOUND_SMS_SECRET;
    }
    if (defined('ICOOK_INBOUND_SMS_SECRET_PREV') && is_string(ICOOK_INBOUND_SMS_SECRET_PREV)
        && defined('ICOOK_INBOUND_SMS_SECRET_PREV_UNTIL')
        && (int) ICOOK_INBOUND_SMS_SECRET_PREV_UNTIL > time()) {
        $secrets[] = ICOOK_INBOUND_SMS_SECRET_PREV;
    }

    return array_values(array_filter(
        $secrets,
        static fn(string $v): bool => strlen($v) >= 32
    ));
}

$secrets = icook_sms_secrets();
$requireHmac = defined('ICOOK_REQUIRE_HMAC') && ICOOK_REQUIRE_HMAC === true;
$signature = (string) ($_SERVER['HTTP_X_IKS_SIGNATURE'] ?? '');
$timestamp = (string) ($_SERVER['HTTP_X_IKS_TIMESTAMP'] ?? '');
$authorized = false;
$authMode = '';

if ($signature !== '' && $timestamp !== '') {
    // HMAC: секрет не передаётся, подпись привязана к телу и времени.
    if (ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300) {
        foreach ($secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', $timestamp . '.' . $raw, $secret), strtolower($signature))) {
                $authorized = true;
                $authMode = 'hmac';
                break;
            }
        }
    }
} elseif (!$requireHmac) {
    // Обратная совместимость: статический токен (уйдёт после перехода на HMAC).
    $token = (string) ($_SERVER['HTTP_X_IKS_SMS_TOKEN'] ?? '');
    if ($token === '') {
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(\S+)$/iD', $authorization, $match)) {
            $token = $match[1];
        }
    }
    if ($token !== '') {
        foreach ($secrets as $secret) {
            if (hash_equals($secret, $token)) {
                $authorized = true;
                $authMode = 'token';
                break;
            }
        }
    }
}

if (!$authorized) {
    error_log('Inbound SMS rejected: bad credentials from ' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ' (auth=' . ($signature !== '' ? 'hmac' : 'token') . ')');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$fromRaw = $data['from'] ?? $data['sender'] ?? $data['phone'] ?? $data['origin'] ?? '';
$textRaw = $data['text'] ?? $data['message'] ?? $data['msg'] ?? $data['content'] ?? '';

$sender = iks_normalize_phone((string) $fromRaw);
$text = trim((string) $textRaw);

if ($sender === null || $text === '') {
    // Подтверждаем доставку, чтобы форвардер не повторял бесконечно.
    echo json_encode(['ok' => true, 'matched' => false]);
    exit;
}

// Принимаем ровно одну группу из 4 цифр: и "1234", и "Код: 1234".
preg_match_all('/\d+/u', $text, $matches);
$groups = array_values(array_filter($matches[0] ?? [], static fn(string $v): bool => $v !== ''));
$code = null;
if (count($groups) === 1 && strlen($groups[0]) === 4) {
    $code = $groups[0];
}

if ($code === null) {
    error_log('Inbound SMS ignored: no single 4-digit code from ' . iks_mask_phone($sender));
    echo json_encode(['ok' => true, 'matched' => false]);
    exit;
}

// Подтверждаем все активные проверки этого номера с таким кодом: при нескольких
// одновременных проверках коды могут совпасть, и «одна строка» выбрала бы чужую.
$update = $pdo->prepare("UPDATE sms_login_challenges
    SET status = 'verified', verified_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    WHERE phone = ? AND code = ? AND status = 'pending' AND expires_at > NOW()");
$update->execute([$sender, $code]);
$matched = $update->rowCount() >= 1;

if (!$matched) {
    error_log('Inbound SMS ignored: no active challenge from ' . iks_mask_phone($sender));
    echo json_encode(['ok' => true, 'matched' => false]);
    exit;
}

if ($matched) {
    error_log('Inbound SMS verified: ' . iks_mask_phone($sender)
        . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' auth=' . $authMode);
}

echo json_encode(['ok' => true, 'matched' => $matched]);
