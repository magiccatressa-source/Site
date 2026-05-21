<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
start_user_session();
only_post();
csrf_verify();

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
check_rate_limit($ip);

$data     = body();
$email    = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (!$email || !$password) json_err('missing_fields');

$s = db()->prepare('SELECT id, name, password_hash, email_verified, role FROM users WHERE email = ?');
$s->execute([$email]);
$user = $s->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    record_failed_login($ip);
    json_err('invalid_credentials', 401);
}

if (!$user['email_verified']) {
    json_err('email_not_verified', 403);
}

clear_rate_limit($ip);
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];

json_ok([
    'id'   => $user['id'],
    'name' => $user['name'],
    'role' => $user['role'],
]);
