<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
require_admin();

$videoId = trim($_GET['video_id'] ?? '');
if (!$videoId) json_err('missing_video_id');

if (!defined('KINESCOPE_API_TOKEN') || !KINESCOPE_API_TOKEN) {
    json_err('kinescope_token_not_configured');
}

$url = 'https://api.kinescope.io/v1/videos/' . urlencode($videoId);
$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => 'Authorization: Bearer ' . KINESCOPE_API_TOKEN . "\r\n",
        'timeout' => 5,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) json_err('kinescope_request_failed');

$json = json_decode($raw, true);
if (!isset($json['data'])) json_err('kinescope_invalid_response');

$duration = $json['data']['duration'] ?? null; // seconds
$durationMin = $duration ? (int)round($duration / 60) : null;

json_ok(['duration_min' => $durationMin]);
