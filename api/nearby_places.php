<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/venue_store.php';

header('Cache-Control: private, no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respond_with_venue_data(array $payload): never
{
    if (empty($payload['success']) || empty($payload['venues'])) {
        respond($payload);
    }

    $venues = $payload['venues'];
    $conditions = [];
    $parameters = [(int) ($_SESSION['user_id'] ?? 0)];
    foreach ($venues as $venue) {
        $venueId = parse_venue_id($venue['venue_id'] ?? null);
        if ($venueId === null) {
            continue;
        }
        $conditions[] = '(v.osm_type = ? AND v.osm_id = ?)';
        $parameters[] = $venueId[0];
        $parameters[] = $venueId[1];
    }
    if (!$conditions) {
        respond($payload);
    }

    try {
        $venuePdo = venue_database();
        $statement = $venuePdo->prepare(
            'SELECT v.osm_type, v.osm_id, v.photo_path,
                    AVG(r.rating) AS average_rating, COUNT(r.user_id) AS rating_count,
                    MAX(CASE WHEN r.user_id = ? THEN r.rating END) AS user_rating
             FROM venues v
             LEFT JOIN venue_ratings r ON r.osm_type = v.osm_type AND r.osm_id = v.osm_id
             WHERE ' . implode(' OR ', $conditions) . '
             GROUP BY v.osm_type, v.osm_id, v.photo_path'
        );
        $statement->execute($parameters);
        $ratings = [];
        foreach ($statement->fetchAll() as $row) {
            $ratings[$row['osm_type'] . ':' . $row['osm_id']] = [
                'rating' => $row['average_rating'] === null ? null : round((float) $row['average_rating'], 2),
                'rating_count' => (int) $row['rating_count'],
                'user_rating' => $row['user_rating'] === null ? null : (int) $row['user_rating'],
                'img' => (string) ($row['photo_path'] ?? ''),
            ];
        }
        foreach ($payload['venues'] as &$venue) {
            $summary = $ratings[$venue['venue_id'] ?? ''] ?? null;
            $venue['rating'] = $summary['rating'] ?? null;
            $venue['rating_count'] = $summary['rating_count'] ?? 0;
            $venue['user_rating'] = $summary['user_rating'] ?? null;
            $venue['img'] = $summary['img'] ?? '';
        }
        unset($venue);
    } catch (Throwable $error) {
        error_log('Nearby venue ratings could not be loaded: ' . $error->getMessage());
        respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 503);
    }

    respond($payload);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

$latitude = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_GET['lon'] ?? null, FILTER_VALIDATE_FLOAT);
if ($latitude === false || $longitude === false
    || $latitude === null || $longitude === null
    || $latitude < -90 || $latitude > 90
    || $longitude < -180 || $longitude > 180) {
    respond(['success' => false, 'error' => 'Invalid location'], 400);
}

if (!extension_loaded('curl')) {
    error_log('Nearby places API requires the PHP cURL extension');
    respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 503);
}

$radius = 3000;
$cacheLatitude = round((float) $latitude, 3);
$cacheLongitude = round((float) $longitude, 3);
$cacheKey = hash('sha256', sprintf('v2:%.3f:%.3f:%d', $cacheLatitude, $cacheLongitude, $radius));
$cachePath = sys_get_temp_dir() . '/icook-nearby-' . $cacheKey . '.json';
$cacheContents = @file_get_contents($cachePath);
if ($cacheContents !== false && filemtime($cachePath) > time() - 600) {
    $cached = json_decode($cacheContents, true);
    if (is_array($cached) && isset($cached['venues']) && is_array($cached['venues'])) {
        respond_with_venue_data($cached);
    }
}

$lock = @fopen(sys_get_temp_dir() . '/icook-nominatim.lock', 'c+');
if ($lock === false || !flock($lock, LOCK_EX)) {
    if (is_resource($lock)) {
        fclose($lock);
    }
    error_log('Nearby places API could not acquire its Nominatim request lock');
    respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 503);
}

try {
    $cacheContents = @file_get_contents($cachePath);
    if ($cacheContents !== false && filemtime($cachePath) > time() - 600) {
        $cached = json_decode($cacheContents, true);
        if (is_array($cached) && isset($cached['venues']) && is_array($cached['venues'])) {
            respond_with_venue_data($cached);
        }
    }

    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ratePath = sys_get_temp_dir() . '/icook-nearby-rate-' . hash('sha256', $clientIp) . '.json';
    $rateContents = @file_get_contents($ratePath);
    $rateState = $rateContents === false ? [] : json_decode($rateContents, true);
    if (!is_array($rateState) || (int) ($rateState['expires'] ?? 0) <= time()) {
        $rateState = ['expires' => time() + 900, 'count' => 0];
    }
    if ((int) $rateState['count'] >= 12) {
        respond(['success' => false, 'error' => 'Please try again later'], 429);
    }
    $rateState['count'] = (int) $rateState['count'] + 1;
    if (@file_put_contents($ratePath, json_encode($rateState), LOCK_EX) === false) {
        error_log('Nearby places API could not update its rate-limit file');
        respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 503);
    }

    $queryRadius = $radius + 150;
    $latitudeDelta = $queryRadius / 111320;
    $longitudeScale = max(0.01, abs(cos(deg2rad((float) $latitude))));
    $longitudeDelta = $queryRadius / (111320 * $longitudeScale);
    $viewbox = implode(',', [
        max(-180, (float) $longitude - $longitudeDelta),
        min(90, (float) $latitude + $latitudeDelta),
        min(180, (float) $longitude + $longitudeDelta),
        max(-90, (float) $latitude - $latitudeDelta),
    ]);

    $categories = [
        'restaurant' => 'Ресторан',
        'bar' => 'Бар',
        'cafe' => 'Кафе',
    ];
    $venuesById = [];
    $successfulCategories = 0;

    foreach ($categories as $amenity => $label) {
        rewind($lock);
        $lastRequestStarted = (float) trim((string) stream_get_contents($lock));
        if ($lastRequestStarted > 0) {
            $waitMicroseconds = 1100000 - (int) ((microtime(true) - $lastRequestStarted) * 1000000);
            if ($waitMicroseconds > 0) {
                usleep($waitMicroseconds);
            }
        }

        $lastRequestStarted = microtime(true);
        rewind($lock);
        ftruncate($lock, 0);
        fwrite($lock, (string) $lastRequestStarted);
        fflush($lock);
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'format' => 'jsonv2',
            'amenity' => $amenity,
            'viewbox' => $viewbox,
            'bounded' => 1,
            'limit' => 30,
            'addressdetails' => 1,
            'extratags' => 1,
        ]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'iCookRestaurantSearch/1.0 (+https://icook.com.ru/)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $responseBody = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false || $httpStatus !== 200) {
            error_log(sprintf(
                'Nearby places lookup failed for %s: HTTP %d %s',
                $amenity,
                $httpStatus,
                $curlError
            ));
            continue;
        }

        $places = json_decode($responseBody, true);
        if (!is_array($places)) {
            error_log('Nearby places lookup returned invalid JSON for ' . $amenity);
            continue;
        }
        $successfulCategories++;

        foreach ($places as $place) {
            if (!is_array($place)) {
                continue;
            }
            $placeLatitude = filter_var($place['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $placeLongitude = filter_var($place['lon'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($placeLatitude === false || $placeLongitude === false
                || $placeLatitude === null || $placeLongitude === null) {
                continue;
            }

            $addressParts = $place['address'] ?? [];
            $street = (string) ($addressParts['road'] ?? $addressParts['pedestrian'] ?? $addressParts['residential'] ?? $addressParts['street'] ?? '');
            $houseNumber = (string) ($addressParts['house_number'] ?? '');
            $city = (string) ($addressParts['city'] ?? $addressParts['town'] ?? $addressParts['village'] ?? $addressParts['suburb'] ?? '');
            $streetAddress = trim($street . ($houseNumber !== '' ? ', ' . $houseNumber : ''));
            $address = implode(', ', array_filter([$streetAddress, $city], static fn (string $part): bool => $part !== ''));
            if ($address === '') {
                $address = (string) ($place['display_name'] ?? 'Адрес не указан');
                $address = implode(', ', array_slice(array_map('trim', explode(',', $address)), 0, 3));
            }

            $extra = is_array($place['extratags'] ?? null) ? $place['extratags'] : [];
            $ratingValue = $extra['stars'] ?? $extra['rating:stars'] ?? $extra['rating'] ?? null;
            $ratingValue = is_string($ratingValue) ? str_replace(',', '.', $ratingValue) : $ratingValue;
            $rating = is_numeric($ratingValue) && (float) $ratingValue > 0 && (float) $ratingValue <= 5
                ? (float) $ratingValue
                : null;
            $osmType = (string) ($place['osm_type'] ?? '');
            $osmId = (string) ($place['osm_id'] ?? $place['place_id'] ?? '');
            if (!in_array($osmType, ['node', 'way', 'relation'], true)
                || !preg_match('/\A[1-9][0-9]{0,18}\z/', $osmId)) {
                continue;
            }
            $id = $osmType . ':' . $osmId;
            $venuesById[$id] = [
                'venue_id' => $id,
                'name' => (string) ($place['name'] ?? $place['namedetails']['name'] ?? 'Заведение'),
                'type' => $label . (!empty($extra['cuisine']) ? ' · ' . str_replace(';', ', ', (string) $extra['cuisine']) : ''),
                'addr' => $address,
                'lat' => (float) $placeLatitude,
                'lon' => (float) $placeLongitude,
                'img' => '',
                'website' => (string) ($extra['contact:website'] ?? $extra['website'] ?? $extra['url'] ?? ''),
                'email' => (string) ($extra['contact:email'] ?? $extra['email'] ?? ''),
                'phone' => (string) ($extra['contact:phone'] ?? $extra['phone'] ?? ''),
                'rating' => null,
                'rating_count' => 0,
                'user_rating' => null,
            ];
        }
    }

    if ($successfulCategories === 0) {
        respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 502);
    }

    $result = ['success' => true, 'venues' => array_values($venuesById)];
    $venuePdo = venue_database();
    $venuePdo->beginTransaction();
    try {
        $upsert = $venuePdo->prepare(
            'INSERT INTO venues (osm_type, osm_id, name, address, website, latitude, longitude)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address),
                website = VALUES(website), latitude = VALUES(latitude), longitude = VALUES(longitude)'
        );
        foreach ($venuesById as $venue) {
            [$osmType, $osmId] = parse_venue_id($venue['venue_id']);
            $upsert->execute([
                $osmType,
                $osmId,
                mb_substr($venue['name'], 0, 255),
                mb_substr($venue['addr'], 0, 500),
                mb_substr($venue['website'], 0, 2048),
                $venue['lat'],
                $venue['lon'],
            ]);
        }
        $venuePdo->commit();
    } catch (Throwable $error) {
        if ($venuePdo->inTransaction()) {
            $venuePdo->rollBack();
        }
        error_log('Nearby venues could not be synchronized: ' . $error->getMessage());
        respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 503);
    }
    $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        error_log('Nearby places API could not encode its result');
        respond(['success' => false, 'error' => 'Nearby places are temporarily unavailable'], 500);
    }
    if (@file_put_contents($cachePath, $encoded, LOCK_EX) === false) {
        error_log('Nearby places API could not cache its result');
    }
    respond_with_venue_data($result);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
