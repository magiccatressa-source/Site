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
// Только значения, допустимые ENUM payment_method в БД
if (!in_array($method, ['phone', 'prodamus', 'manual'], true)) {
    $method = 'prodamus';
}

try {
    $stmt = db()->prepare(
        'UPDATE subscriptions
         SET payment_method = ?, payment_status = ?
         WHERE user_id = ?'
    );
    $stmt->execute([$method, 'pending', $user['id']]);

    json_ok(['status' => 'marked_pending']);

} catch (Exception $e) {
    json_err('server_error', 500);
}
