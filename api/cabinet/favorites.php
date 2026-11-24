<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$user = require_login();

if (!user_has_any_access((int)$user['id'])) json_err('subscription_required', 403);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Избранное работает поверх всех доступных уроков: показываем только
    // те, что входят в продукты с активным доступом.
    $topicIds = accessible_topic_ids((int)$user['id']);
    if (!$topicIds) json_ok(['favorites' => []]);
    $ph = implode(',', array_fill(0, count($topicIds), '?'));

    $s = db()->prepare(
        "SELECT l.id, l.title, l.duration_min, t.title AS topic_title
         FROM lesson_favorites lf
         JOIN lessons l ON l.id = lf.lesson_id AND l.is_visible = 1
         JOIN topics t ON t.id = l.topic_id
         WHERE lf.user_id = ? AND l.topic_id IN ($ph)
         ORDER BY lf.created_at DESC"
    );
    $s->execute(array_merge([$user['id']], $topicIds));
    json_ok(['favorites' => $s->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $data     = body();
    $lessonId = (int)($data['lesson_id'] ?? 0);
    $action   = $data['action'] ?? 'add'; // 'add' or 'remove'

    if (!$lessonId) json_err('missing_lesson_id');

    $s = db()->prepare('SELECT id FROM lessons WHERE id = ? AND is_visible = 1');
    $s->execute([$lessonId]);
    if (!$s->fetch()) json_err('lesson_not_found', 404);

    // В избранное можно положить только урок, к которому есть доступ.
    // Удалить — всегда, иначе потерявший доступ не сможет убрать запись.
    if ($action === 'add' && !can_view_lesson((int)$user['id'], $lessonId)) {
        json_err('no_access_to_lesson', 403);
    }

    if ($action === 'add') {
        db()->prepare(
            'INSERT IGNORE INTO lesson_favorites (user_id, lesson_id) VALUES (?, ?)'
        )->execute([$user['id'], $lessonId]);
    } else {
        db()->prepare(
            'DELETE FROM lesson_favorites WHERE user_id = ? AND lesson_id = ?'
        )->execute([$user['id'], $lessonId]);
    }
    json_ok();
}

json_err('method_not_allowed', 405);
