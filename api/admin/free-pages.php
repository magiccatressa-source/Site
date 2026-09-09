<?php
/**
 * CRUD для публичных страниц /free/<slug>.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

/**
 * Принимает и голый ID, и ссылку из Кинескопа:
 *   https://kinescope.io/abc123?x=1  →  abc123
 *   https://kinescope.io/embed/abc123 →  abc123
 */
function normalize_kinescope_id(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $path = parse_url($raw, PHP_URL_PATH);
    if ($path === false || $path === null) $path = $raw;
    $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
    $id = $parts ? end($parts) : '';
    return preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id) ? $id : '';
}

$method = $_SERVER['REQUEST_METHOD'];

$textFields = ['slug','eyebrow','heading','subline','intro_text','disclaimer','meta_description'];
$intFields  = ['is_vertical','is_visible','sort_order'];

if ($method === 'GET') {
    $rows = db()->query(
        'SELECT id, slug, eyebrow, heading, subline, intro_text, disclaimer,
                kinescope_id, is_vertical, meta_description, is_visible, sort_order
         FROM free_pages ORDER BY sort_order, id'
    )->fetchAll();
    json_ok(['pages' => $rows]);
}

csrf_verify();
$data = body();

if ($method === 'POST') {
    $slug    = strtolower(trim($data['slug'] ?? ''));
    $heading = trim($data['heading'] ?? '');
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) json_err('invalid_slug');
    if ($heading === '') json_err('missing_heading');

    $exists = db()->prepare('SELECT 1 FROM free_pages WHERE slug = ?');
    $exists->execute([$slug]);
    if ($exists->fetch()) json_err('slug_taken');

    $rawKinescope = (string)($data['kinescope_id'] ?? '');
    $kinescopeId  = normalize_kinescope_id($rawKinescope);
    if ($rawKinescope !== '' && $kinescopeId === '') json_err('invalid_kinescope');

    $max = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM free_pages')->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO free_pages
            (slug, eyebrow, heading, subline, intro_text, disclaimer,
             kinescope_id, is_vertical, meta_description, is_visible, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $slug,
        trim($data['eyebrow'] ?? '') ?: 'Бесплатный урок',
        $heading,
        trim($data['subline'] ?? '') ?: null,
        trim($data['intro_text'] ?? '') ?: null,
        trim($data['disclaimer'] ?? '') ?: null,
        $kinescopeId,
        (int)($data['is_vertical'] ?? 0) ? 1 : 0,
        trim($data['meta_description'] ?? '') ?: null,
        array_key_exists('is_visible', $data) ? ((int)$data['is_visible'] ? 1 : 0) : 1,
        $max,
    ]);
    $newId = (int)db()->lastInsertId();
    log_admin($admin['id'], 'free_page.create', 'free_page', $newId, ['slug' => $slug]);
    json_ok(['id' => $newId], 201);
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');

    $fields = [];
    $params = [];

    foreach ($textFields as $f) {
        if (!array_key_exists($f, $data)) continue;
        if (!is_scalar($data[$f]) && $data[$f] !== null) json_err('invalid_field');
        $val = trim((string)$data[$f]);

        if ($f === 'slug') {
            $val = strtolower($val);
            if (!preg_match('/^[a-z0-9-]{1,80}$/', $val)) json_err('invalid_slug');
            $busy = db()->prepare('SELECT 1 FROM free_pages WHERE slug = ? AND id != ?');
            $busy->execute([$val, $id]);
            if ($busy->fetch()) json_err('slug_taken');
        }
        if ($f === 'heading' && $val === '') json_err('missing_heading');
        if ($f === 'eyebrow' && $val === '') $val = 'Бесплатный урок';

        // Пустая строка в необязательных полях = очистить (NULL)
        $optional = in_array($f, ['subline','intro_text','disclaimer','meta_description'], true);
        $fields[] = "$f = ?";
        $params[] = ($optional && $val === '') ? null : $val;
    }

    if (array_key_exists('kinescope_id', $data)) {
        $rawKinescope = (string)$data['kinescope_id'];
        $kinescopeId  = normalize_kinescope_id($rawKinescope);
        // Не даём молча стереть видео из-за опечатки в ссылке
        if ($rawKinescope !== '' && $kinescopeId === '') json_err('invalid_kinescope');
        $fields[] = 'kinescope_id = ?';
        $params[] = $kinescopeId;
    }

    foreach ($intFields as $f) {
        if (!array_key_exists($f, $data)) continue;
        $fields[] = "$f = ?";
        $params[] = $f === 'sort_order' ? max(0, (int)$data[$f]) : ((int)$data[$f] ? 1 : 0);
    }

    if ($fields) {
        $params[] = $id;
        db()->prepare('UPDATE free_pages SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        log_admin($admin['id'], 'free_page.update', 'free_page', $id, $data);
    }
    json_ok();
}

if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');
    db()->prepare('DELETE FROM free_pages WHERE id = ?')->execute([$id]);
    log_admin($admin['id'], 'free_page.delete', 'free_page', $id);
    json_ok();
}

json_err('method_not_allowed', 405);
