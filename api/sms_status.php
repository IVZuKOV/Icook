<?php
declare(strict_types=1);

/**
 * Обратный SMS-вход, шаг 2: браузер опрашивает статус проверки.
 * Когда SMS дошла и совпала — вернём mode: reset или register.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/phone.php';

$data = body_json();
$challenge = trim((string) ($data['challenge'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/D', $challenge)) {
    json_out(['error' => 'Некорректная проверка'], 422);
}

$challengeHash = hash('sha256', $challenge);
$stmt = $pdo->prepare("SELECT phone, status, expires_at FROM sms_login_challenges WHERE challenge_hash = ? LIMIT 1");
$stmt->execute([$challengeHash]);
$row = $stmt->fetch();

if (!$row) {
    json_out(['error' => 'Проверка не найдена'], 404);
}

if ($row['status'] === 'pending' && strtotime((string) $row['expires_at']) <= time()) {
    $pdo->prepare("UPDATE sms_login_challenges SET status = 'expired' WHERE challenge_hash = ? AND status = 'pending'")
        ->execute([$challengeHash]);
    $row['status'] = 'expired';
}

if ($row['status'] === 'pending') {
    json_out(['success' => true, 'status' => 'pending', 'expires_at' => $row['expires_at']]);
}

if ($row['status'] === 'verified') {
    if (strtotime((string) $row['expires_at']) <= time()) {
        $pdo->prepare("UPDATE sms_login_challenges SET status = 'expired' WHERE challenge_hash = ? AND status = 'verified'")
            ->execute([$challengeHash]);
        json_out(['success' => true, 'status' => 'expired']);
    }

    $user = iks_find_user_by_phone($pdo, (string) $row['phone']);
    json_out([
        'success' => true,
        'status' => 'verified',
        'mode' => $user ? 'reset' : 'register',
        'name' => $user ? (string) $user['name'] : null,
        'expires_at' => $row['expires_at'],
    ]);
}

json_out(['success' => true, 'status' => $row['status']]);
