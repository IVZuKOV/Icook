<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ingredients.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}
$query = $_GET['q'] ?? '';
if (!is_string($query) || mb_strlen($query) > 100) {
    json_out(['success' => false, 'error' => 'Поиск должен содержать не больше 100 символов'], 400);
}
$query = trim($query);
if (!rate_limit($pdo, 'ingredient-search:' . client_ip(), 120, 60)) {
    json_out(['success' => false, 'error' => 'Слишком много запросов. Попробуйте немного позже.'], 429);
}
try {
    json_out(['success' => true, 'ingredients' => ingredient_catalog_suggestions($pdo, $query)]);
} catch (InvalidArgumentException $error) {
    json_out(['success' => false, 'error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    error_log('Ingredient suggestions failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Подсказки временно недоступны. Можно ввести название самостоятельно.'], 503);
}
