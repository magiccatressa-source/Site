<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login();

// Completed lessons (unique)
$completed = db()->prepare(
    'SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND completed = 1'
);
$completed->execute([$user['id']]);
$completedCount = (int)$completed->fetchColumn();

// Total watch time across ALL sessions including repeats
$watchRow = db()->prepare(
    'SELECT COALESCE(SUM(watch_seconds), 0) FROM lesson_views WHERE user_id = ?'
);
$watchRow->execute([$user['id']]);
$totalSeconds = (int)$watchRow->fetchColumn();

// Format hours and minutes
$hours   = floor($totalSeconds / 3600);
$minutes = floor(($totalSeconds % 3600) / 60);

json_ok([
    'completed_lessons' => $completedCount,
    'total_seconds'     => $totalSeconds,
    'hours'             => $hours,
    'minutes'           => $minutes,
]);
