<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
start_admin_session();
only_post();
csrf_verify();

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
check_rate_limit($ip);

$data     = body();
$email    = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

$s = db()->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ? AND role = "admin"');
$s->execute([$email]);
$user = $s->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    record_failed_login($ip);
    json_err('invalid_credentials', 401);
}

clear_rate_limit($ip);
session_regenerate_id(true);
$_SESSION['admin_id'] = $user['id'];

log_admin($user['id'], 'admin.login');

json_ok(['name' => $user['name']]);
