<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

$userId = require_login();
if (!rate_limit($pdo, 'avatar-upload:' . $userId, 10, 3600)) {
    json_out(['success' => false, 'error' => 'Слишком много попыток. Попробуйте позже.'], 429);
}

$file = $_FILES['avatar'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(['success' => false, 'error' => 'Не удалось получить фотографию'], 400);
}
if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 10 * 1024 * 1024) {
    json_out(['success' => false, 'error' => 'Размер исходного фото должен быть не больше 10 МБ'], 413);
}

$imageInfo = @getimagesize((string) $file['tmp_name']);
$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if ($imageInfo === false || !in_array($imageInfo[2] ?? null, $allowedTypes, true)) {
    json_out(['success' => false, 'error' => 'Поддерживаются изображения JPEG, PNG и WebP'], 400);
}

$width = (int) $imageInfo[0];
$height = (int) $imageInfo[1];
if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || $width * $height > 20000000) {
    json_out(['success' => false, 'error' => 'Разрешение фото слишком большое'], 413);
}
if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
    error_log('Avatar upload unavailable: GD image functions are missing');
    json_out(['success' => false, 'error' => 'На сервере не настроена обработка изображений'], 503);
}

$sourceData = file_get_contents((string) $file['tmp_name']);
$source = $sourceData === false ? false : @imagecreatefromstring($sourceData);
unset($sourceData);
if ($source === false) {
    json_out(['success' => false, 'error' => 'Файл не является корректным изображением'], 400);
}

$side = min($width, $height);
$targetDimension = min(512, $side);
$target = imagecreatetruecolor($targetDimension, $targetDimension);
if ($target === false) {
    imagedestroy($source);
    error_log('Avatar upload failed: unable to allocate target image');
    json_out(['success' => false, 'error' => 'Не удалось обработать фото'], 500);
}
imagealphablending($target, true);
$white = imagecolorallocate($target, 255, 255, 255);
imagefill($target, 0, 0, $white);
$side = min($width, $height);
imagecopyresampled(
    $target,
    $source,
    0,
    0,
    (int) floor(($width - $side) / 2),
    (int) floor(($height - $side) / 2),
    $targetDimension,
    $targetDimension,
    $side,
    $side
);
imagedestroy($source);

$targetDir = dirname(__DIR__) . '/uploads/avatars';
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    imagedestroy($target);
    error_log('Avatar upload failed: cannot create avatar storage directory');
    json_out(['success' => false, 'error' => 'Не удалось сохранить фото'], 500);
}

$tempPath = tempnam($targetDir, '.avatar_');
if ($tempPath === false) {
    imagedestroy($target);
    error_log('Avatar upload failed: cannot create temporary file');
    json_out(['success' => false, 'error' => 'Не удалось сохранить фото'], 500);
}

$maxBytes = 50 * 1024;
$compressed = false;
$dimensions = [$targetDimension];
for ($dimension = $targetDimension; $dimension > 32; $dimension = max(32, (int) floor($dimension * 0.75))) {
    $nextDimension = max(32, (int) floor($dimension * 0.75));
    if ($nextDimension < $dimension && !in_array($nextDimension, $dimensions, true)) {
        $dimensions[] = $nextDimension;
    }
}
if (!in_array(32, $dimensions, true)) {
    $dimensions[] = 32;
}

foreach ($dimensions as $dimension) {
    $small = $dimension === $targetDimension
        ? $target
        : imagecreatetruecolor($dimension, $dimension);
    if ($small === false) {
        continue;
    }
    if ($small !== $target) {
        imagealphablending($small, true);
        $smallWhite = imagecolorallocate($small, 255, 255, 255);
        imagefill($small, 0, 0, $smallWhite);
        imagecopyresampled($small, $target, 0, 0, 0, 0, $dimension, $dimension, $targetDimension, $targetDimension);
    }
    foreach ([85, 75, 65, 55, 45, 35, 25, 15, 8, 2] as $quality) {
        $written = imagejpeg($small, $tempPath, $quality);
        clearstatcache(true, $tempPath);
        if ($written && filesize($tempPath) <= $maxBytes) {
            $compressed = true;
            break;
        }
    }
    if ($small !== $target) {
        imagedestroy($small);
    }
    if ($compressed) {
        break;
    }
}
imagedestroy($target);

if (!$compressed) {
    unlink($tempPath);
    json_out(['success' => false, 'error' => 'Не удалось сжать фото до 50 КБ. Выберите другое изображение.'], 422);
}

$filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(6)) . '.jpg';
$absolutePath = $targetDir . '/' . $filename;
$avatarPath = 'uploads/avatars/' . $filename;
clearstatcache(true, $tempPath);
$sizeBytes = filesize($tempPath);
if (!rename($tempPath, $absolutePath)) {
    unlink($tempPath);
    error_log('Avatar upload failed: cannot publish compressed image');
    json_out(['success' => false, 'error' => 'Не удалось сохранить фото'], 500);
}
if (!chmod($absolutePath, 0644)) {
    unlink($absolutePath);
    error_log('Avatar upload failed: cannot set image permissions');
    json_out(['success' => false, 'error' => 'Не удалось сохранить фото'], 500);
}

try {
    $avatarVersion = bin2hex(random_bytes(8));
    $stmt = $pdo->prepare('UPDATE users SET avatar = ?, avatar_version = ? WHERE id = ?');
    $stmt->execute([$avatarPath, $avatarVersion, $userId]);
} catch (PDOException $error) {
    unlink($absolutePath);
    error_log('Avatar profile update failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Фото обработано, но не удалось сохранить его в профиле'], 500);
}

echo json_encode([
    'success' => true,
    'avatar' => $avatarPath,
    'size_kb' => round($sizeBytes / 1024, 1),
    'avatar_version' => $avatarVersion,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
