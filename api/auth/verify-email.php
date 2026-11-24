<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/access.php';

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

// Пробный доступ к клубу на 5 дней — только если доступа ещё не было
$clubId = club_product_id();
if (!get_access_row((int)$user['id'], $clubId)) {
    grant_access((int)$user['id'], $clubId, 5, 'promo', null, null, true);
}

header('Location: /auth/login.php?verified=1');
exit;
