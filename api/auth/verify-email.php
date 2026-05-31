<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

$token = trim($_GET['token'] ?? '');
if (!$token) {
    header('Location: /auth/login.php?error=invalid_token');
    exit;
}

$token_hash = hash('sha256', $token);
$s = db()->prepare(
    'SELECT id FROM users
     WHERE email_verify_token = ? AND email_verified = 0 AND email_verify_expires > NOW()'
);
$s->execute([$token_hash]);
$user = $s->fetch();

if (!$user) {
    header('Location: /auth/login.php?error=invalid_token');
    exit;
}

db()->prepare(
    'UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?'
)->execute([$user['id']]);

// Create trial subscription (5 days) if none exists
$hasSub = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ?');
$hasSub->execute([$user['id']]);
if (!$hasSub->fetch()) {
    db()->prepare(
        'INSERT INTO subscriptions (user_id, status, started_at, expires_at)
         VALUES (?, "trial", CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY))'
    )->execute([$user['id']]);
}

header('Location: /auth/login.php?verified=1');
exit;
