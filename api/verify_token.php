<?php
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    echo json_encode(['error' => 'Нет токена']);
    exit;
}

$stmt = $pdo->prepare("SELECT email FROM email_verifications WHERE token=? AND expires_at > NOW()");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'Ссылка недействительна или истекла']);
    exit;
}

$pdo->prepare("DELETE FROM email_verifications WHERE token=?")->execute([$token]);

echo json_encode(['success' => true, 'email' => $row['email']]);
?>
