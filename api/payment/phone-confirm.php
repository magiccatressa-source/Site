<?php
/**
 * Подтверждение оплаты по телефону.
 * Активирует подписку немедленно + уведомляет владельца в Telegram.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$user = require_login();

try {
    $clubId = club_product_id();
    $amount = (float)club_price_for_user((int)$user['id']);

    // Заказ остаётся в статусе pending: перевод по телефону Любовь
    // подтверждает вручную. Доступ при этом выдаётся сразу.
    $orderId = settle_order(
        (int)$user['id'], $clubId, $amount, 'phone', null, 'pending'
    );
    grant_access((int)$user['id'], $clubId, null, 'payment', $orderId ?: null);

    $access    = get_access_row((int)$user['id'], $clubId);
    $expiresAt = $access['expires_at'] ?? date('Y-m-d', strtotime('+30 days'));

    // Уведомляем владельца в Telegram через Bot API (если настроен)
    $botToken = setting('telegram_bot_token');
    $ownerId  = setting('telegram_owner_id');
    if ($botToken && $ownerId) {
        $text = "💳 Подтверждение оплаты по телефону\n\n"
              . "Пользователь: *" . $user['name'] . "*\n"
              . "Email: " . $user['email'] . "\n"
              . "Сумма: проверь перевод в Т-Банке\n\n"
              . "Подписка активирована до " . date('d.m.Y', strtotime($expiresAt)) . "\n"
              . "Если оплаты нет — отбери доступ в админке.";

        @file_get_contents(
            'https://api.telegram.org/bot' . $botToken
            . '/sendMessage?chat_id=' . $ownerId
            . '&text=' . urlencode($text)
            . '&parse_mode=Markdown'
        );
    }

    error_log('Phone payment confirmed by user ' . $user['id'] . ' (' . $user['name'] . ')');

    json_ok([
        'activated' => true,
        'expires_at' => $expiresAt,
    ]);

} catch (Exception $e) {
    error_log('Phone confirm error: ' . $e->getMessage());
    json_err('Ошибка. Напишите напрямую в Telegram.', 500);
}
