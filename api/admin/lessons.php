<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/telegram.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $topicId = (int)($_GET['topic_id'] ?? 0);
    if ($topicId) {
        $rows = db()->prepare(
            'SELECT id, topic_id, title, kinescope_id, description, duration_min, sort_order, is_visible, is_trial
             FROM lessons WHERE topic_id = ? ORDER BY sort_order, id'
        );
        $rows->execute([$topicId]);
    } else {
        $rows = db()->query(
            'SELECT l.id, l.topic_id, l.title, l.kinescope_id, l.description, l.duration_min, l.sort_order, l.is_visible,
                    t.title AS topic_title
             FROM lessons l
             JOIN topics t ON t.id = l.topic_id
             ORDER BY t.sort_order, l.sort_order, l.id'
        );
    }
    json_ok(['lessons' => $rows->fetchAll()]);
}

csrf_verify();
$data = body();

if ($method === 'POST') {
    $topicId     = (int)($data['topic_id'] ?? 0);
    $title       = trim($data['title'] ?? '');
    $kinescopeId = trim($data['kinescope_id'] ?? '');

    if (!$topicId)     json_err('missing_topic_id');
    if (!$title)       json_err('missing_title');
    if (!$kinescopeId) json_err('missing_kinescope_id');

    $max = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM lessons WHERE topic_id = ?');
    $max->execute([$topicId]);
    $sortOrder = (int)$max->fetchColumn();

    db()->prepare(
        'INSERT INTO lessons (topic_id, title, kinescope_id, description, duration_min, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $topicId, $title, $kinescopeId,
        $data['description'] ?? null,
        ($data['duration_min'] ?? null) ? (int)$data['duration_min'] : null,
        $sortOrder,
    ]);
    $newId = (int)db()->lastInsertId();
    log_admin($admin['id'], 'lesson.create', '', $newId, ['title' => $title, 'topic_id' => $topicId]);
    notify_new_lesson($newId, $title);
    json_ok(['id' => $newId], 201);
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');

    $fields = [];
    $params = [];
    foreach (['topic_id','title','kinescope_id','description'] as $f) {
        if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
    }
    foreach (['duration_min','sort_order','is_visible','is_trial'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = ($data[$f] !== null && $data[$f] !== '') ? (int)$data[$f] : null;
        }
    }
    if ($fields) {
        $params[] = $id;
        db()->prepare('UPDATE lessons SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        log_admin($admin['id'], 'lesson.update', '', $id, $data);
    }
    json_ok();
}

if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');
    db()->prepare('DELETE FROM lessons WHERE id = ?')->execute([$id]);
    log_admin($admin['id'], 'lesson.delete', '', $id);
    json_ok();
}

json_err('method_not_allowed', 405);
