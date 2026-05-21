<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/email.php';

header('Content-Type: application/json; charset=utf-8');
start_user_session();
only_post();
csrf_verify();

$data  = body();
$email = strtolower(trim($data['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('invalid_email');

$s = db()->prepare('SELECT id, name FROM users WHERE email = ? AND email_verified = 1');
$s->execute([$email]);
$user = $s->fetch();

// Always return OK to prevent email enumeration
if ($user) {
    // Invalidate existing tokens
    db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')->execute([$user['id']]);

    $token      = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires    = date('Y-m-d H:i:s', strtotime('+1 hour'));

    db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$user['id'], $token_hash, $expires]);

    send_reset_email($email, $user['name'], $token);
}

json_ok(['message' => 'check_email']);
