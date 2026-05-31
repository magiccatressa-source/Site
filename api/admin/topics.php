<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = db()->query(
        'SELECT id, title, description, sort_order, is_visible, is_current FROM topics ORDER BY sort_order, id'
    )->fetchAll();
    json_ok(['topics' => $rows]);
}

csrf_verify();
$data = body();

if ($method === 'POST') {
    $title = trim($data['title'] ?? '');
    if (!$title) json_err('missing_title');
    $max = db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM topics')->fetchColumn();
    $id = db()->prepare('INSERT INTO topics (title, description, sort_order) VALUES (?, ?, ?)');
    $id->execute([$title, $data['description'] ?? null, $max]);
    $newId = (int)db()->lastInsertId();
    log_admin($admin['id'], 'topic.create', '', $newId, ['title' => $title]);
    json_ok(['id' => $newId], 201);
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');
    $fields = [];
    $params = [];
    foreach (['title','description'] as $f) {
        if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
    }
    foreach (['sort_order','is_visible','is_current'] as $f) {
        if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = (int)$data[$f]; }
    }
    // Only one topic can be current at a time
    if (array_key_exists('is_current', $data) && (int)$data['is_current'] === 1) {
        db()->prepare('UPDATE topics SET is_current = 0 WHERE id != ?')->execute([$id]);
    }
    if ($fields) {
        $params[] = $id;
        db()->prepare('UPDATE topics SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        log_admin($admin['id'], 'topic.update', '', $id, $data);
    }
    json_ok();
}

if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');
    db()->prepare('DELETE FROM topics WHERE id = ?')->execute([$id]);
    log_admin($admin['id'], 'topic.delete', '', $id);
    json_ok();
}

json_err('method_not_allowed', 405);
