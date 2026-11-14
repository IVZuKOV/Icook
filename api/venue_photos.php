<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/venue_store.php';

header('Cache-Control: private, no-store');

const VENUE_PHOTO_MAX_UPLOAD = 10 * 1024 * 1024;
const VENUE_PHOTO_MAX_STORED = 50 * 1024;
const VENUE_PHOTO_PENDING_DIR = '/var/www/private/icook/venue-pending';
const VENUE_PHOTO_PUBLIC_DIR = __DIR__ . '/../assets/images/REST';

final class VenuePhotoException extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $message, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

function photo_error(string $message, int $status = 400): never
{
    json_out(['success' => false, 'error' => $message], $status);
}

function photo_dimensions(int $width, int $height): array
{
    $scale = min(1, 1200 / max($width, $height));
    return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
}

function compress_venue_photo(string $sourceData, string $destination): int
{
    $imageInfo = @getimagesizefromstring($sourceData);
    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if ($imageInfo === false || !in_array($imageInfo[2] ?? null, $allowedTypes, true)) {
        throw new VenuePhotoException('Поддерживаются изображения JPEG, PNG и WebP');
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || $width * $height > 20000000) {
        throw new VenuePhotoException('Разрешение фото слишком большое', 413);
    }
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        error_log('Venue photo compression is unavailable: GD image functions are missing');
        throw new VenuePhotoException('Обработка фотографий временно недоступна', 503);
    }

    $source = @imagecreatefromstring($sourceData);
    if ($source === false) {
        throw new VenuePhotoException('Файл не является корректным изображением');
    }
    [$targetWidth, $targetHeight] = photo_dimensions($width, $height);
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($target === false) {
        imagedestroy($source);
        throw new VenuePhotoException('Не удалось обработать фото', 500);
    }
    imagealphablending($target, true);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($source);

    $compressed = false;
    $dimensions = [];
    for ($dimension = max($targetWidth, $targetHeight); $dimension > 32; $dimension = max(32, (int) floor($dimension * 0.75))) {
        if (!in_array($dimension, $dimensions, true)) {
            $dimensions[] = $dimension;
        }
    }
    $dimensions[] = 32;
    foreach ($dimensions as $dimension) {
        $scale = min(1, $dimension / max($targetWidth, $targetHeight));
        $resizeWidth = max(1, (int) round($targetWidth * $scale));
        $resizeHeight = max(1, (int) round($targetHeight * $scale));
        $resized = imagecreatetruecolor($resizeWidth, $resizeHeight);
        if ($resized === false) {
            continue;
        }
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $target, 0, 0, 0, 0, $resizeWidth, $resizeHeight, $targetWidth, $targetHeight);
        foreach ([82, 70, 58, 46, 34, 22, 12, 5] as $quality) {
            $written = imagejpeg($resized, $destination, $quality);
            clearstatcache(true, $destination);
            if ($written && filesize($destination) <= VENUE_PHOTO_MAX_STORED) {
                $compressed = true;
                break;
            }
        }
        imagedestroy($resized);
        if ($compressed) {
            break;
        }
    }
    imagedestroy($target);

    if (!$compressed) {
        @unlink($destination);
        throw new VenuePhotoException('Не удалось сжать фото до 50 КБ. Выберите другое изображение.');
    }
    clearstatcache(true, $destination);
    return (int) filesize($destination);
}

function official_site_image(string $url, string $venueWebsite): string
{
    if (!function_exists('curl_init')) {
        photo_error('Загрузка фото с сайта временно недоступна', 503);
    }
    $imageParts = parse_url($url);
    $siteParts = parse_url($venueWebsite);
    $host = strtolower((string) ($imageParts['host'] ?? ''));
    $siteHost = strtolower((string) ($siteParts['host'] ?? ''));
    if (($imageParts['scheme'] ?? '') !== 'https'
        || $host === ''
        || $siteHost === ''
        || isset($imageParts['user'])
        || isset($imageParts['pass'])
        || (isset($imageParts['port']) && (int) $imageParts['port'] !== 443)
        || !($host === $siteHost || str_ends_with($host, '.' . $siteHost))) {
        photo_error('Укажите HTTPS-ссылку на изображение с официального сайта заведения');
    }

    $addresses = gethostbynamel($host);
    if (!$addresses) {
        photo_error('Не удалось проверить адрес сайта заведения');
    }
    $publicAddress = null;
    foreach ($addresses as $address) {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $publicAddress = $address;
            break;
        }
    }
    if ($publicAddress === null) {
        photo_error('Адрес сайта не разрешён для загрузки');
    }

    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_RESOLVE => [$host . ':443:' . $publicAddress],
        CURLOPT_USERAGENT => 'iCookVenuePhotoReview/1.0 (+https://icook.com.ru/)',
        CURLOPT_HTTPHEADER => ['Accept: image/jpeg,image/png,image/webp'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > VENUE_PHOTO_MAX_UPLOAD) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $downloaded = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($downloaded === false || $status !== 200 || !str_starts_with($contentType, 'image/')) {
        error_log('Official venue photo download failed: HTTP ' . $status . ' ' . $curlError);
        photo_error('Не удалось получить изображение. Проверьте ссылку и права на его использование.', 422);
    }
    return $body;
}

function venue_photo_upload_source(PDO $venuePdo, int $userId, array $venue): array
{
    $file = $_FILES['photo'] ?? null;
    $sourceUrl = trim((string) ($_POST['source_url'] ?? ''));
    $fileUploaded = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($fileUploaded && $sourceUrl !== '') {
        photo_error('Загрузите файл или укажите ссылку, но не оба источника сразу');
    }

    if ($fileUploaded) {
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > VENUE_PHOTO_MAX_UPLOAD) {
            photo_error('Исходное фото должно быть не больше 10 МБ', 413);
        }
        $contents = file_get_contents((string) $file['tmp_name']);
        if ($contents === false) {
            photo_error('Не удалось прочитать загруженный файл', 400);
        }
        return [$contents, 'upload', null];
    }

    if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        photo_error('Не удалось получить фотографию', 400);
    }
    if ($sourceUrl === '' || strlen($sourceUrl) > 2048) {
        photo_error('Выберите фото или укажите ссылку на изображение с официального сайта');
    }
    if (filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
        photo_error('Некорректная ссылка на изображение');
    }
    if ($venue['website'] === '') {
        photo_error('Для этого заведения не указан официальный сайт. Загрузите фото файлом.');
    }
    return [official_site_image($sourceUrl, (string) $venue['website']), 'official_site', $sourceUrl];
}

function require_photo_admin(PDO $pdo): int
{
    $userId = require_login();
    if (!is_venue_admin($pdo)) {
        photo_error('Нет доступа', 403);
    }
    return $userId;
}

try {
    $venuePdo = venue_database();
} catch (Throwable $error) {
    error_log('Venue photo API database initialization failed: ' . $error->getMessage());
    photo_error('Фотографии заведений временно недоступны', 503);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET') {
    if ($action === 'preview') {
        require_photo_admin($pdo);
        $photoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$photoId || $photoId < 1) {
            photo_error('Фото не найдено', 404);
        }
        $statement = $venuePdo->prepare(
            "SELECT pending_filename FROM venue_photos WHERE id = ? AND status = 'pending'"
        );
        $statement->execute([$photoId]);
        $filename = $statement->fetchColumn();
        if (!is_string($filename) || !preg_match('/\Apending_[a-f0-9]{24}\.jpg\z/', $filename)) {
            photo_error('Фото не найдено', 404);
        }
        $path = VENUE_PHOTO_PENDING_DIR . '/' . $filename;
        if (!is_file($path)) {
            photo_error('Файл фото не найден', 404);
        }
        header_remove('Content-Type');
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    if ($action === 'pending') {
        require_photo_admin($pdo);
        $rows = $venuePdo->query(
            "SELECT p.id, p.osm_type, p.osm_id, p.user_id, p.source_type, p.source_url,
                    p.created_at, v.name AS venue_name, v.address
             FROM venue_photos p
             JOIN venues v ON v.osm_type = p.osm_type AND v.osm_id = p.osm_id
             WHERE p.status = 'pending'
             ORDER BY p.created_at ASC LIMIT 100"
        )->fetchAll();
        json_out(['success' => true, 'photos' => $rows]);
    }

    if ($action === 'mine') {
        $userId = require_login();
        $statement = $venuePdo->prepare(
            'SELECT p.id, p.osm_type, p.osm_id, p.source_type, p.status, p.created_at,
                    v.name AS venue_name
             FROM venue_photos p
             JOIN venues v ON v.osm_type = p.osm_type AND v.osm_id = p.osm_id
             WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 50'
        );
        $statement->execute([$userId]);
        json_out(['success' => true, 'photos' => $statement->fetchAll()]);
    }

    header('Allow: GET, POST');
    photo_error('Метод не поддерживается', 405);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    photo_error('Метод не поддерживается', 405);
}

$data = body_json();
$action = (string) ($data['action'] ?? $_POST['action'] ?? '');

if ($action === 'submit') {
    $userId = require_login();
    if (!rate_limit($pdo, 'venue-photo:' . $userId, 10, 86400)) {
        photo_error('Слишком много загрузок. Попробуйте позже.', 429);
    }
    if ((string) ($_POST['rights_confirm'] ?? '') !== '1') {
        photo_error('Подтвердите, что у вас есть право передать это фото на публикацию');
    }
    $venueId = parse_venue_id($_POST['venue_id'] ?? null);
    if ($venueId === null) {
        photo_error('Не удалось определить заведение');
    }

    $statement = $venuePdo->prepare('SELECT name, address, website FROM venues WHERE osm_type = ? AND osm_id = ?');
    $statement->execute($venueId);
    $venue = $statement->fetch();
    if (!$venue) {
        photo_error('Заведение не найдено. Обновите список заведений.', 404);
    }

    $statement = $venuePdo->prepare(
        "SELECT id FROM venue_photos
         WHERE osm_type = ? AND osm_id = ? AND user_id = ? AND status = 'pending' LIMIT 1"
    );
    $statement->execute([$venueId[0], $venueId[1], $userId]);
    if ($statement->fetchColumn()) {
        photo_error('Ваше фото этого заведения уже ожидает проверки', 409);
    }

    [$sourceData, $sourceType, $sourceUrl] = venue_photo_upload_source($venuePdo, $userId, $venue);
    if (!is_dir(VENUE_PHOTO_PENDING_DIR)
        && !mkdir(VENUE_PHOTO_PENDING_DIR, 0750, true)
        && !is_dir(VENUE_PHOTO_PENDING_DIR)) {
        error_log('Venue photo upload failed: cannot create pending storage');
        photo_error('Не удалось сохранить фото', 500);
    }
    $temporaryPath = tempnam(VENUE_PHOTO_PENDING_DIR, '.venue-photo-');
    if ($temporaryPath === false) {
        error_log('Venue photo upload failed: cannot create temporary file');
        photo_error('Не удалось сохранить фото', 500);
    }
    try {
        $storedSize = compress_venue_photo($sourceData, $temporaryPath);
    } catch (VenuePhotoException $error) {
        @unlink($temporaryPath);
        photo_error($error->getMessage(), $error->httpStatus);
    } catch (Throwable $error) {
        @unlink($temporaryPath);
        error_log('Venue photo compression failed: ' . $error->getMessage());
        photo_error('Не удалось обработать фото', 500);
    }
    unset($sourceData);
    $filename = 'pending_' . bin2hex(random_bytes(12)) . '.jpg';
    $pendingPath = VENUE_PHOTO_PENDING_DIR . '/' . $filename;
    if (!rename($temporaryPath, $pendingPath) || !chmod($pendingPath, 0640)) {
        @unlink($temporaryPath);
        @unlink($pendingPath);
        error_log('Venue photo upload failed: cannot publish pending photo');
        photo_error('Не удалось сохранить фото', 500);
    }

    try {
        $statement = $venuePdo->prepare(
            "INSERT INTO venue_photos
                (osm_type, osm_id, user_id, source_type, source_url, pending_filename, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );
        $statement->execute([$venueId[0], $venueId[1], $userId, $sourceType, $sourceUrl, $filename]);
    } catch (Throwable $error) {
        @unlink($pendingPath);
        error_log('Venue photo submission failed: ' . $error->getMessage());
        photo_error('Не удалось отправить фото на проверку', 500);
    }
    json_out(['success' => true, 'message' => 'Фото отправлено на проверку', 'size_bytes' => $storedSize]);
}

if ($action === 'review') {
    $adminId = require_photo_admin($pdo);
    $photoId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $decision = (string) ($data['decision'] ?? '');
    if (!$photoId || $photoId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
        photo_error('Некорректное действие', 400);
    }

    $venuePdo->beginTransaction();
    $publishedPath = null;
    $pendingPath = null;
    try {
        $statement = $venuePdo->prepare(
            "SELECT id, osm_type, osm_id, pending_filename
             FROM venue_photos WHERE id = ? AND status = 'pending' FOR UPDATE"
        );
        $statement->execute([$photoId]);
        $photo = $statement->fetch();
        if (!$photo) {
            $venuePdo->rollBack();
            photo_error('Фото уже проверено или не найдено', 404);
        }
        $pendingFilename = (string) $photo['pending_filename'];
        if (!preg_match('/\Apending_[a-f0-9]{24}\.jpg\z/', $pendingFilename)) {
            throw new RuntimeException('Invalid pending venue photo filename');
        }
        $pendingPath = VENUE_PHOTO_PENDING_DIR . '/' . $pendingFilename;

        if ($decision === 'approve') {
            if (!is_file($pendingPath)) {
                throw new RuntimeException('Pending venue photo is missing');
            }
            if (!is_dir(VENUE_PHOTO_PUBLIC_DIR)
                && !mkdir(VENUE_PHOTO_PUBLIC_DIR, 0755, true)
                && !is_dir(VENUE_PHOTO_PUBLIC_DIR)) {
                throw new RuntimeException('Cannot create public venue photo directory');
            }
            $publishedFilename = sprintf(
                'venue_%s_%d_%d_%s.jpg',
                $photo['osm_type'],
                (int) $photo['osm_id'],
                $photoId,
                bin2hex(random_bytes(4))
            );
            $publishedPath = VENUE_PHOTO_PUBLIC_DIR . '/' . $publishedFilename;
            if (!rename($pendingPath, $publishedPath) || !chmod($publishedPath, 0644)) {
                throw new RuntimeException('Cannot publish approved venue photo');
            }
            $relativePath = 'assets/images/REST/' . $publishedFilename;
            $statement = $venuePdo->prepare(
                "UPDATE venue_photos
                 SET status = 'approved', public_path = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'pending'"
            );
            $statement->execute([$relativePath, $adminId, $photoId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Venue photo approval status was not updated');
            }
            $statement = $venuePdo->prepare(
                'UPDATE venues SET photo_path = ? WHERE osm_type = ? AND osm_id = ?'
            );
            $statement->execute([$relativePath, $photo['osm_type'], $photo['osm_id']]);
        } else {
            $statement = $venuePdo->prepare(
                "UPDATE venue_photos
                 SET status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'pending'"
            );
            $statement->execute([$adminId, $photoId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Venue photo rejection status was not updated');
            }
        }
        $venuePdo->commit();
    } catch (Throwable $error) {
        if ($venuePdo->inTransaction()) {
            $venuePdo->rollBack();
        }
        if ($publishedPath !== null && is_file($publishedPath) && $pendingPath !== null) {
            if (!@rename($publishedPath, $pendingPath)) {
                error_log('Venue photo moderation failed to restore the pending file');
            }
        }
        error_log('Venue photo moderation failed: ' . $error->getMessage());
        photo_error('Не удалось обработать фото', 500);
    }

    if ($decision === 'reject' && $pendingPath !== null) {
        if (is_file($pendingPath) && !@unlink($pendingPath)) {
            error_log('Venue photo moderation could not remove a rejected pending file');
        }
    }
    json_out(['success' => true]);
}

photo_error('Неизвестное действие', 400);
