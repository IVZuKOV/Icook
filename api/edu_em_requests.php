<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['success' => false, 'error' => 'Метод не поддерживается'], 405);
}

if (!rate_limit($pdo, 'edu-em-request:' . client_ip(), 5, 900)) {
    json_out(['success' => false, 'error' => 'Слишком много заявок. Попробуйте позже.'], 429);
}

$data = body_json();
$deliveryType = (string) ($data['delivery_type'] ?? '');
$name = trim(strip_tags((string) ($data['name'] ?? '')));
$phone = preg_replace('/[\s().-]+/u', '', trim((string) ($data['phone'] ?? ''))) ?? '';
$route = trim(strip_tags((string) ($data['route'] ?? '')));
$station = trim(strip_tags((string) ($data['station'] ?? '')));
$outletId = (int) ($data['outlet_id'] ?? 0);
$deliveryTime = trim((string) ($data['delivery_local_time'] ?? ''));
$carriageSeat = trim(strip_tags((string) ($data['carriage_seat'] ?? '')));
$orderDetails = trim(strip_tags((string) ($data['order_details'] ?? '')));
$comment = trim(strip_tags((string) ($data['comment'] ?? '')));
$consent = ($data['consent'] ?? false) === true || ($data['consent'] ?? '') === '1';
$parsedDeliveryTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $deliveryTime);
$deliveryTimeErrors = DateTimeImmutable::getLastErrors();
$validDeliveryTime = $parsedDeliveryTime !== false
    && $parsedDeliveryTime->format('Y-m-d\TH:i') === $deliveryTime
    && ($deliveryTimeErrors === false || ($deliveryTimeErrors['warning_count'] === 0 && $deliveryTimeErrors['error_count'] === 0));

if (!in_array($deliveryType, ['train', 'bus'], true)) {
    json_out(['success' => false, 'error' => 'Выберите поезд или автобус'], 422);
}
if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    json_out(['success' => false, 'error' => 'Укажите имя (от 2 до 120 символов)'], 422);
}
if (!preg_match('/^\+?[0-9]{8,15}$/D', $phone)) {
    json_out(['success' => false, 'error' => 'Проверьте номер телефона'], 422);
}
if (mb_strlen($route) < 2 || mb_strlen($route) > 200 || mb_strlen($station) < 2 || mb_strlen($station) > 160) {
    json_out(['success' => false, 'error' => 'Укажите маршрут и место получения'], 422);
}
if ($outletId <= 0) {
    json_out(['success' => false, 'error' => 'Выберите согласованную торговую точку'], 422);
}
if (!$validDeliveryTime) {
    json_out(['success' => false, 'error' => 'Укажите дату и время по местному времени станции'], 422);
}
if (mb_strlen($carriageSeat) > 100 || mb_strlen($orderDetails) < 2 || mb_strlen($orderDetails) > 4000 || mb_strlen($comment) > 2000) {
    json_out(['success' => false, 'error' => 'Проверьте состав заказа и дополнительные сведения'], 422);
}
if (!$consent) {
    json_out(['success' => false, 'error' => 'Подтвердите согласие на обработку данных для ответа на заявку'], 422);
}

try {
    $outletStmt = $pdo->prepare('SELECT name FROM edu_em_outlets WHERE id = ? AND is_active = 1');
    $outletStmt->execute([$outletId]);
    $outletName = $outletStmt->fetchColumn();
    if ($outletName === false) {
        json_out(['success' => false, 'error' => 'Выбранная торговая точка больше недоступна'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO edu_em_requests
            (delivery_type, customer_name, phone, route, station, outlet_id, outlet_name,
             delivery_local_time, carriage_seat, order_details, comment)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $deliveryType,
        $name,
        $phone,
        $route,
        $station,
        $outletId,
        $outletName,
        $deliveryTime,
        $carriageSeat !== '' ? $carriageSeat : null,
        $orderDetails,
        $comment !== '' ? $comment : null,
    ]);
    json_out([
        'success' => true,
        'id' => (int) $pdo->lastInsertId(),
        'message' => 'Заявка отправлена. Мы уточним возможность доставки и стоимость отдельно.',
    ], 201);
} catch (Throwable $error) {
    error_log('Saving Еду ем request failed: ' . $error->getMessage());
    json_out(['success' => false, 'error' => 'Не удалось отправить заявку. Попробуйте позже.'], 500);
}
