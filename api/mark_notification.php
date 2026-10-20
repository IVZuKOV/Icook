<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$notif_id = $data['id'] ?? null;
$mark_all = $data['mark_all'] ?? false;

if ($mark_all) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'message' => 'Все отмечены']);
} elseif ($notif_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Отмечено']);
} else {
    echo json_encode(['success' => false, 'error' => 'Нет параметров']);
}
?>
