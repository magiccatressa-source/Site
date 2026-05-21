<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$admin = require_admin();
csrf_verify();

$data   = body();
$userId = (int)($data['user_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$userId) json_err('missing_user_id');

// Check user exists
$u = db()->prepare('SELECT id, name FROM users WHERE id = ? AND role = "user"');
$u->execute([$userId]);
$targetUser = $u->fetch();
if (!$targetUser) json_err('user_not_found', 404);

// Get or create subscription
$s = db()->prepare('SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$s->execute([$userId]);
$sub = $s->fetch();

switch ($action) {

  case 'activate':
    $startDate  = $data['started_at'] ?? date('Y-m-d');
    $expiresDate = $data['expires_at'] ?? date('Y-m-d', strtotime('+30 days'));
    $payStatus  = ($data['payment_pending'] ?? false) ? 'pending' : 'paid';

    if ($sub) {
      db()->prepare(
        'UPDATE subscriptions SET status = "active", is_paused = 0, payment_status = ?,
         started_at = ?, expires_at = ?, pause_started_at = NULL, pause_notes = NULL
         WHERE id = ?'
      )->execute([$payStatus, $startDate, $expiresDate, $sub['id']]);
    } else {
      db()->prepare(
        'INSERT INTO subscriptions (user_id, status, is_paused, payment_status, started_at, expires_at)
         VALUES (?, "active", 0, ?, ?, ?)'
      )->execute([$userId, $payStatus, $startDate, $expiresDate]);
    }
    log_admin($admin['id'], 'subscription.activate', 'user', $userId, [
      'started_at' => $startDate, 'expires_at' => $expiresDate, 'payment_status' => $payStatus,
    ]);
    break;

  case 'deactivate':
    if ($sub) {
      db()->prepare('UPDATE subscriptions SET status = "inactive", is_paused = 0 WHERE id = ?')
          ->execute([$sub['id']]);
    }
    log_admin($admin['id'], 'subscription.deactivate', 'user', $userId);
    break;

  case 'trial':
    $expiresDate = $data['expires_at'] ?? date('Y-m-d', strtotime('+7 days'));
    if ($sub) {
      db()->prepare('UPDATE subscriptions SET status = "trial", is_paused = 0, payment_status = NULL, expires_at = ? WHERE id = ?')
          ->execute([$expiresDate, $sub['id']]);
    } else {
      db()->prepare('INSERT INTO subscriptions (user_id, status, expires_at) VALUES (?, "trial", ?)')
          ->execute([$userId, $expiresDate]);
    }
    log_admin($admin['id'], 'subscription.trial', 'user', $userId, ['expires_at' => $expiresDate]);
    break;

  case 'pause':
    if (!$sub) json_err('no_subscription');
    $pauseNotes = trim($data['pause_notes'] ?? '');
    db()->prepare(
      'UPDATE subscriptions SET is_paused = 1, pause_started_at = CURDATE(), pause_notes = ? WHERE id = ?'
    )->execute([$pauseNotes ?: null, $sub['id']]);
    log_admin($admin['id'], 'subscription.pause', 'user', $userId, ['notes' => $pauseNotes]);
    break;

  case 'unpause':
    if (!$sub || !$sub['is_paused']) json_err('not_paused');
    // Extend expires_at by number of paused days
    $pauseStart = $sub['pause_started_at'] ?? date('Y-m-d');
    $pausedDays = (int)(new DateTime())->diff(new DateTime($pauseStart))->days;
    db()->prepare(
      'UPDATE subscriptions SET is_paused = 0, pause_started_at = NULL, pause_notes = NULL,
       expires_at = DATE_ADD(expires_at, INTERVAL ? DAY) WHERE id = ?'
    )->execute([$pausedDays, $sub['id']]);
    log_admin($admin['id'], 'subscription.unpause', 'user', $userId, ['days_added' => $pausedDays]);
    break;

  case 'confirm_payment':
    if (!$sub) json_err('no_subscription');
    db()->prepare('UPDATE subscriptions SET payment_status = "paid" WHERE id = ?')
        ->execute([$sub['id']]);
    log_admin($admin['id'], 'subscription.paid', 'user', $userId);
    break;

  case 'set_pending':
    if (!$sub) json_err('no_subscription');
    db()->prepare('UPDATE subscriptions SET payment_status = "pending" WHERE id = ?')
        ->execute([$sub['id']]);
    log_admin($admin['id'], 'subscription.pending', 'user', $userId);
    break;

  case 'update_expires':
    $expiresDate = $data['expires_at'] ?? '';
    if (!$expiresDate) json_err('missing_expires_at');
    if ($sub) {
      db()->prepare('UPDATE subscriptions SET expires_at = ? WHERE id = ?')
          ->execute([$expiresDate, $sub['id']]);
    }
    log_admin($admin['id'], 'subscription.update_expires', 'user', $userId, ['expires_at' => $expiresDate]);
    break;

  case 'set_notes':
    if (!$sub) json_err('no_subscription');
    db()->prepare('UPDATE subscriptions SET notes = ? WHERE id = ?')
        ->execute([$data['notes'] ?? null, $sub['id']]);
    break;

  default:
    json_err('unknown_action');
}

json_ok();
