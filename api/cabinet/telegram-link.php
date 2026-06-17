<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login();

$token = bin2hex(random_bytes(16));
db()->prepare('UPDATE users SET telegram_link_token = ? WHERE id = ?')
    ->execute([$token, $user['id']]);

$botUsername = setting('telegram_bot_username');
json_ok(['url' => 'https://t.me/' . $botUsername . '?start=' . $token]);
