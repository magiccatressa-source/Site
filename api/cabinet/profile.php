<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = db()->prepare('SELECT id, name, last_name, email, phone, vk_url, tg_url FROM users WHERE id = ?');
    $s->execute([$user['id']]);
    json_ok(['user' => $s->fetch()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $data = body();

    $name        = trim($data['name'] ?? '');
    $last_name   = trim($data['last_name'] ?? '');
    $phone       = trim($data['phone'] ?? '');
    $vk_nick = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $data['vk_nick'] ?? '');
    $tg_nick = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $data['tg_nick'] ?? '');
    $vk_url  = $vk_nick ? 'https://vk.com/' . $vk_nick : null;
    $tg_url  = $tg_nick ? 'https://t.me/' . $tg_nick : null;

    if (mb_strlen($name) < 2) json_err('name_too_short');

    // Change password if provided
    if (!empty($data['new_password'])) {
        $current = $data['current_password'] ?? '';
        $s = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $s->execute([$user['id']]);
        $row = $s->fetch();
        if (!password_verify($current, $row['password_hash'])) {
            json_err('wrong_current_password');
        }
        if (strlen($data['new_password']) < 8) json_err('password_too_short');
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($data['new_password'], PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
    }

    db()->prepare(
        'UPDATE users SET name = ?, last_name = ?, phone = ?, vk_url = ?, tg_url = ? WHERE id = ?'
    )->execute([
        $name,
        $last_name ?: null,
        $phone ?: null,
        $vk_url,
        $tg_url,
        $user['id'],
    ]);

    json_ok();
}

json_err('method_not_allowed', 405);
