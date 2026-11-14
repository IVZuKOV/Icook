<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function save_joint_project_image(array $file): string
{
    $extension = validate_image_upload($file);
    $imageInfo = @getimagesize((string) $file['tmp_name']);
    if ($imageInfo === false || $imageInfo[0] !== 256 || $imageInfo[1] !== 256) {
        throw new RuntimeException('Лейбл должен быть квадратным изображением 256 × 256 пикселей');
    }

    $directory = '/var/www/html/icook/uploads/joint-projects/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать каталог для логотипов проектов');
    }

    $filename = 'project_' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . $filename)) {
        throw new RuntimeException('Не удалось сохранить логотип проекта');
    }
    @chmod($directory . $filename, 0644);
    return '/uploads/joint-projects/' . $filename;
}

function delete_joint_project_image(?string $path): void
{
    if ($path === null || !preg_match('#^/uploads/joint-projects/[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#D', $path)) {
        return;
    }
    $file = '/var/www/html/icook' . $path;
    if (is_file($file) && !unlink($file)) {
        error_log('Unable to remove a joint project image');
    }
}

function save_edu_em_outlet_image(array $file): string
{
    $extension = validate_image_upload($file);
    $imageInfo = @getimagesize((string) $file['tmp_name']);
    if ($imageInfo === false || $imageInfo[0] !== 256 || $imageInfo[1] !== 256) {
        throw new RuntimeException('Лейбл должен быть квадратным изображением 256 × 256 пикселей');
    }

    $directory = '/var/www/html/icook/uploads/edu-em-outlets/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать каталог для лейблов торговых точек');
    }

    $filename = 'outlet_' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . $filename)) {
        throw new RuntimeException('Не удалось сохранить лейбл торговой точки');
    }
    if (!chmod($directory . $filename, 0644)) {
        error_log('Unable to set permissions on an outlet label');
    }
    return '/uploads/edu-em-outlets/' . $filename;
}

function delete_edu_em_outlet_image(?string $path): void
{
    if ($path === null || !preg_match('#^/uploads/edu-em-outlets/[A-Za-z0-9_-]+\.(?:jpg|png|webp)$#D', $path)) {
        return;
    }
    $file = '/var/www/html/icook' . $path;
    if (is_file($file) && !unlink($file)) {
        error_log('Unable to remove an outlet label');
    }
}

function valid_project_url(string $url): bool
{
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\r\n]/', $url)) {
        return false;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return true;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET') {
    if ($action === '' || $action === 'public') {
        $stmt = $pdo->query(
            'SELECT id, slug, name, project_url, label_image
             FROM joint_projects WHERE is_active = 1 ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'projects' => $stmt->fetchAll()]);
    }
    if ($action === 'public_outlets') {
        $stmt = $pdo->query(
            'SELECT id, name, website_url, label_image
             FROM edu_em_outlets WHERE is_active = 1 ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'outlets' => $stmt->fetchAll()]);
    }

    require_admin($pdo);
    if ($action === 'outlets') {
        $stmt = $pdo->query(
            'SELECT id, name, website_url, label_image, is_active, sort_order
             FROM edu_em_outlets ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'outlets' => $stmt->fetchAll()]);
    }
    if ($action === 'admin') {
        $stmt = $pdo->query(
            'SELECT id, slug, name, project_url, label_image, is_active, sort_order
             FROM joint_projects ORDER BY sort_order, id'
        );
        json_out(['success' => true, 'projects' => $stmt->fetchAll()]);
    }
    if ($action === 'requests') {
        $stmt = $pdo->query(
            'SELECT id, delivery_type, customer_name, phone, route, station, delivery_local_time,
                    outlet_id, outlet_name, carriage_seat, order_details, comment, status, created_at
             FROM edu_em_requests ORDER BY id DESC LIMIT 250'
        );
        json_out(['success' => true, 'requests' => $stmt->fetchAll()]);
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
    $name = trim(strip_tags((string) ($input['name'] ?? '')));
    $url = trim((string) ($input['url'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        json_out(['success' => false, 'error' => 'Название проекта должно быть от 2 до 120 символов'], 422);
    }
    if (!valid_project_url($url)) {
        json_out(['success' => false, 'error' => 'Укажите корректную ссылку проекта'], 422);
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, label_image FROM joint_projects WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            json_out(['success' => false, 'error' => 'Проект не найден'], 404);
        }
    }

    $imagePath = $existing['label_image'] ?? null;
    $newImagePath = null;
    if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImagePath = save_joint_project_image($_FILES['image']);
            $imagePath = $newImagePath;
        } catch (Throwable $error) {
            json_out(['success' => false, 'error' => $error->getMessage()], 422);
        }
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE joint_projects SET name = ?, project_url = ?, label_image = ? WHERE id = ?'
            );
            $stmt->execute([$name, $url, $imagePath, $id]);
        } else {
            $slug = 'project-' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare(
                'INSERT INTO joint_projects (slug, name, project_url, label_image, is_active, sort_order)
                 VALUES (?, ?, ?, ?, 1, 100)'
            );
            $stmt->execute([$slug, $name, $url, $imagePath]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $error) {
        if ($newImagePath !== null) {
            delete_joint_project_image($newImagePath);
        }
        error_log('Saving joint project failed: ' . $error->getMessage());
        json_out(['success' => false, 'error' => 'Не удалось сохранить проект'], 500);
    }

    if ($newImagePath !== null && !empty($existing['label_image'])) {
        delete_joint_project_image((string) $existing['label_image']);
    }
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'save_outlet') {
    $id = max(0, (int) ($input['id'] ?? 0));
    $name = trim(strip_tags((string) ($input['name'] ?? '')));
    $url = trim((string) ($input['url'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        json_out(['success' => false, 'error' => 'Название торговой точки должно быть от 2 до 120 символов'], 422);
    }
    if (!valid_project_url($url) || str_starts_with($url, '/')) {
        json_out(['success' => false, 'error' => 'Укажите полный URL сайта торговой точки'], 422);
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, label_image FROM edu_em_outlets WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            json_out(['success' => false, 'error' => 'Торговая точка не найдена'], 404);
        }
    }

    $oldImagePath = $existing['label_image'] ?? null;
    $imagePath = $oldImagePath;
    $newImagePath = null;
    if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newImagePath = save_edu_em_outlet_image($_FILES['image']);
            $imagePath = $newImagePath;
        } catch (Throwable $error) {
            json_out(['success' => false, 'error' => $error->getMessage()], 422);
        }
    } elseif (($input['remove_image'] ?? '') === '1') {
        $imagePath = null;
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE edu_em_outlets SET name = ?, website_url = ?, label_image = ? WHERE id = ?'
            );
            $stmt->execute([$name, $url, $imagePath, $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO edu_em_outlets (name, website_url, label_image, is_active, sort_order)
                 VALUES (?, ?, ?, 1, 100)'
            );
            $stmt->execute([$name, $url, $imagePath]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $error) {
        if ($newImagePath !== null) {
            delete_edu_em_outlet_image($newImagePath);
        }
        error_log('Saving Еду ем outlet failed: ' . $error->getMessage());
        json_out(['success' => false, 'error' => 'Не удалось сохранить торговую точку'], 500);
    }

    if ($oldImagePath !== null && $oldImagePath !== $imagePath) {
        delete_edu_em_outlet_image((string) $oldImagePath);
    }
    json_out(['success' => true, 'id' => $id]);
}

if ($action === 'toggle_outlet') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        json_out(['success' => false, 'error' => 'Некорректный ID торговой точки'], 422);
    }
    $stmt = $pdo->prepare('UPDATE edu_em_outlets SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT 1 FROM edu_em_outlets WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            json_out(['success' => false, 'error' => 'Торговая точка не найдена'], 404);
        }
    }
    json_out(['success' => true]);
}

if ($action === 'delete_outlet') {
    $id = (int) ($input['id'] ?? 0);
    $find = $pdo->prepare('SELECT label_image FROM edu_em_outlets WHERE id = ?');
    $find->execute([$id]);
    $imagePath = $find->fetchColumn();
    if ($imagePath === false) {
        json_out(['success' => false, 'error' => 'Торговая точка не найдена'], 404);
    }
    $stmt = $pdo->prepare('DELETE FROM edu_em_outlets WHERE id = ?');
    $stmt->execute([$id]);
    delete_edu_em_outlet_image($imagePath === null ? null : (string) $imagePath);
    json_out(['success' => true]);
}

if ($action === 'toggle') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        json_out(['success' => false, 'error' => 'Некорректный ID проекта'], 422);
    }
    $stmt = $pdo->prepare('UPDATE joint_projects SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT 1 FROM joint_projects WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            json_out(['success' => false, 'error' => 'Проект не найден'], 404);
        }
    }
    json_out(['success' => true]);
}

if ($action === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT label_image FROM joint_projects WHERE id = ?');
    $stmt->execute([$id]);
    $imagePath = $stmt->fetchColumn();
    if ($imagePath === false) {
        json_out(['success' => false, 'error' => 'Проект не найден'], 404);
    }
    $pdo->prepare('DELETE FROM joint_projects WHERE id = ?')->execute([$id]);
    delete_joint_project_image($imagePath === null ? null : (string) $imagePath);
    json_out(['success' => true]);
}

if ($action === 'request_status') {
    $id = (int) ($input['id'] ?? 0);
    $status = (string) ($input['status'] ?? '');
    if ($id <= 0 || !in_array($status, ['new', 'processing', 'completed', 'cancelled'], true)) {
        json_out(['success' => false, 'error' => 'Некорректный статус заявки'], 422);
    }
    $stmt = $pdo->prepare('UPDATE edu_em_requests SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT 1 FROM edu_em_requests WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            json_out(['success' => false, 'error' => 'Заявка не найдена'], 404);
        }
    }
    json_out(['success' => true]);
}

json_out(['success' => false, 'error' => 'Действие не поддерживается'], 400);
