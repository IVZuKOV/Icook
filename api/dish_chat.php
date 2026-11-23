<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$userId = require_login();
$data = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? body_json() : $_GET;
$action = (string) ($data['action'] ?? 'messages');

if ($action === 'open') {
    $dishId = (int) ($data['dish_id'] ?? 0);
    $dish = $pdo->prepare('SELECT id, user_id FROM dishes WHERE id = ? AND is_available = 1');
    $dish->execute([$dishId]);
    $row = $dish->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) $row['user_id'] === $userId) json_out(['success' => false, 'error' => 'Чат недоступен'], 404);
    $pdo->prepare('INSERT IGNORE INTO dish_conversations (dish_id, buyer_id, seller_id) VALUES (?, ?, ?)')
        ->execute([$dishId, $userId, (int) $row['user_id']]);
    $query = $pdo->prepare('SELECT id FROM dish_conversations WHERE dish_id = ? AND buyer_id = ? AND seller_id = ?');
    $query->execute([$dishId, $userId, (int) $row['user_id']]);
    json_out(['success' => true, 'conversation_id' => (int) $query->fetchColumn()]);
}

$conversationId = (int) ($data['conversation_id'] ?? 0);
$conversation = $pdo->prepare('SELECT * FROM dish_conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)');
$conversation->execute([$conversationId, $userId, $userId]);
if (!$conversation->fetch(PDO::FETCH_ASSOC)) json_out(['success' => false, 'error' => 'Чат не найден'], 404);

if ($action === 'send') {
    $text = trim((string) ($data['message'] ?? ''));
    if ($text === '' || mb_strlen($text) > 2000) json_out(['success' => false, 'error' => 'Сообщение должно содержать до 2000 символов']);
    $pdo->prepare('INSERT INTO dish_messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)')
        ->execute([$conversationId, $userId, $text]);
    $pdo->prepare('UPDATE dish_conversations SET updated_at = NOW() WHERE id = ?')->execute([$conversationId]);
}
$messages = $pdo->prepare('SELECT m.id, m.sender_id, m.message_text, m.created_at, u.name AS sender_name FROM dish_messages m JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ? ORDER BY m.id');
$messages->execute([$conversationId]);
json_out(['success' => true, 'messages' => $messages->fetchAll(PDO::FETCH_ASSOC)]);
