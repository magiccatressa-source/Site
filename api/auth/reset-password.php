<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
start_user_session();
only_post();
csrf_verify();

$data     = body();
$token    = $data['token'] ?? '';
$password = $data['password'] ?? '';

if (!$token)                   json_err('missing_token');
if (strlen($password) < 8)    json_err('password_too_short');
if (strlen($password) > 100)  json_err('password_too_long');

$token_hash = hash('sha256', $token);
$s = db()->prepare(
    'SELECT pr.id, pr.user_id FROM password_resets pr
     WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()'
);
$s->execute([$token_hash]);
$row = $s->fetch();

if (!$row) json_err('invalid_or_expired_token', 400);

db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $row['user_id']]);

db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
    ->execute([$row['id']]);

json_ok(['message' => 'password_updated']);
