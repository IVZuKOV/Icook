<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

session_destroy();

json_out(['success' => true]);
