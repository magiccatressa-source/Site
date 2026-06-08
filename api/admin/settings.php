<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $keys = ['zoom_link','telegram_chat_link','schedule_text','welcome_kinescope_id','welcome_text','kinescope_password','trial_lesson_id','trial_lesson_id_2','trial_lesson_id_3'];
    $result = [];
    foreach ($keys as $k) $result[$k] = setting($k);
    json_ok($result);
}

only_post();
csrf_verify();
$data = body();

$allowed = ['zoom_link','telegram_chat_link','schedule_text','welcome_kinescope_id','welcome_text','kinescope_password','trial_lesson_id','trial_lesson_id_2','trial_lesson_id_3'];
$saved = [];
foreach ($allowed as $key) {
    if (array_key_exists($key, $data)) {
        set_setting($key, (string)$data[$key], $admin['id']);
        $saved[] = $key;
    }
}

if ($saved) {
    log_admin($admin['id'], 'settings.update', '', 0, ['keys' => $saved]);
}
json_ok();
