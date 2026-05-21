<?php
function json_ok(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $error, int $code = 400, array $extra = []): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function only_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_err('method_not_allowed', 405);
    }
}

function only_get(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_err('method_not_allowed', 405);
    }
}

function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null && $raw !== '') {
        json_err('invalid_json', 400);
    }
    return $data ?? [];
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
