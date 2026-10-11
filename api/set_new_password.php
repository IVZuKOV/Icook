<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';

if (!$token || !$password || strlen($password) < 8) {
    echo json_encode(['error' => 'Пароль минимум 8 символов']);
    exit;
}

$stmt = $pdo->prepare("SELECT email FROM email_verifications WHERE token=? AND expires_at > NOW()");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'Ссылка недействительна или истекла']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hash, $row['email']]);

$pdo->prepare("DELETE FROM email_verifications WHERE token=?")->execute([$token]);

echo json_encode(['success' => true, 'message' => 'Пароль успешно изменён']);
?>
