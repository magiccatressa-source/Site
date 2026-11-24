<?php
/**
 * Отметить платёж как "в ожидании" перед редиректом на Payform
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$user = require_login();

$data = json_decode(file_get_contents('php://input'), true);
$method = $data['payment_method'] ?? 'prodamus';
if (!in_array($method, ['phone', 'prodamus', 'manual'], true)) {
    $method = 'prodamus';
}

try {
    $clubId = club_product_id();

    // Заказ создаём только если незакрытого ещё нет: человек может
    // нажать «оплатить» несколько раз, дубли в истории не нужны.
    $existing = latest_order((int)$user['id'], $clubId);
    if (!$existing || $existing['status'] !== 'pending') {
        create_order(
            (int)$user['id'],
            $clubId,
            (float)club_price_for_user((int)$user['id']),
            $method,
            'pending',
            null,
            'Переход на платёжную форму, оплата ещё не подтверждена'
        );
    }

    json_ok(['status' => 'marked_pending']);

} catch (Exception $e) {
    json_err('server_error', 500);
}
