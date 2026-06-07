<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
require_admin();

$rows = db()->query(
    'SELECT l.id, l.title, t.title AS topic_title
     FROM lessons l
     JOIN topics t ON t.id = l.topic_id
     WHERE l.is_visible = 1
     ORDER BY t.sort_order, l.sort_order, l.id'
)->fetchAll();

json_ok(['lessons' => $rows]);
