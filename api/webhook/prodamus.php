<?php
/**
 * Webhook от Prodamus/Payform — автоматически активирует подписку при оплате.
 *
 * Prodamus отправляет POST с полями (не JSON):
 *   customer_email, customer_phone, sum, order_id, date, payment_type
 * Подпись передаётся в заголовке Sign — см. includes/prodamus-hmac.php.
 * Событие "Заказ оплачен" не содержит поля status — сам факт вызова = успешная оплата,
 * поэтому подлинность запроса подтверждается только подписью и суммой.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/prodamus-hmac.php';
require_once __DIR__ . '/../../includes/access.php';

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

function notify_admin_telegram(string $text): void {
    $botToken = setting('telegram_bot_token');
    $adminId  = setting('telegram_admin_chat_id');
    if (!$botToken || !$adminId) return;
    @file_get_contents(
        'https://api.telegram.org/bot' . $botToken . '/sendMessage?chat_id=' . $adminId
        . '&text=' . urlencode($text)
    );
}

function prodamus_reject(string $reason, array $data): never {
    error_log('Prodamus webhook REJECTED (' . $reason . '): ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    notify_admin_telegram(
        "⚠️ Отклонён подозрительный вебхук от Prodamus\n" .
        "Причина: " . $reason . "\n" .
        "Если это была настоящая оплата клиента — активируйте подписку вручную в админке " .
        "и напишите разработчику, что-то не так с проверкой подписи.\n" .
        "Данные: " . json_encode($data, JSON_UNESCAPED_UNICODE)
    );
    // Отвечаем 200, чтобы Prodamus не повторял запрос бесконечно
    http_response_code(200);
    echo json_encode(['status' => 'rejected', 'reason' => $reason]);
    exit;
}

// ── Проверка подписи ────────────────────────────────────────────
$secretKey = setting('prodamus_secret_key');
if ($secretKey) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $signature = $headers['Sign'] ?? $headers['sign'] ?? ($_SERVER['HTTP_SIGN'] ?? null);
    if (!ProdamusHmac::verify($data, $secretKey, $signature)) {
        prodamus_reject('invalid_signature', $data);
    }
} else {
    // Секретный ключ не заполнен в Настройках админки — подпись проверить нечем.
    error_log('Prodamus webhook: prodamus_secret_key не задан в настройках, подпись НЕ проверяется!');
}

// ── Проверка суммы (защита от "оплаты" по подменённой ссылке) ──
// Актуальные цены клуба: 3500₽ (первый месяц) / 3000₽ (продление) — см. cabinet/partials/payment-block.php
$MIN_VALID_AMOUNT = 2000.0;
$amount = (float)str_replace(',', '.', $data['sum'] ?? $data['amount'] ?? 0);
if ($amount < $MIN_VALID_AMOUNT) {
    prodamus_reject('amount_too_low_' . $amount, $data);
}

try {
    // Prodamus присылает email покупателя в customer_email
    $email       = trim($data['customer_email'] ?? $data['email'] ?? '');
    $phone       = trim($data['customer_phone'] ?? $data['phone'] ?? '');
    $orderId     = $data['order_id'] ?? null;

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

    // Факт получения вебхука "Заказ оплачен" с валидной подписью и суммой = успешная оплата
    $clubId = club_product_id();

    // Защита от повторной доставки: Prodamus может прислать вебхук
    // дважды, и без этой проверки доступ продлился бы два раза.
    if ($orderId && access_model_ready()) {
        $dup = db()->prepare(
            "SELECT id FROM orders WHERE external_id = ? AND status = 'paid' LIMIT 1"
        );
        $dup->execute([(string)$orderId]);
        if ($dup->fetchColumn()) {
            error_log('Prodamus webhook: заказ ' . $orderId . ' уже оплачен, повторную выдачу пропускаю');
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'already processed']);
            exit;
        }
    }

    $internalOrderId = settle_order(
        $userId, $clubId, $amount, 'prodamus',
        $orderId ? (string)$orderId : null, 'paid'
    );
    grant_access($userId, $clubId, null, 'payment', $internalOrderId ?: null);

    $access = get_access_row($userId, $clubId);
    error_log('Prodamus webhook: доступ к клубу выдан пользователю ' . $userId
        . ' (email=' . $email . ', sum=' . $amount
        . ', до ' . ($access['expires_at'] ?? '—') . ')');

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log('Prodamus webhook error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'error']);
}
