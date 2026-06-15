<?php
/**
 * Webhook от Prodamus/Payform — автоматически активирует подписку при оплате.
 *
 * Prodamus отправляет POST с полями (не JSON):
 *   customer_email, customer_phone, sum, order_id, date, payment_type
 * Событие "Заказ оплачен" не содержит поля status — сам факт вызова = успешная оплата.
 */

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// Prodamus шлёт form-encoded POST, не JSON
$data = $_POST;
if (empty($data)) {
    // На случай если всё-таки JSON
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
}

error_log('Prodamus webhook received: ' . json_encode($data));

if (empty($data)) {
    http_response_code(200);
    echo json_encode(['status' => 'error', 'message' => 'no data']);
    exit;
}

try {
    // Prodamus присылает email покупателя в customer_email
    $email       = trim($data['customer_email'] ?? $data['email'] ?? '');
    $phone       = trim($data['customer_phone'] ?? $data['phone'] ?? '');
    $orderId     = $data['order_id'] ?? null;
    $amount      = (float)str_replace(',', '.', $data['sum'] ?? $data['amount'] ?? 0);

    $userId = null;

    // Ищем пользователя по email
    if ($email) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $userId = $row['id'];
        }
    }

    // Если email не нашёл — пробуем по телефону
    if (!$userId && $phone) {
        $phoneClean = preg_replace('/\D/', '', $phone);
        $stmt = db()->prepare("SELECT id FROM users WHERE REGEXP_REPLACE(phone, '[^0-9]', '') = ? LIMIT 1");
        $stmt->execute([$phoneClean]);
        $row = $stmt->fetch();
        if ($row) {
            $userId = $row['id'];
        }
    }

    if (!$userId) {
        error_log('Prodamus webhook: user not found for email=' . $email . ' phone=' . $phone);
        // Отвечаем 200 — Prodamus не будет повторять попытки
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'user not found']);
        exit;
    }

    // Факт получения вебхука "Заказ оплачен" = успешная оплата
    $expiresAt = date('Y-m-d', strtotime('+30 days'));

    $stmt = db()->prepare(
        'UPDATE subscriptions
         SET status = ?, payment_status = ?, payment_method = ?,
             prodamus_transaction_id = ?, payment_date = NOW(),
             expires_at = ?, started_at = CURDATE()
         WHERE user_id = ?'
    );
    $stmt->execute(['active', 'paid', 'payform', $orderId, $expiresAt, $userId]);

    error_log('Prodamus webhook: subscription activated for user ' . $userId . ' (email=' . $email . ', sum=' . $amount . ')');

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log('Prodamus webhook error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error']);
}
