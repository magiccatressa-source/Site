<?php
/**
 * CRUD продуктов и привязка тем к ним.
 *
 * Тема, не привязанная ни к одному продукту, не видна никому —
 * can_view_lesson() работает fail-closed. Поэтому GET отдаёт ещё и
 * список «бесхозных» тем, чтобы админка могла предупредить.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
$admin = require_admin();

if (!access_model_ready()) json_err('access_model_not_ready', 503);

/** Темы продукта. */
function product_topic_ids(int $productId): array {
    $s = db()->prepare('SELECT topic_id FROM product_topics WHERE product_id = ?');
    $s->execute([$productId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Заменить набор тем продукта.
 * Возвращает темы, которые после этого остались без продукта вообще.
 */
function set_product_topics(int $productId, array $topicIds): array {
    $topicIds = array_values(array_unique(array_map('intval', $topicIds)));

    db()->beginTransaction();
    try {
        db()->prepare('DELETE FROM product_topics WHERE product_id = ?')->execute([$productId]);
        if ($topicIds) {
            $ins = db()->prepare('INSERT IGNORE INTO product_topics (product_id, topic_id) VALUES (?, ?)');
            foreach ($topicIds as $tid) {
                if ($tid > 0) $ins->execute([$productId, $tid]);
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return orphan_topics();
}

/** Темы, не входящие ни в один продукт: их уроки не увидит никто. */
function orphan_topics(): array {
    return db()->query(
        'SELECT t.id, t.title FROM topics t
         WHERE NOT EXISTS (SELECT 1 FROM product_topics pt WHERE pt.topic_id = t.id)
         ORDER BY t.sort_order, t.id'
    )->fetchAll();
}

/** Проверка и нормализация полей продукта. Возвращает [поле => значение]. */
function product_fields(array $data, bool $isNew, int $id = 0): array {
    $out = [];

    if ($isNew || array_key_exists('slug', $data)) {
        $slug = strtolower(trim((string)($data['slug'] ?? '')));
        if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) json_err('invalid_slug');
        $busy = db()->prepare('SELECT 1 FROM products WHERE slug = ? AND id != ?');
        $busy->execute([$slug, $id]);
        if ($busy->fetch()) json_err('slug_taken');
        $out['slug'] = $slug;
    }

    if ($isNew || array_key_exists('title', $data)) {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') json_err('missing_title');
        $out['title'] = $title;
    }

    foreach (['subtitle', 'description'] as $f) {
        if (!array_key_exists($f, $data)) continue;
        $val = trim((string)$data[$f]);
        $out[$f] = $val === '' ? null : $val;
    }

    if ($isNew || array_key_exists('type', $data)) {
        $type = (string)($data['type'] ?? 'one_time');
        if (!in_array($type, ['subscription', 'one_time'], true)) json_err('invalid_type');
        $out['type'] = $type;
    }

    if ($isNew || array_key_exists('price', $data)) {
        $raw = trim((string)($data['price'] ?? ''));
        // Пустое поле — это почти всегда забытая цена, а не «бесплатно».
        // Ноль можно поставить, но только написав его явно.
        if ($raw === '') json_err('invalid_price');
        $price = (float)str_replace(',', '.', $raw);
        if ($price < 0 || $price > 9999999) json_err('invalid_price');
        $out['price'] = round($price, 2);
    }

    if (array_key_exists('price_renewal', $data)) {
        $raw = trim((string)$data['price_renewal']);
        if ($raw === '') {
            $out['price_renewal'] = null;
        } else {
            $pr = (float)str_replace(',', '.', $raw);
            if ($pr < 0 || $pr > 9999999) json_err('invalid_price');
            $out['price_renewal'] = round($pr, 2);
        }
    }

    if (array_key_exists('duration_days', $data)) {
        $raw = trim((string)$data['duration_days']);
        if ($raw === '' || $raw === '0') {
            $out['duration_days'] = null;
        } else {
            $d = (int)$raw;
            if ($d < 1 || $d > 3650) json_err('invalid_duration');
            $out['duration_days'] = $d;
        }
    }

    foreach (['payment_url', 'contact_url'] as $f) {
        if (!array_key_exists($f, $data)) continue;
        $url = trim((string)$data[$f]);
        if ($url === '') { $out[$f] = null; continue; }
        if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_err('invalid_url');
        }
        $out[$f] = $url;
    }

    if (array_key_exists('is_active', $data)) {
        $out['is_active'] = (int)$data['is_active'] ? 1 : 0;
    }
    if (array_key_exists('sort_order', $data)) {
        $out['sort_order'] = max(0, (int)$data['sort_order']);
    }

    return $out;
}

/**
 * У подписки обязан быть срок: без него нечего продлевать, и витрина
 * не сможет написать, на сколько человек покупает доступ.
 * Считаем по итоговому состоянию продукта, а не только по присланным полям.
 */
function check_duration(array $fields, ?array $existing): void {
    $type = $fields['type'] ?? ($existing['type'] ?? 'one_time');
    if ($type !== 'subscription') return;

    $duration = array_key_exists('duration_days', $fields)
        ? $fields['duration_days']
        : ($existing['duration_days'] ?? null);

    if (!$duration) json_err('duration_required');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $products = db()->query(
        'SELECT p.*,
                (SELECT COUNT(*) FROM user_access ua
                  WHERE ua.product_id = p.id AND ua.status = "active"
                    AND (ua.expires_at IS NULL OR ua.expires_at > NOW())) AS active_count,
                (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id) AS orders_count
         FROM products p ORDER BY p.sort_order, p.id'
    )->fetchAll();

    foreach ($products as &$p) {
        $p['topic_ids'] = product_topic_ids((int)$p['id']);
    }
    unset($p);

    json_ok([
        'products' => $products,
        'topics'   => db()->query('SELECT id, title FROM topics ORDER BY sort_order, id')->fetchAll(),
        'orphans'  => orphan_topics(),
        'club_id'  => club_product_id(),
    ]);
}

csrf_verify();
$data = body();

if ($method === 'POST') {
    $fields = product_fields($data, true);
    check_duration($fields, null);
    if (!array_key_exists('sort_order', $fields)) {
        $fields['sort_order'] = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM products')->fetchColumn();
    }

    $cols = array_keys($fields);
    $stmt = db()->prepare(
        'INSERT INTO products (' . implode(', ', $cols) . ')
         VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
    );
    $stmt->execute(array_values($fields));
    $newId = (int)db()->lastInsertId();

    $orphans = [];
    if (array_key_exists('topic_ids', $data) && is_array($data['topic_ids'])) {
        $orphans = set_product_topics($newId, $data['topic_ids']);
    }

    log_admin($admin['id'], 'product.create', 'product', $newId, [
        'slug'  => $fields['slug'] ?? '',
        'title' => $fields['title'] ?? '',
    ]);
    json_ok(['id' => $newId, 'orphans' => $orphans], 201);
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');

    $product = product_by_id($id);
    if (!$product) json_err('product_not_found', 404);

    $fields = product_fields($data, false, $id);
    check_duration($fields, $product);

    // Клуб — опора всей старой модели: его slug менять нельзя,
    // иначе club_product_id() перестанет его находить и действующие
    // участницы потеряют доступ.
    if ($product['slug'] === PRODUCT_CLUB_SLUG
        && array_key_exists('slug', $fields)
        && $fields['slug'] !== PRODUCT_CLUB_SLUG) {
        json_err('club_slug_locked');
    }

    if ($fields) {
        $set    = implode(', ', array_map(static fn($f) => "$f = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        db()->prepare("UPDATE products SET $set WHERE id = ?")->execute($params);
    }

    $orphans = [];
    if (array_key_exists('topic_ids', $data) && is_array($data['topic_ids'])) {
        $orphans = set_product_topics($id, $data['topic_ids']);
    }

    if ($fields || array_key_exists('topic_ids', $data)) {
        log_admin($admin['id'], 'product.update', 'product', $id, $fields);
    }
    json_ok(['orphans' => $orphans]);
}

if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_err('missing_id');

    $product = product_by_id($id);
    if (!$product) json_err('product_not_found', 404);
    if ($product['slug'] === PRODUCT_CLUB_SLUG) json_err('club_undeletable');

    // user_access удалился бы каскадом вместе с продуктом, поэтому
    // продукт с выданными доступами удалять нельзя — только скрыть.
    $hasAccess = db()->prepare('SELECT 1 FROM user_access WHERE product_id = ? LIMIT 1');
    $hasAccess->execute([$id]);
    if ($hasAccess->fetchColumn()) json_err('product_has_access');

    $hasOrders = db()->prepare('SELECT 1 FROM orders WHERE product_id = ? LIMIT 1');
    $hasOrders->execute([$id]);
    if ($hasOrders->fetchColumn()) json_err('product_has_orders');

    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    log_admin($admin['id'], 'product.delete', 'product', $id, ['slug' => $product['slug']]);
    json_ok(['orphans' => orphan_topics()]);
}

json_err('method_not_allowed', 405);
