<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$user = require_login();
csrf_verify();

$userId = (int)$user['id'];

$clubId = club_product_id();
$access = get_access_row($userId, $clubId);

// Block double-activation: активный непробный доступ, который ещё не истёк
if ($access && $access['status'] === 'active' && !$access['is_trial'] && !$access['is_frozen']
    && $access['expires_at'] && strtotime($access['expires_at']) > time()) {
    json_err('already_active', 409);
}

// Заказ помечается оплаченным — так эта кнопка работала и раньше.
// Кнопка в кабинете (api/payment/phone-confirm.php) при том же действии
// ставит 'pending'. Расхождение досталось в наследство; какой из двух
// вариантов правильный — решение Любови, здесь поведение не меняем.
$orderId = settle_order($userId, $clubId, (float)club_price_for_user($userId), 'phone', null, 'paid');
grant_access($userId, $clubId, null, 'payment', $orderId ?: null);

$newAccess   = get_access_row($userId, $clubId);
$expiresDate = $newAccess && $newAccess['expires_at']
    ? date('Y-m-d', strtotime($newAccess['expires_at']))
    : date('Y-m-d', strtotime('+30 days'));

// Notify admin via Telegram
$adminChatId = setting('telegram_admin_chat_id');
$botToken    = setting('telegram_bot_token');
if ($adminChatId && $botToken) {
    $name = trim(($user['name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $text = "💳 Клиент сообщил об оплате:\n<b>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</b>\n"
          . "Подписка активирована до " . date('d.m.Y', strtotime($expiresDate)) . " (ожидается подтверждение оплаты)\n"
          . "/admin/user-edit.php?id=" . $userId;
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode(['chat_id' => $adminChatId, 'text' => $text, 'parse_mode' => 'HTML']),
    ]]);
    @file_get_contents('https://api.telegram.org/bot' . $botToken . '/sendMessage', false, $ctx);
}

json_ok(['expires_at' => $expiresDate]);
