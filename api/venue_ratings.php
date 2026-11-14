<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/venue_store.php';

header('Cache-Control: private, no-store');

function rating_venue_conditions(array $venueIds): array
{
    $conditions = [];
    $parameters = [];
    foreach ($venueIds as [$type, $id]) {
        $conditions[] = '(v.osm_type = ? AND v.osm_id = ?)';
        $parameters[] = $type;
        $parameters[] = $id;
    }
    return [implode(' OR ', $conditions), $parameters];
}

try {
    $venuePdo = venue_database();
} catch (Throwable $error) {
    error_log('Venue rating API database initialization failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Данные рейтинга временно недоступны'], 503);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $rawIds = (string) ($_GET['ids'] ?? '');
        $parts = array_slice(array_unique(array_filter(explode(',', $rawIds))), 0, 100);
        $venueIds = [];
        foreach ($parts as $part) {
            $venueId = parse_venue_id($part);
            if ($venueId !== null) {
                $venueIds[$venueId[0] . ':' . $venueId[1]] = $venueId;
            }
        }
        if (!$venueIds) {
            json_out(['success' => true, 'ratings' => (object) []]);
        }

        [$where, $parameters] = rating_venue_conditions(array_values($venueIds));
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $sql = 'SELECT v.osm_type, v.osm_id, v.photo_path,
                       AVG(r.rating) AS average_rating, COUNT(r.user_id) AS rating_count,
                       MAX(CASE WHEN r.user_id = ? THEN r.rating END) AS user_rating
                FROM icook_venues.venues v
                LEFT JOIN icook_venues.venue_ratings r
                    ON r.osm_type = v.osm_type AND r.osm_id = v.osm_id
                WHERE ' . $where . '
                GROUP BY v.osm_type, v.osm_id, v.photo_path';
        $statement = $pdo->prepare($sql);
        $statement->execute(array_merge([$userId], $parameters));
        $ratings = [];
        foreach ($statement->fetchAll() as $row) {
            $id = $row['osm_type'] . ':' . $row['osm_id'];
            $ratings[$id] = [
                'average' => $row['average_rating'] === null ? null : round((float) $row['average_rating'], 2),
                'count' => (int) $row['rating_count'],
                'user_rating' => $row['user_rating'] === null ? null : (int) $row['user_rating'],
                'photo' => $row['photo_path'],
            ];
        }
        json_out(['success' => true, 'ratings' => $ratings]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
    }

    $userId = require_login();
    if (!rate_limit($pdo, 'venue-rating:' . $userId, 120, 86400)) {
        json_out(['success' => false, 'error' => 'Слишком много оценок. Попробуйте позже.'], 429);
    }

    $data = body_json();
    $venueId = parse_venue_id($data['venue_id'] ?? null);
    $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
    if ($venueId === null || $rating === false || $rating === null || $rating < 1 || $rating > 5) {
        json_out(['success' => false, 'error' => 'Выберите заведение и оценку от 1 до 5'], 400);
    }

    $statement = $venuePdo->prepare('SELECT 1 FROM venues WHERE osm_type = ? AND osm_id = ?');
    $statement->execute([$venueId[0], $venueId[1]]);
    if (!$statement->fetchColumn()) {
        json_out(['success' => false, 'error' => 'Заведение не найдено. Обновите список заведений.'], 404);
    }

    $statement = $venuePdo->prepare(
        'INSERT INTO venue_ratings (osm_type, osm_id, user_id, rating)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([$venueId[0], $venueId[1], $userId, $rating]);

    $statement = $venuePdo->prepare(
        'SELECT AVG(rating) AS average_rating, COUNT(*) AS rating_count
         FROM venue_ratings WHERE osm_type = ? AND osm_id = ?'
    );
    $statement->execute([$venueId[0], $venueId[1]]);
    $summary = $statement->fetch();
    json_out([
        'success' => true,
        'rating' => $rating,
        'average' => round((float) $summary['average_rating'], 2),
        'count' => (int) $summary['rating_count'],
    ]);
} catch (PDOException | RuntimeException $error) {
    error_log('Venue rating API failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось сохранить оценку. Попробуйте позже.'], 503);
}
