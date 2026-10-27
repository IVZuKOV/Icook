<?php
declare(strict_types=1);

// Совместимость: старые скрипты подключают db.php.
// Прямой запрос к этому файлу из браузера запрещён.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

require_once '/etc/icook/config.php';
