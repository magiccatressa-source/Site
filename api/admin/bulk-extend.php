<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$admin = require_admin();
csrf_verify();

$data = body();
$days = (int)($data['days'] ?? 0);

if ($days < 1 || $days > 365) json_err('invalid_days');

$affected = db()->prepare(
    'UPDATE subscriptions SET expires_at = DATE_ADD(expires_at, INTERVAL ? DAY)
     WHERE status IN ("active", "trial") AND expires_at IS NOT NULL'
);
$affected->execute([$days]);
$count = $affected->rowCount();

log_admin($admin['id'], 'subscription.bulk_extend', '', 0, [
    'days'     => $days,
    'affected' => $count,
]);

json_ok(['affected' => $count]);
