<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$user = require_login();

$sub = get_subscription($user['id']);
if (!has_active_access($sub)) json_err('subscription_required', 403);

$data       = body();
$lessonId   = (int)($data['lesson_id'] ?? 0);
$watchSec   = (int)($data['watch_seconds'] ?? 0);
$completed  = !empty($data['completed']);

if (!$lessonId) json_err('missing_lesson_id');

// Verify lesson exists and is visible
$s = db()->prepare('SELECT id FROM lessons WHERE id = ? AND is_visible = 1');
$s->execute([$lessonId]);
if (!$s->fetch()) json_err('lesson_not_found', 404);

db()->prepare(
    'INSERT INTO lesson_progress (user_id, lesson_id, watch_seconds, completed, watched_at)
     VALUES (?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
       watch_seconds = GREATEST(watch_seconds, VALUES(watch_seconds)),
       completed     = IF(VALUES(completed) = 1, 1, completed),
       watched_at    = NOW()'
)->execute([$user['id'], $lessonId, $watchSec, $completed ? 1 : 0]);

json_ok();
