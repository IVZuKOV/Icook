<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function save_index_banner_image(array $file): string
{
    $extension = validate_image_upload($file);
    $imageInfo = @getimagesize((string) ($file['tmp_name'] ?? ''));
    if ($imageInfo === false || (int) $imageInfo[0] < 320 || (int) $imageInfo[1] < 100 || (int) $imageInfo[0] <= (int) $imageInfo[1] || (int) $imageInfo[0] > 12000 || (int) $imageInfo[1] > 12000) {
        throw new RuntimeException('Изображение баннера должно быть прямоугольным, альбомной ориентации (минимум 320 × 100)');
    }

    $directory = '/var/www/html/icook/uploads/index-banners/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать каталог изображений баннеров');
    }
    $filename = 'banner_' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . $filename)) {
        throw new RuntimeException('Не удалось сохранить изображение баннера');
    }
    @chmod($directory . $filename, 0644);
    return '/uploads/index-banners/' . $filename;
}

function delete_index_banner_image(?string $path): void
{
    if ($path === null || !preg_match('#^/uploads/index-banners/[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#D', $path)) {
        return;
    }
    $file = '/var/www/html/icook' . $path;
    if (is_file($file) && !unlink($file)) {
        error_log('Unable to remove an index banner image');
    }
}

function valid_index_banner_link(string $url): bool
{
    if ($url === '') {
        return true;
    }
    if (strlen($url) > 2048 || preg_match('/[\r\n]/', $url)) {
        return false;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return true;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'public');

if ($method === 'GET') {
    if ($action === 'public') {
        $stmt = $pdo->query(
            'SELECT id, tag, title, description, link, image_path, show_tag, show_title, show_description
             FROM index_banners WHERE is_active = 1 ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'banners' => $stmt->fetchAll()]);
    }
    if ($action === 'admin') {
        require_admin($pdo);
        $stmt = $pdo->query(
            'SELECT id, tag, title, description, link, image_path, show_tag, show_title, show_description, is_active, sort_order
             FROM index_banners ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'banners' => $stmt->fetchAll()]);
    }
    json_out(['success' => false, 'error' => 'Маршрут не найден'], 404);
}

if ($method !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

require_admin($pdo);
$input = str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')
    ? body_json()
    : $_POST;
$action = (string) ($input['action'] ?? '');

if ($action === 'save') {
    $id = max(0, (int) ($input['id'] ?? 0));
    $tag = trim(strip_tags((string) ($input['tag'] ?? '')));
    $title = trim(strip_tags((string) ($input['title'] ?? '')));
    $description = trim(strip_tags((string) ($input['description'] ?? '')));
    $link = trim((string) ($input['link'] ?? ''));
    $showTag = (int) ($input['show_tag'] ?? 1) === 1 ? 1 : 0;
    $showTitle = (int) ($input['show_title'] ?? 1) === 1 ? 1 : 0;
    $showDescription = (int) ($input['show_description'] ?? 1) === 1 ? 1 : 0;
    if (mb_strlen($tag) > 40 || mb_strlen($title) < 2 || mb_strlen($title) > 120 || mb_strlen($description) > 500) {
        json_out(['success' => false, 'error' => 'Проверьте длину подписи, заголовка и описания баннера'], 422);
    }
    if (!valid_index_banner_link($link)) {
        json_out(['success' => false, 'error' => 'Укажите корректную ссылку: http(s)://… или /страница.html'], 422);
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, image_path FROM index_banners WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            json_out(['success' => false, 'error' => 'Баннер не найден'], 404);
        }
    }

    $imagePath = $existing['image_path'] ?? null;
    $newImagePath = null;
    if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImagePath = save_index_banner_image($_FILES['image']);
            $imagePath = $newImagePath;
        } catch (Throwable $error) {
            json_out(['success' => false, 'error' => $error->getMessage()], 422);
        }
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE index_banners SET tag = ?, title = ?, description = ?, link = ?, image_path = ?, show_tag = ?, show_title = ?, show_description = ? WHERE id = ?'
            );
            $stmt->execute([$tag !== '' ? $tag : null, $title, $description, $link !== '' ? $link : null, $imagePath, $showTag, $showTitle, $showDescription, $id]);
        } else {
            $slug = 'banner-' . bin2hex(random_bytes(8));
            $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM index_banners')->fetchColumn();
            $stmt = $pdo->prepare(
                'INSERT INTO index_banners (slug, tag, title, description, link, image_path, show_tag, show_title, show_description, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([$slug, $tag !== '' ? $tag : null, $title, $description, $link !== '' ? $link : null, $imagePath, $showTag, $showTitle, $showDescription, $sortOrder]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $error) {
        if ($newImagePath !== null) {
            delete_index_banner_image($newImagePath);
        }
        error_log('Saving index banner failed: ' . $error->getMessage());
        json_out(['success' => false, 'error' => 'Не удалось сохранить баннер'], 500);
    }

    if ($newImagePath !== null && !empty($existing['image_path'])) {
        delete_index_banner_image((string) $existing['image_path']);
    }
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'toggle') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        json_out(['success' => false, 'error' => 'Некорректный ID баннера'], 422);
    }
    $stmt = $pdo->prepare('UPDATE index_banners SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT 1 FROM index_banners WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            json_out(['success' => false, 'error' => 'Баннер не найден'], 404);
        }
    }
    json_out(['success' => true]);
}

if ($action === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT image_path FROM index_banners WHERE id = ?');
    $stmt->execute([$id]);
    $imagePath = $stmt->fetchColumn();
    if ($imagePath === false) {
        json_out(['success' => false, 'error' => 'Баннер не найден'], 404);
    }
    $pdo->prepare('DELETE FROM index_banners WHERE id = ?')->execute([$id]);
    delete_index_banner_image($imagePath === null ? null : (string) $imagePath);
    json_out(['success' => true]);
}

json_out(['success' => false, 'error' => 'Неизвестное действие'], 400);
