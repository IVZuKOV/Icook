<?php
declare(strict_types=1);

function venue_database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    global $host, $user, $pass;
    if (!isset($host, $user, $pass)) {
        require_once '/etc/icook/config.php';
        global $host, $user, $pass;
    }

    try {
        $connection = new PDO(
            'mysql:host=' . $host . ';dbname=icook_venues;charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $error) {
        error_log('Venue database connection failed: ' . $error->getMessage());
        throw new RuntimeException('Venue data is temporarily unavailable', 0, $error);
    }

    return $connection;
}

/**
 * Validate and split a stable OSM identifier such as "way:12345".
 *
 * @return array{0: string, 1: int}|null
 */
function parse_venue_id(mixed $value): ?array
{
    if (!is_string($value) || !preg_match('/\A(node|way|relation):([1-9][0-9]{0,19})\z/', $value, $matches)) {
        return null;
    }

    if (strlen($matches[2]) > 19 || (strlen($matches[2]) === 19 && strcmp($matches[2], '9223372036854775807') > 0)) {
        return null;
    }

    return [$matches[1], (int) $matches[2]];
}

function is_venue_admin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $statement = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $statement->execute([(int) $_SESSION['user_id']]);
    return in_array((string) $statement->fetchColumn(), ['admin', 'superuser'], true);
}
