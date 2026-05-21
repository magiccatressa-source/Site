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

$data = body();

$name         = trim($data['name'] ?? '');
$last_name    = trim($data['last_name'] ?? '');
$email        = strtolower(trim($data['email'] ?? ''));
$password     = $data['password'] ?? '';
$social_link  = trim($data['social_link'] ?? '');
$consent_pd   = !empty($data['consent_pd']);
$consent_offer = !empty($data['consent_offer']);

// Validation
if (mb_strlen($name) < 2)    json_err('name_too_short');
if (mb_strlen($name) > 100)  json_err('name_too_long');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('invalid_email');
if (mb_strlen($email) > 255) json_err('email_too_long');
if (strlen($password) < 8)   json_err('password_too_short');
if (strlen($password) > 100) json_err('password_too_long');
if (!$consent_pd)            json_err('consent_pd_required');
if (!$consent_offer)         json_err('consent_offer_required');

// Check uniqueness
$s = db()->prepare('SELECT id FROM users WHERE email = ?');
$s->execute([$email]);
if ($s->fetch()) json_err('email_exists');

$token        = bin2hex(random_bytes(32));
$token_hash   = hash('sha256', $token);
$token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
$ip           = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

db()->prepare(
    'INSERT INTO users
     (name, last_name, email, password_hash, social_link,
      email_verify_token, email_verify_expires,
      consent_pd, consent_offer, consent_at, consent_ip)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), ?)'
)->execute([
    $name,
    $last_name ?: null,
    $email,
    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
    $social_link ?: null,
    $token_hash,
    $token_expires,
    $ip,
]);

send_verify_email($email, $name, $token);

json_ok(['message' => 'check_email']);
