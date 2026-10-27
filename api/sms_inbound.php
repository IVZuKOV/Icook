<?php
declare(strict_types=1);

/**
 * Вебхук для SMS-форвардера на телефоне.
 *
 * Обязательный заголовок: X-IKS-SMS-Token: <ICOOK_INBOUND_SMS_SECRET>
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

$token = (string) ($_SERVER['HTTP_X_IKS_SMS_TOKEN'] ?? '');
if ($token === '') {
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(\S+)$/iD', $authorization, $match)) {
        $token = $match[1];
    }
}

if (!defined('ICOOK_INBOUND_SMS_SECRET') || !is_string(ICOOK_INBOUND_SMS_SECRET)
    || strlen(ICOOK_INBOUND_SMS_SECRET) < 32 || $token === ''
    || !hash_equals(ICOOK_INBOUND_SMS_SECRET, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
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

$stmt = $pdo->prepare("SELECT id FROM sms_login_challenges
    WHERE phone = ? AND code = ? AND status = 'pending' AND expires_at > NOW()
    ORDER BY id DESC
    LIMIT 1");
$stmt->execute([$sender, $code]);
$challengeId = $stmt->fetchColumn();

if ($challengeId === false) {
    error_log('Inbound SMS ignored: no active challenge from ' . iks_mask_phone($sender));
    echo json_encode(['ok' => true, 'matched' => false]);
    exit;
}

$update = $pdo->prepare("UPDATE sms_login_challenges
    SET status = 'verified', verified_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    WHERE id = ? AND status = 'pending'");
$update->execute([(int) $challengeId]);

echo json_encode(['ok' => true, 'matched' => $update->rowCount() === 1]);
