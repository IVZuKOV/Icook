<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

function getReviewUser($pdo)
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    if (!$userId) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers['X-Auth-Token'] ?? $headers['x-auth-token'] ?? ($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
        $decoded = $token !== '' ? base64_decode($token, true) : false;
        if ($decoded !== false) {
            $parts = explode(':', $decoded, 2);
            if (count($parts) === 2 && ctype_digit($parts[0])) {
                $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND COALESCE(email, '') = ?");
                $check->execute([(int) $parts[0], $parts[1]]);
                $userId = (int) ($check->fetchColumn() ?: 0);
            }
        }
    }

    if (!$userId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function respondWithError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'me') {
        $user = getReviewUser($pdo);
        echo json_encode([
            'success' => true,
            'authenticated' => (bool) $user,
            'name' => $user ? $user['name'] ?? '' : '',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($method === 'GET' && $action === 'pending') {
        $user = getReviewUser($pdo);
        if (!$user || !in_array($user['role'], ['admin', 'superuser'], true)) {
            respondWithError('Нет доступа', 403);
        }

        $status = $_GET['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            respondWithError('Некорректный статус');
        }

        $stmt = $pdo->prepare(
            "SELECT r.id, r.rating, r.review_text, r.status, r.created_at, u.name AS user_name
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.status = ?
             ORDER BY r.created_at DESC, r.id DESC"
        );
        $stmt->execute([$status]);
        echo json_encode([
            'success' => true,
            'reviews' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($method === 'GET') {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
        $limit = $limit === false || $limit === null ? 24 : max(1, min($limit, 60));
        $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);
        $offset = $offset === false || $offset === null ? 0 : max(0, $offset);

        $count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'");
        $total = (int) $count->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT r.id, r.rating, r.review_text, r.created_at, u.name AS user_name
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.status = 'approved'
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute();
        echo json_encode([
            'success' => true,
            'reviews' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        respondWithError('Метод не поддерживается', 405);
    }

    $user = getReviewUser($pdo);
    if (!$user) {
        respondWithError('Войдите в аккаунт, чтобы отправить отзыв', 401);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respondWithError('Некорректный запрос');
    }

    if (($data['action'] ?? '') === 'moderate') {
        if (!in_array($user['role'], ['admin', 'superuser'], true)) {
            respondWithError('Нет прав', 403);
        }

        $reviewIdValue = $data['review_id'] ?? null;
        $reviewId = filter_var(
            is_int($reviewIdValue) || is_string($reviewIdValue) ? $reviewIdValue : null,
            FILTER_VALIDATE_INT
        );
        $status = $data['status'] ?? '';
        if (!$reviewId || !in_array($status, ['approved', 'rejected'], true)) {
            respondWithError('Укажите отзыв и корректное решение');
        }

        $stmt = $pdo->prepare(
            "UPDATE reviews
             SET status = ?, moderated_by = ?, moderated_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$status, $user['id'], $reviewId]);
        if ($stmt->rowCount() !== 1) {
            respondWithError('Отзыв не найден или уже обработан', 404);
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ratingValue = $data['rating'] ?? null;
    $rating = filter_var(
        is_int($ratingValue) || is_string($ratingValue) ? $ratingValue : null,
        FILTER_VALIDATE_INT
    );
    $reviewText = $data['review_text'] ?? null;
    if (!is_string($reviewText)) {
        respondWithError('Текст отзыва должен быть строкой');
    }
    $reviewText = trim($reviewText);
    if ($rating === false || $rating < 1 || $rating > 5) {
        respondWithError('Оценка должна быть от 1 до 5');
    }
    if (mb_strlen($reviewText) < 10 || mb_strlen($reviewText) > 2000) {
        respondWithError('Текст отзыва должен содержать от 10 до 2000 символов');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO reviews (user_id, rating, review_text, status)
         VALUES (?, ?, ?, 'pending')"
    );
    $stmt->execute([$user['id'], $rating, $reviewText]);
    echo json_encode([
        'success' => true,
        'message' => 'Отзыв отправлен на модерацию',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Reviews API failed: ' . $e->getMessage());
    respondWithError('Не удалось обработать отзывы. Проверьте, что база данных обновлена.', 500);
}
