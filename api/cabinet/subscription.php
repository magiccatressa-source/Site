<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_get();
$user = require_login();

$clubId = club_product_id();
$access = get_access_row((int)$user['id'], $clubId);
$status = access_display_status((int)$user['id'], $clubId);

$labels = [
    'active'          => 'Активна',
    'paused'          => 'Активна (на паузе)',
    'pending_payment' => 'Активна, ожидает подтверждения',
    'trial'           => 'Пробный доступ',
    'inactive'        => 'Не активна',
];

json_ok([
    'status'       => $status,
    'label'        => $labels[$status] ?? 'Не активна',
    'has_access'   => has_access((int)$user['id'], $clubId),
    'expires_at'   => $access['expires_at'] ?? null,
    'zoom_link'    => setting('zoom_link'),
    'tg_chat_link' => setting('telegram_chat_link'),
    'schedule'     => setting('schedule_text'),
]);
