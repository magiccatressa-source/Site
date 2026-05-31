<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_get();
$user = require_login();

$sub = get_subscription($user['id']);
if (!has_active_access($sub)) {
    json_err('subscription_required', 403);
}

$topics = db()->prepare(
    'SELECT id, title, description, is_current FROM topics WHERE is_visible = 1 ORDER BY sort_order, id'
);
$topics->execute();
$topicRows = $topics->fetchAll();

// Get progress and favorites for this user
$progress = [];
$s = db()->prepare('SELECT lesson_id, completed FROM lesson_progress WHERE user_id = ?');
$s->execute([$user['id']]);
foreach ($s->fetchAll() as $p) {
    $progress[$p['lesson_id']] = (bool)$p['completed'];
}

$favorites = [];
$s = db()->prepare('SELECT lesson_id FROM lesson_favorites WHERE user_id = ?');
$s->execute([$user['id']]);
foreach ($s->fetchAll() as $f) {
    $favorites[$f['lesson_id']] = true;
}

// Get lessons per topic
$lessonsStmt = db()->prepare(
    'SELECT id, topic_id, title, duration_min, is_trial FROM lessons
     WHERE is_visible = 1 ORDER BY topic_id, sort_order, id'
);
$lessonsStmt->execute();

$lessonsByTopic = [];
foreach ($lessonsStmt->fetchAll() as $l) {
    $lessonsByTopic[$l['topic_id']][] = [
        'id'          => $l['id'],
        'title'       => $l['title'],
        'duration_min'=> $l['duration_min'],
        'completed'   => $progress[$l['id']] ?? false,
        'is_favorite' => $favorites[$l['id']] ?? false,
        'is_trial'    => (bool)$l['is_trial'],
    ];
}

$result = [];
foreach ($topicRows as $t) {
    $result[] = [
        'id'          => $t['id'],
        'title'       => $t['title'],
        'description' => $t['description'],
        'is_current'  => (bool)$t['is_current'],
        'lessons'     => $lessonsByTopic[$t['id']] ?? [],
    ];
}

json_ok(['topics' => $result]);
