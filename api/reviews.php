<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Текущий пользователь для API отзывов: только сессия (никаких самодельных токенов). */
function getReviewUser(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function respondWithError(string $message, int $statusCode = 400): void
{
    json_out(['success' => false, 'error' => $message], $statusCode);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

try {
    if ($method === 'GET' && $action === 'me') {
        $user = getReviewUser($pdo);
        json_out([
            'success' => true,
            'authenticated' => (bool) $user,
            'name' => $user ? ($user['name'] ?? '') : '',
        ]);
    }

    if ($method === 'GET' && $action === 'pending') {
        $user = getReviewUser($pdo);
        if (!$user || !in_array((string) $user['role'], ['admin', 'superuser'], true)) {
            respondWithError('Нет доступа', 403);
        }

        $status = (string) ($_GET['status'] ?? 'pending');
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
        json_out(['success' => true, 'reviews' => $stmt->fetchAll()]);
    }

    if ($method === 'GET') {
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
        $limit = $limit === false || $limit === null ? 24 : max(1, min($limit, 60));
        $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);
        $offset = $offset === false || $offset === null ? 0 : max(0, $offset);

        $total = (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT r.id, r.rating, r.review_text, r.created_at, u.name AS user_name
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.status = 'approved'
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_out([
            'success' => true,
            'reviews' => $stmt->fetchAll(),
            'total' => $total,
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        respondWithError('Метод не поддерживается', 405);
    }

    $user = getReviewUser($pdo);
    if (!$user) {
        respondWithError('Войдите в аккаунт, чтобы отправить отзыв', 401);
    }

    $data = body_json();
    if (!$data) {
        respondWithError('Некорректный запрос');
    }

    if (($data['action'] ?? '') === 'moderate') {
        if (!in_array((string) $user['role'], ['admin', 'superuser'], true)) {
            respondWithError('Нет прав', 403);
        }

        $reviewId = filter_var($data['review_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($data['status'] ?? '');
        if (!$reviewId || !in_array($status, ['approved', 'rejected'], true)) {
            respondWithError('Укажите отзыв и корректное решение');
        }

        $stmt = $pdo->prepare(
            "UPDATE reviews
             SET status = ?, moderated_by = ?, moderated_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$status, (int) $user['id'], (int) $reviewId]);
        if ($stmt->rowCount() !== 1) {
            respondWithError('Отзыв не найден или уже обработан', 404);
        }

        json_out(['success' => true]);
    }

    $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
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
    if (!rate_limit($pdo, 'review:' . $user['id'], 5, 3600)) {
        respondWithError('Слишком много отзывов. Попробуйте позже', 429);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO reviews (user_id, rating, review_text, status) VALUES (?, ?, ?, 'pending')"
    );
    $stmt->execute([(int) $user['id'], $rating, $reviewText]);

    json_out(['success' => true, 'message' => 'Отзыв отправлен на модерацию']);
} catch (PDOException $e) {
    error_log('Reviews API failed: ' . $e->getMessage());
    respondWithError('Не удалось обработать отзывы. Проверьте, что база данных обновлена.', 500);
}
