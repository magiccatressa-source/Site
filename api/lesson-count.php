<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';

header('Content-Type: application/json; charset=utf-8');

$count = (int)db()->query('SELECT COUNT(*) FROM lessons WHERE is_visible = 1')->fetchColumn();
json_ok(['count' => $count]);
