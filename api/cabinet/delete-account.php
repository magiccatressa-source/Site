<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$user = require_login();
csrf_verify();

// Cascade delete via FK: subscriptions, lesson_progress, lesson_favorites, admin_log references
db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);

start_user_session();
session_destroy();
json_ok();
