<?php
declare(strict_types=1);

/**
 * Общий загрузчик API: подключение к БД, безопасная сессия, JSON-заголовки,
 * проверка Origin (CSRF-защита) и вспомогательные функции.
 */

// Конфиг БД лежит вне веб-рута (/etc/icook/config.php).
require_once '/etc/icook/config.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * IP клиента. nginx восстанавливает реальный адрес через модуль realip:
 * заголовок CF-Connecting-IP принимается только от loopback (Cloudflare
 * Tunnel), прямые подключения с интернета сохраняют свой REMOTE_ADDR.
 * Поэтому здесь всегда настоящий IP клиента.
 */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function is_unsafe_method(): bool
{
    return in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

/**
 * CSRF-защита. Браузер при cross-site POST всегда присылает Origin,
 * поэтому если Origin/Referer указан и не совпадает с нашим доменом — отказ.
 * Запросы без Origin (curl, мобильные клиенты) пропускаем: чужую сессию
 * они всё равно не получат из-за SameSite=Strict.
 */
function csrf_guard(): void
{
    if (!is_unsafe_method()) {
        return;
    }
    $source = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($source === '') {
        $source = $_SERVER['HTTP_REFERER'] ?? '';
    }
    if ($source === '') {
        return;
    }
    $host = strtolower((string) parse_url($source, PHP_URL_HOST));
    if (in_array($host, ['icook.com.ru', 'www.icook.com.ru'], true)) {
        return;
    }
    json_out(['success' => false, 'error' => 'Запрос отклонён'], 403);
}

csrf_guard();

/** Ограничение частоты запросов через таблицу rate_limits. */
function rate_limit(PDO $pdo, string $bucket, int $maxHits, int $windowSeconds): bool
{
    $key = hash('sha256', $bucket);
    $expires = (new DateTimeImmutable('+' . $windowSeconds . ' seconds'))->format('Y-m-d H:i:s');
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (bucket, hits, expires_at)
             VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
                hits = IF(expires_at IS NULL OR expires_at < NOW(), 1, hits + 1),
                expires_at = IF(expires_at IS NULL OR expires_at < NOW(), ?, expires_at)'
        );
        $stmt->execute([$key, $expires, $expires]);

        $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ?');
        $stmt->execute([$key]);
        $hits = (int) ($stmt->fetchColumn() ?: 0);

        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM rate_limits WHERE expires_at < NOW()');
        }
        return $hits <= $maxHits;
    } catch (Throwable $e) {
        error_log('rate_limit failed: ' . $e->getMessage());
        return true; // служебная таблица не должна ломать сайт
    }
}

function require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        json_out(['success' => false, 'error' => 'Не авторизован'], 401);
    }
    return (int) $_SESSION['user_id'];
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, name, email, phone, role, is_seller, seller_description,
                country, city, address, lat, lng, search_radius, lang
         FROM users WHERE id = ?'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_admin(PDO $pdo): array
{
    require_login();
    $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !in_array((string) $user['role'], ['admin', 'superuser'], true)) {
        json_out(['success' => false, 'error' => 'Нет доступа'], 403);
    }
    return $user;
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Проверяет, что загруженный файл действительно изображение JPG/PNG/WEBP.
 * Возвращает безопасное расширение.
 */
function validate_image_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ошибка загрузки файла');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Фото максимум 5 МБ');
    }
    $info = @getimagesize((string) $file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Только JPG, PNG, WEBP');
    }
    return $allowed[$info[2]];
}

/** Сохраняет изображение под случайным именем, возвращает публичный путь. */
function save_dish_image(array $file): string
{
    $ext = validate_image_upload($file);
    $dir = '/var/www/html/icook/uploads/dishes/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось сохранить файл');
    }
    $filename = 'dish_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . $filename)) {
        throw new RuntimeException('Не удалось сохранить файл');
    }
    @chmod($dir . $filename, 0644);
    return '/uploads/dishes/' . $filename;
}
