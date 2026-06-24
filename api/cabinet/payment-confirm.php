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

$s = db()->prepare('SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$s->execute([$userId]);
$sub = $s->fetch();

// Block double-activation: active subscription that hasn't expired yet
if ($sub && $sub['status'] === 'active' && !$sub['is_paused']
    && $sub['expires_at'] && $sub['expires_at'] >= date('Y-m-d')) {
    json_err('already_active', 409);
}

$startDate   = date('Y-m-d');
$expiresDate = date('Y-m-d', strtotime('+30 days'));

if ($sub) {
    db()->prepare(
        'UPDATE subscriptions SET status = "active", is_paused = 0, payment_status = "pending",
         started_at = ?, expires_at = ?, pause_started_at = NULL, pause_notes = NULL WHERE id = ?'
    )->execute([$startDate, $expiresDate, $sub['id']]);
} else {
    db()->prepare(
        'INSERT INTO subscriptions (user_id, status, is_paused, payment_status, started_at, expires_at)
         VALUES (?, "active", 0, "pending", ?, ?)'
    )->execute([$userId, $startDate, $expiresDate]);
}

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
