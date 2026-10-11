<?php
/**
 * Подключение к базе данных.
 *
 * Параметры берутся из переменных окружения (docker-compose.yml / .env).
 * Значения по умолчанию совпадают с настройками docker-compose, чтобы
 * локальный запуск работал «из коробки». Секретов в коде НЕТ.
 */
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'icook_db';
$user = getenv('DB_USER') ?: 'icook';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Ошибка подключения к БД']));
}
