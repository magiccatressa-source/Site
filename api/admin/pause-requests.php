<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db()->query(
        'SELECT pr.id, pr.user_id, pr.days, pr.reason, pr.status, pr.admin_note,
                pr.created_at, pr.reviewed_at,
                u.name, u.last_name, u.email,
                s.expires_at
         FROM pause_requests pr
         JOIN users u ON u.id = pr.user_id
         LEFT JOIN subscriptions s ON s.user_id = pr.user_id
         ORDER BY pr.status ASC, pr.created_at DESC'
    )->fetchAll();
    json_ok(['requests' => $rows]);
}

csrf_verify();
$data = body();

$id     = (int)($data['id'] ?? 0);
$action = $data['action'] ?? ''; // approve | reject
if (!$id || !in_array($action, ['approve','reject'])) json_err('invalid_request');

$pr = db()->prepare('SELECT * FROM pause_requests WHERE id = ?');
$pr->execute([$id]);
$req = $pr->fetch();
if (!$req) json_err('not_found', 404);
if ($req['status'] !== 'pending') json_err('already_reviewed');

if ($action === 'approve') {
    $days = (int)($data['days'] ?? $req['days']); // admin can override days

    // Extend subscription
    db()->prepare(
        'UPDATE subscriptions
         SET expires_at = DATE_ADD(COALESCE(expires_at, CURDATE()), INTERVAL ? DAY),
             is_paused = 0
         WHERE user_id = ?'
    )->execute([$days, $req['user_id']]);

    db()->prepare(
        'UPDATE pause_requests
         SET status = "approved", reviewed_at = NOW(), reviewed_by = ?, admin_note = ?
         WHERE id = ?'
    )->execute([$admin['id'], $data['admin_note'] ?? null, $id]);

    log_admin($admin['id'], 'pause.approve', 'user', $req['user_id'], ['days' => $days]);
} else {
    db()->prepare(
        'UPDATE pause_requests
         SET status = "rejected", reviewed_at = NOW(), reviewed_by = ?, admin_note = ?
         WHERE id = ?'
    )->execute([$admin['id'], $data['admin_note'] ?? null, $id]);

    log_admin($admin['id'], 'pause.reject', 'user', $req['user_id'], []);
}

json_ok();
