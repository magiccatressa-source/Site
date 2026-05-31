<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = db()->prepare(
        'SELECT id, days, reason, status, admin_note, created_at, reviewed_at
         FROM pause_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5'
    );
    $s->execute([$user['id']]);
    json_ok(['requests' => $s->fetchAll()]);
}

only_post();
csrf_verify();
$data = body();

// Check no pending request already exists
$existing = db()->prepare(
    'SELECT id FROM pause_requests WHERE user_id = ? AND status = "pending"'
);
$existing->execute([$user['id']]);
if ($existing->fetch()) json_err('pending_request_exists');

$days   = min(90, max(1, (int)($data['days'] ?? 7)));
$reason = trim($data['reason'] ?? '');

db()->prepare(
    'INSERT INTO pause_requests (user_id, days, reason) VALUES (?, ?, ?)'
)->execute([$user['id'], $days, $reason ?: null]);

json_ok();
