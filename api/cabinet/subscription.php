<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_get();
$user = require_login();

$sub = get_subscription($user['id']);
$status = subscription_display_status($sub);

$labels = [
    'active'          => 'Активна',
    'paused'          => 'Активна (на паузе)',
    'pending_payment' => 'Активна, ожидается оплата',
    'trial'           => 'Пробный доступ',
    'inactive'        => 'Не активна',
];

json_ok([
    'status'       => $status,
    'label'        => $labels[$status] ?? 'Не активна',
    'has_access'   => has_active_access($sub),
    'expires_at'   => $sub['expires_at'] ?? null,
    'zoom_link'    => setting('zoom_link'),
    'tg_chat_link' => setting('telegram_chat_link'),
    'schedule'     => setting('schedule_text'),
]);
