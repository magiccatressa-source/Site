<?php
/**
 * Webhook для получения уведомлений от Prodamus/Payform о платежах
 * Автоматически активирует подписку при успешном платеже
 */

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log('Payform webhook received: ' . $input);

if (!$data) {
    http_response_code(400);
    exit;
}

try {
    $status = $data['status'] ?? null;
    $orderId = $data['order_id'] ?? null;
    $transactionId = $data['transaction_id'] ?? null;
    $amount = (int)($data['amount'] ?? 0);

    // Пытаемся найти пользователя по номеру заказа или email
    $userId = null;
    $user = null;

    // Способ 1: если orderId содержит ID пользователя (если отправляли через API)
    if ($orderId && strpos($orderId, 'order_') === 0) {
        $parts = explode('_', $orderId);
        if (count($parts) >= 2) {
            $userId = (int)$parts[1];
        }
    }

    // Способ 2: ищем по email если он есть в вебхуке
    if (!$userId && !empty($data['email'])) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        if ($user) {
            $userId = $user['id'];
        }
    }

    // Способ 3: последний платёж в ожидании (если клиент переходил по ссылке с суммой)
    if (!$userId) {
        // Ищем самый свежий ожидающий платёж с близкой суммой
        $stmt = db()->prepare(
            'SELECT user_id FROM subscriptions
             WHERE payment_status = ? AND payment_method IN (?, ?)
             ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute(['pending', 'payform', 'phone']);
        $sub = $stmt->fetch();
        if ($sub) {
            $userId = $sub['user_id'];
        }
    }

    if (!$userId) {
        error_log('Could not find user for Payform webhook');
        http_response_code(200);
        exit;
    }

    // Обрабатываем статус платежа
    if ($status === 'success' || $status === 'completed' || $status === 'paid') {
        // ✅ Платёж успешен — активируем подписку
        $expiresAt = date('Y-m-d', strtotime('+30 days'));

        $stmt = db()->prepare(
            'UPDATE subscriptions
             SET status = ?, payment_status = ?, payment_method = ?,
                 prodamus_transaction_id = ?, payment_date = NOW(),
                 expires_at = ?, started_at = CURDATE()
             WHERE user_id = ?'
        );
        $stmt->execute([
            'active',
            'paid',
            'payform',
            $transactionId,
            $expiresAt,
            $userId
        ]);

        error_log('✓ Subscription activated for user ' . $userId . ' (amount: ' . $amount . ')');

        http_response_code(200);
        echo json_encode(['status' => 'ok']);

    } elseif ($status === 'failed' || $status === 'cancelled' || $status === 'declined') {
        // ❌ Платёж отклонён
        $stmt = db()->prepare(
            'UPDATE subscriptions SET payment_status = ?, status = ? WHERE user_id = ?'
        );
        $stmt->execute(['failed', 'inactive', $userId]);

        error_log('✗ Payment failed for user ' . $userId);
        http_response_code(200);
        echo json_encode(['status' => 'ok']);

    } else {
        // ⏳ Платёж в обработке
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    }

} catch (Exception $e) {
    error_log('Payform webhook error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error']);
}
