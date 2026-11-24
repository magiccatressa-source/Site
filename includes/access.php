<?php
/**
 * Доступы к продуктам — единственная точка правды.
 *
 * Проверка доступа всегда идёт через has_access() / can_view_lesson(),
 * выдача — всегда через grant_access(). И оплата, и ручная выдача в
 * админке пользуются одной и той же функцией, различается только
 * параметр $source.
 *
 * Таблица subscriptions считается устаревшей. Пока она жива, функции
 * выдачи дублируют запись в неё (см. блок LEGACY внизу файла), чтобы
 * откат на предыдущую версию кода не терял свежие оплаты.
 */

require_once __DIR__ . '/db.php';

/** Slug продукта "Клуб" — единственный продукт до появления курсов. */
const PRODUCT_CLUB_SLUG = 'club';

/**
 * Готова ли мультипродуктовая модель в базе.
 *
 * Деплой кода и применение миграции — два отдельных действия, и код
 * может оказаться на сервере раньше SQL. Если это случилось, все
 * функции ниже молча работают по старой таблице subscriptions, и
 * действующие участники клуба доступ не теряют. Как только миграция
 * применена, проверка начинает возвращать true сама, без правок кода.
 */
function access_model_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try {
            db()->query('SELECT 1 FROM user_access LIMIT 1');
            $ready = (bool)db()->query(
                "SELECT 1 FROM products WHERE slug = '" . PRODUCT_CLUB_SLUG . "' LIMIT 1"
            )->fetchColumn();
        } catch (Throwable $e) {
            error_log('access_model_ready: мультипродуктовые таблицы недоступны, '
                . 'работаю по старой модели — ' . $e->getMessage());
            $ready = false;
        }
    }
    return $ready;
}

// ── Продукты ──────────────────────────────────────────────────

function product_by_slug(string $slug): ?array {
    static $cache = [];
    if (!array_key_exists($slug, $cache)) {
        try {
            $s = db()->prepare('SELECT * FROM products WHERE slug = ? LIMIT 1');
            $s->execute([$slug]);
            $cache[$slug] = $s->fetch() ?: null;
        } catch (Throwable $e) {
            $cache[$slug] = null;
        }
    }
    return $cache[$slug];
}

function product_by_id(int $id): ?array {
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        try {
            $s = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
            $s->execute([$id]);
            $cache[$id] = $s->fetch() ?: null;
        } catch (Throwable $e) {
            $cache[$id] = null;
        }
    }
    return $cache[$id];
}

function club_product(): ?array {
    return product_by_slug(PRODUCT_CLUB_SLUG);
}

function club_product_id(): int {
    $p = club_product();
    return $p ? (int)$p['id'] : 0;
}

/** Все продукты для витрины. */
function all_products(bool $activeOnly = true): array {
    if (!access_model_ready()) return [];
    $sql = 'SELECT * FROM products';
    if ($activeOnly) $sql .= ' WHERE is_active = 1';
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/**
 * Цена продукта для конкретного пользователя: базовая для первой
 * покупки, цена продления — если он по этому продукту уже платил.
 */
function product_price_for_user(int $userId, array $product): float {
    if ($product['price_renewal'] !== null
        && has_paid_product_before($userId, (int)$product['id'])) {
        return (float)$product['price_renewal'];
    }
    return (float)$product['price'];
}

/**
 * Цена клуба для пользователя — берётся из products, поэтому
 * поменять её можно в админке, не трогая код. Хардкод 3000
 * остаётся только на случай, если миграция ещё не применена.
 *
 * С 2026-11-25 цена одна и та же для первой покупки и для
 * продления: price_renewal у клуба = NULL.
 */
function club_price_for_user(int $userId): int {
    $p = club_product();
    if ($p) return (int)round(product_price_for_user($userId, $p));
    return 3000;
}

// ── Проверка доступа ──────────────────────────────────────────

/**
 * Есть ли у пользователя активный доступ к продукту.
 *
 * is_frozen здесь намеренно НЕ проверяется: заморозка в клубе не
 * отбирает доступ, а лишь означает, что админ по заявке продлит
 * дату окончания. Так было до мультипродуктовой модели, и менять
 * это поведение нельзя — иначе участники на паузе потеряют доступ.
 */
function has_access(int $userId, int $productId): bool {
    if (!$userId) return false;
    if (!access_model_ready()) return legacy_has_access($userId);
    if (!$productId) return false;
    $s = db()->prepare(
        "SELECT 1 FROM user_access
         WHERE user_id = ? AND product_id = ? AND status = 'active'
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1"
    );
    $s->execute([$userId, $productId]);
    return (bool)$s->fetchColumn();
}

function has_access_to_slug(int $userId, string $slug): bool {
    $p = product_by_slug($slug);
    return $p ? has_access($userId, (int)$p['id']) : false;
}

/** Есть ли хоть один активный доступ (для общих блоков ЛК). */
function user_has_any_access(int $userId): bool {
    if (!$userId) return false;
    if (!access_model_ready()) return legacy_has_access($userId);
    $s = db()->prepare(
        "SELECT 1 FROM user_access
         WHERE user_id = ? AND status = 'active'
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1"
    );
    $s->execute([$userId]);
    return (bool)$s->fetchColumn();
}

/**
 * Запись доступа по продукту — для показа статуса и дат.
 * Активная имеет приоритет, иначе самая свежая.
 */
function get_access_row(int $userId, int $productId): ?array {
    if (!access_model_ready()) return legacy_access_row($userId);
    $s = db()->prepare(
        "SELECT * FROM user_access
         WHERE user_id = ? AND product_id = ?
         ORDER BY (status = 'active' AND (expires_at IS NULL OR expires_at > NOW())) DESC,
                  expires_at DESC, id DESC
         LIMIT 1"
    );
    $s->execute([$userId, $productId]);
    return $s->fetch() ?: null;
}

/** Все активные доступы пользователя вместе с данными продуктов. */
function get_active_access(int $userId): array {
    if (!access_model_ready()) return [];
    $s = db()->prepare(
        "SELECT ua.*, p.slug, p.title, p.subtitle, p.type, p.cover_image,
                p.price, p.price_renewal, p.payment_url, p.contact_url
         FROM user_access ua
         JOIN products p ON p.id = ua.product_id
         WHERE ua.user_id = ? AND ua.status = 'active'
           AND (ua.expires_at IS NULL OR ua.expires_at > NOW())
         ORDER BY p.sort_order, p.id"
    );
    $s->execute([$userId]);
    return $s->fetchAll();
}

/** @return int[] */
function get_accessible_product_ids(int $userId): array {
    if (!$userId || !access_model_ready()) return [];
    $s = db()->prepare(
        "SELECT DISTINCT product_id FROM user_access
         WHERE user_id = ? AND status = 'active'
           AND (expires_at IS NULL OR expires_at > NOW())"
    );
    $s->execute([$userId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/** @return int[] id тем, доступных пользователю */
function accessible_topic_ids(int $userId): array {
    if (!$userId) return [];
    if (!access_model_ready()) {
        // Старая модель: доступ ко всем темам или ни к одной.
        if (!legacy_has_access($userId)) return [];
        return array_map('intval', db()->query(
            'SELECT id FROM topics WHERE is_visible = 1'
        )->fetchAll(PDO::FETCH_COLUMN));
    }
    $s = db()->prepare(
        "SELECT DISTINCT pt.topic_id
         FROM product_topics pt
         JOIN user_access ua ON ua.product_id = pt.product_id
          AND ua.user_id = ? AND ua.status = 'active'
          AND (ua.expires_at IS NULL OR ua.expires_at > NOW())"
    );
    $s->execute([$userId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/** @return int[] продукты, в которые входит урок (через его тему) */
function lesson_product_ids(int $lessonId): array {
    if (!access_model_ready()) return [];
    $s = db()->prepare(
        'SELECT pt.product_id
         FROM lessons l
         JOIN product_topics pt ON pt.topic_id = l.topic_id
         WHERE l.id = ?'
    );
    $s->execute([$lessonId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Может ли пользователь смотреть урок.
 *
 * Урок, не привязанный ни к одному продукту, не видит никто —
 * это сделано намеренно (fail-closed): лучше временно спрятать
 * контент, чем случайно отдать платный курс участникам клуба.
 * Поэтому админка при создании темы обязана привязать продукт.
 *
 * Пробный доступ открывает только уроки с is_trial = 1. Раньше это
 * ограничение было лишь визуальным в списке уроков, а страница урока
 * пускала пробного пользователя на любой урок по прямой ссылке.
 */
function can_view_lesson(int $userId, int $lessonId): bool {
    if (!$userId || !$lessonId) return false;
    if (!access_model_ready()) return legacy_can_view_lesson($userId, $lessonId);
    $s = db()->prepare(
        "SELECT 1
         FROM lessons l
         JOIN product_topics pt ON pt.topic_id = l.topic_id
         JOIN user_access ua ON ua.product_id = pt.product_id
          AND ua.user_id = ? AND ua.status = 'active'
          AND (ua.expires_at IS NULL OR ua.expires_at > NOW())
         WHERE l.id = ? AND l.is_visible = 1
           AND (ua.is_trial = 0 OR l.is_trial = 1)
         LIMIT 1"
    );
    $s->execute([$userId, $lessonId]);
    return (bool)$s->fetchColumn();
}

/**
 * Ключ статуса доступа для бейджей в ЛК и админке.
 * Значения совпадают с прежними ключами subscription_display_status(),
 * поэтому существующие массивы подписей менять не нужно.
 *
 * @return string active|paused|pending_payment|trial|inactive
 */
function access_display_status(int $userId, int $productId): string {
    $row = get_access_row($userId, $productId);
    if (!$row || $row['status'] !== 'active') return 'inactive';

    $expired = $row['expires_at'] !== null && strtotime($row['expires_at']) <= time();
    if ($expired && !$row['is_frozen']) return 'inactive';

    if ($row['is_trial'])  return 'trial';
    if ($row['is_frozen']) return 'paused';
    if (product_payment_status($userId, $productId) === 'pending') return 'pending_payment';
    return 'active';
}

// ── Выдача и отзыв доступа ────────────────────────────────────

/**
 * Выдать или продлить доступ. Единственная точка входа — и для
 * оплаты, и для ручной выдачи в админке.
 *
 * @param int|null $days  срок в днях; null — взять duration_days
 *                        продукта, а если и он null — бессрочно
 * @param string   $source manual|payment|promo
 * @return int id записи user_access
 */
function grant_access(
    int $userId,
    int $productId,
    ?int $days = null,
    string $source = 'manual',
    ?int $orderId = null,
    ?int $adminId = null,
    bool $isTrial = false
): int {
    if (!access_model_ready()) {
        legacy_grant_club_access($userId, $days, $isTrial);
        return 0;
    }

    $product = product_by_id($productId);
    if (!$product) throw new RuntimeException('product_not_found');

    if ($days === null && $product['duration_days'] !== null) {
        $days = (int)$product['duration_days'];
    }

    $existing = db()->prepare(
        "SELECT * FROM user_access
         WHERE user_id = ? AND product_id = ? AND status = 'active'
         ORDER BY expires_at DESC, id DESC LIMIT 1"
    );
    $existing->execute([$userId, $productId]);
    $row = $existing->fetch() ?: null;

    // Продление считается от более поздней из двух дат: сегодня или
    // текущее окончание. Так оплата заранее не съедает остаток срока.
    $expiresAt = null;
    if ($days !== null) {
        $base = time();
        if ($row && $row['expires_at'] !== null) {
            $base = max($base, strtotime($row['expires_at']));
        }
        $expiresAt = date('Y-m-d', strtotime("+{$days} day", $base)) . ' 23:59:59';
    }

    if ($row) {
        db()->prepare(
            "UPDATE user_access
             SET expires_at = ?, status = 'active', is_trial = ?, source = ?,
                 order_id = COALESCE(?, order_id)
             WHERE id = ?"
        )->execute([$expiresAt, $isTrial ? 1 : 0, $source, $orderId, $row['id']]);
        $accessId = (int)$row['id'];
    } else {
        db()->prepare(
            "INSERT INTO user_access
             (user_id, product_id, starts_at, expires_at, status, is_trial, source, order_id)
             VALUES (?, ?, NOW(), ?, 'active', ?, ?, ?)"
        )->execute([$userId, $productId, $expiresAt, $isTrial ? 1 : 0, $source, $orderId]);
        $accessId = (int)db()->lastInsertId();
    }

    if ($adminId && function_exists('log_admin')) {
        log_admin($adminId, 'access.grant', 'user', $userId, [
            'product'    => $product['slug'],
            'expires_at' => $expiresAt,
            'source'     => $source,
            'is_trial'   => $isTrial,
        ]);
    }

    legacy_sync_subscription($userId);
    return $accessId;
}

/**
 * Задать период доступа явными датами — для ручного управления в
 * админке, где Любовь сама выбирает «с» и «до». Отличается от
 * grant_access() тем, что ничего не считает от срока продукта.
 *
 * @param string|null $expiresAt 'Y-m-d' или NULL для бессрочного доступа
 */
function set_access(
    int $userId,
    int $productId,
    ?string $startsAt,
    ?string $expiresAt,
    bool $isTrial = false,
    string $source = 'manual',
    ?int $adminId = null
): void {
    if (!access_model_ready()) {
        $status = $isTrial ? 'trial' : 'active';
        $s = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $s->execute([$userId]);
        if ($subId = $s->fetchColumn()) {
            db()->prepare(
                'UPDATE subscriptions SET status = ?, is_paused = 0, started_at = ?, expires_at = ?,
                        pause_started_at = NULL, pause_notes = NULL WHERE id = ?'
            )->execute([$status, $startsAt, $expiresAt, $subId]);
        } else {
            db()->prepare(
                'INSERT INTO subscriptions (user_id, status, started_at, expires_at) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $status, $startsAt, $expiresAt]);
        }
        return;
    }

    // Дата окончания — конец указанного дня, иначе доступ обрывался бы
    // в полночь и последний день пропадал.
    $expiresDt = $expiresAt ? $expiresAt . ' 23:59:59' : null;
    $startsDt  = $startsAt ? $startsAt . ' 00:00:00' : date('Y-m-d H:i:s');

    $existing = db()->prepare(
        "SELECT id FROM user_access WHERE user_id = ? AND product_id = ?
         ORDER BY (status = 'active') DESC, id DESC LIMIT 1"
    );
    $existing->execute([$userId, $productId]);
    $id = $existing->fetchColumn();

    if ($id) {
        db()->prepare(
            "UPDATE user_access
             SET starts_at = ?, expires_at = ?, status = 'active', is_trial = ?,
                 source = ?, is_frozen = 0, frozen_at = NULL, freeze_notes = NULL
             WHERE id = ?"
        )->execute([$startsDt, $expiresDt, $isTrial ? 1 : 0, $source, $id]);
    } else {
        db()->prepare(
            "INSERT INTO user_access (user_id, product_id, starts_at, expires_at, status, is_trial, source)
             VALUES (?, ?, ?, ?, 'active', ?, ?)"
        )->execute([$userId, $productId, $startsDt, $expiresDt, $isTrial ? 1 : 0, $source]);
    }

    if ($adminId && function_exists('log_admin')) {
        $p = product_by_id($productId);
        log_admin($adminId, $isTrial ? 'access.trial' : 'access.grant', 'user', $userId, [
            'product'    => $p['slug'] ?? $productId,
            'starts_at'  => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }
    legacy_sync_subscription($userId);
}

/**
 * Поменять только дату окончания, не трогая заморозку и статус.
 * @param string|null $expiresAt 'Y-m-d' или NULL для бессрочного доступа
 */
function set_access_expiry(int $userId, int $productId, ?string $expiresAt, ?int $adminId = null): void {
    if (!access_model_ready()) {
        db()->prepare('UPDATE subscriptions SET expires_at = ? WHERE user_id = ?')
            ->execute([$expiresAt, $userId]);
        return;
    }

    db()->prepare(
        "UPDATE user_access SET expires_at = ?
         WHERE user_id = ? AND product_id = ? AND status = 'active'"
    )->execute([$expiresAt ? $expiresAt . ' 23:59:59' : null, $userId, $productId]);

    if ($adminId && function_exists('log_admin')) {
        $p = product_by_id($productId);
        log_admin($adminId, 'access.update_expires', 'user', $userId, [
            'product'    => $p['slug'] ?? $productId,
            'expires_at' => $expiresAt,
        ]);
    }
    legacy_sync_subscription($userId);
}

/** Заметка администратора на доступе. */
function set_access_notes(int $userId, int $productId, ?string $notes): void {
    if (!access_model_ready()) {
        db()->prepare('UPDATE subscriptions SET notes = ? WHERE user_id = ?')
            ->execute([$notes, $userId]);
        return;
    }
    db()->prepare(
        'UPDATE user_access SET notes = ? WHERE user_id = ? AND product_id = ?'
    )->execute([$notes, $userId, $productId]);
    legacy_sync_subscription($userId);
}

/** Отозвать доступ к продукту. */
function revoke_access(int $userId, int $productId, ?int $adminId = null): void {
    if (!access_model_ready()) {
        db()->prepare("UPDATE subscriptions SET status = 'inactive', is_paused = 0 WHERE user_id = ?")
            ->execute([$userId]);
        return;
    }
    db()->prepare(
        "UPDATE user_access SET status = 'revoked', is_frozen = 0,
                frozen_at = NULL, freeze_notes = NULL
         WHERE user_id = ? AND product_id = ? AND status = 'active'"
    )->execute([$userId, $productId]);

    if ($adminId && function_exists('log_admin')) {
        $p = product_by_id($productId);
        log_admin($adminId, 'access.revoke', 'user', $userId, [
            'product' => $p['slug'] ?? $productId,
        ]);
    }
    legacy_sync_subscription($userId);
}

/**
 * Продлить активный доступ на N дней от текущей даты окончания.
 * Используется заявками на заморозку и массовым продлением —
 * там семантика именно «добавить дни», а не «отсчитать заново».
 */
function extend_access(int $userId, int $productId, int $days, ?int $adminId = null): void {
    if (!access_model_ready()) {
        db()->prepare(
            'UPDATE subscriptions
             SET expires_at = DATE_ADD(COALESCE(expires_at, CURDATE()), INTERVAL ? DAY)
             WHERE user_id = ?'
        )->execute([$days, $userId]);
        return;
    }
    // expires_at IS NOT NULL обязательно: бессрочный доступ продлевать
    // нечем, и без этого условия он превратился бы в срочный.
    db()->prepare(
        "UPDATE user_access
         SET expires_at = TIMESTAMP(DATE_ADD(DATE(expires_at), INTERVAL ? DAY), '23:59:59')
         WHERE user_id = ? AND product_id = ? AND status = 'active'
           AND expires_at IS NOT NULL"
    )->execute([$days, $userId, $productId]);

    if ($adminId && function_exists('log_admin')) {
        $p = product_by_id($productId);
        log_admin($adminId, 'access.extend', 'user', $userId, [
            'product' => $p['slug'] ?? $productId,
            'days'    => $days,
        ]);
    }
    legacy_sync_subscription($userId);
}

/**
 * Массовое продление всех активных доступов по продукту.
 * @return int сколько записей затронуто
 */
function bulk_extend_product(int $productId, int $days): int {
    if (!access_model_ready()) {
        $s = db()->prepare(
            "UPDATE subscriptions SET expires_at = DATE_ADD(expires_at, INTERVAL ? DAY)
             WHERE status IN ('active','trial') AND expires_at IS NOT NULL"
        );
        $s->execute([$days]);
        return $s->rowCount();
    }

    $s = db()->prepare(
        "UPDATE user_access
         SET expires_at = TIMESTAMP(DATE_ADD(DATE(expires_at), INTERVAL ? DAY), '23:59:59')
         WHERE product_id = ? AND status = 'active' AND expires_at IS NOT NULL"
    );
    $s->execute([$days, $productId]);
    $count = $s->rowCount();

    // Зеркалирование в устаревшую таблицу (см. блок LEGACY)
    if ($productId === club_product_id()) {
        db()->prepare(
            "UPDATE subscriptions SET expires_at = DATE_ADD(expires_at, INTERVAL ? DAY)
             WHERE status IN ('active','trial') AND expires_at IS NOT NULL"
        )->execute([$days]);
    }
    return $count;
}

/** Снять флаг заморозки, ничего не продлевая. */
function clear_freeze(int $userId, int $productId): void {
    if (!access_model_ready()) {
        db()->prepare(
            'UPDATE subscriptions SET is_paused = 0, pause_started_at = NULL, pause_notes = NULL
             WHERE user_id = ?'
        )->execute([$userId]);
        return;
    }
    db()->prepare(
        "UPDATE user_access SET is_frozen = 0, frozen_at = NULL, freeze_notes = NULL
         WHERE user_id = ? AND product_id = ? AND status = 'active'"
    )->execute([$userId, $productId]);
    legacy_sync_subscription($userId);
}

/** Поставить доступ на заморозку (доступ при этом сохраняется). */
function freeze_access(int $userId, int $productId, string $notes = '', ?int $adminId = null): void {
    if (!access_model_ready()) {
        db()->prepare(
            'UPDATE subscriptions SET is_paused = 1, pause_started_at = CURDATE(), pause_notes = ?
             WHERE user_id = ?'
        )->execute([$notes ?: null, $userId]);
        return;
    }
    db()->prepare(
        "UPDATE user_access
         SET is_frozen = 1, frozen_at = CURDATE(), freeze_notes = ?
         WHERE user_id = ? AND product_id = ? AND status = 'active'"
    )->execute([$notes ?: null, $userId, $productId]);

    if ($adminId && function_exists('log_admin')) {
        log_admin($adminId, 'access.freeze', 'user', $userId, ['notes' => $notes]);
    }
    legacy_sync_subscription($userId);
}

/**
 * Снять заморозку, продлив срок на число дней простоя —
 * ровно так же, как это делала старая кнопка «снять паузу».
 */
function unfreeze_access(int $userId, int $productId, ?int $adminId = null): int {
    $row = get_access_row($userId, $productId);
    if (!$row || !$row['is_frozen']) return 0;

    if (!access_model_ready()) {
        $frozenAt = $row['frozen_at'] ?: date('Y-m-d');
        $days = (int)((new DateTime())->diff(new DateTime($frozenAt))->days);
        db()->prepare(
            'UPDATE subscriptions
             SET is_paused = 0, pause_started_at = NULL, pause_notes = NULL,
                 expires_at = DATE_ADD(expires_at, INTERVAL ? DAY)
             WHERE user_id = ?'
        )->execute([$days, $userId]);
        return $days;
    }

    $frozenAt = $row['frozen_at'] ?: date('Y-m-d');
    $days = (int)((new DateTime())->diff(new DateTime($frozenAt))->days);

    db()->prepare(
        "UPDATE user_access
         SET is_frozen = 0, frozen_at = NULL, freeze_notes = NULL,
             expires_at = CASE WHEN expires_at IS NULL THEN NULL
                               ELSE TIMESTAMP(DATE_ADD(DATE(expires_at), INTERVAL ? DAY), '23:59:59')
                          END
         WHERE id = ?"
    )->execute([$days, $row['id']]);

    if ($adminId && function_exists('log_admin')) {
        log_admin($adminId, 'access.unfreeze', 'user', $userId, ['days_added' => $days]);
    }
    legacy_sync_subscription($userId);
    return $days;
}

// ── Заказы ────────────────────────────────────────────────────

function create_order(
    int $userId,
    int $productId,
    float $amount,
    string $provider,
    string $status = 'pending',
    ?string $externalId = null,
    ?string $notes = null
): int {
    if (!access_model_ready()) return 0;
    db()->prepare(
        'INSERT INTO orders
         (user_id, product_id, amount, status, payment_provider, external_id, notes, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId, $productId, $amount, $status, $provider, $externalId, $notes,
        $status === 'paid' ? date('Y-m-d H:i:s') : null,
    ]);
    return (int)db()->lastInsertId();
}

function mark_order_paid(int $orderId, ?string $externalId = null): void {
    if (!$orderId || !access_model_ready()) return;
    db()->prepare(
        "UPDATE orders
         SET status = 'paid', paid_at = NOW(), external_id = COALESCE(?, external_id)
         WHERE id = ?"
    )->execute([$externalId, $orderId]);
}

/**
 * Закрыть оплату: если по продукту уже висит незакрытый заказ
 * (например, человек нажал «оплатить» и ушёл на платёжную форму) —
 * помечаем оплаченным его, иначе создаём новый. Так в истории не
 * плодятся дубли на один платёж.
 *
 * @return int id заказа, 0 если модель ещё не готова
 */
function settle_order(
    int $userId,
    int $productId,
    float $amount,
    string $provider,
    ?string $externalId = null,
    string $status = 'paid'
): int {
    if (!access_model_ready()) return 0;

    $pending = db()->prepare(
        "SELECT id FROM orders
         WHERE user_id = ? AND product_id = ? AND status = 'pending'
         ORDER BY id DESC LIMIT 1"
    );
    $pending->execute([$userId, $productId]);
    $orderId = (int)$pending->fetchColumn();

    if ($orderId) {
        db()->prepare(
            'UPDATE orders
             SET status = ?, amount = ?, payment_provider = ?,
                 external_id = COALESCE(?, external_id),
                 paid_at = CASE WHEN ? = \'paid\' THEN NOW() ELSE paid_at END
             WHERE id = ?'
        )->execute([$status, $amount, $provider, $externalId, $status, $orderId]);
        return $orderId;
    }

    return create_order($userId, $productId, $amount, $provider, $status, $externalId);
}

function latest_order(int $userId, int $productId): ?array {
    if (!access_model_ready()) return null;
    $s = db()->prepare(
        'SELECT * FROM orders WHERE user_id = ? AND product_id = ?
         ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$userId, $productId]);
    return $s->fetch() ?: null;
}

/** @return string|null paid|pending|null */
function product_payment_status(int $userId, int $productId): ?string {
    if (!access_model_ready()) {
        $row = legacy_access_row($userId);
        return $row ? $row['payment_status'] : null;
    }
    $o = latest_order($userId, $productId);
    if (!$o) return null;
    return in_array($o['status'], ['paid', 'pending'], true) ? $o['status'] : null;
}

/** Платил ли пользователь за этот продукт раньше — для цены продления. */
function has_paid_product_before(int $userId, int $productId): bool {
    if (!access_model_ready()) {
        $s = db()->prepare(
            "SELECT 1 FROM subscriptions WHERE user_id = ? AND payment_status = 'paid' LIMIT 1"
        );
        $s->execute([$userId]);
        return (bool)$s->fetchColumn();
    }
    $s = db()->prepare(
        "SELECT 1 FROM orders
         WHERE user_id = ? AND product_id = ? AND status = 'paid' LIMIT 1"
    );
    $s->execute([$userId, $productId]);
    return (bool)$s->fetchColumn();
}

// ── LEGACY ────────────────────────────────────────────────────
// Всё ниже существует только ради возможности откатить код на
// версию до мультипродуктовой модели, не потеряв свежие оплаты.
// Удалить вместе с таблицей subscriptions на этапе 5.
// ──────────────────────────────────────────────────────────────

/**
 * Старая проверка доступа по таблице subscriptions — ровно та логика,
 * что была в has_active_access() до мультипродуктовой модели.
 * Используется, только пока миграция не применена.
 */
function legacy_has_access(int $userId): bool {
    try {
        $s = db()->prepare(
            'SELECT status, expires_at FROM subscriptions
             WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $s->execute([$userId]);
        $sub = $s->fetch();
        if (!$sub) return false;
        if (!in_array($sub['status'], ['active', 'trial'], true)) return false;
        if ($sub['expires_at'] && $sub['expires_at'] < date('Y-m-d')) return false;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Старая проверка доступа к уроку, с тем же правилом про пробный доступ. */
function legacy_can_view_lesson(int $userId, int $lessonId): bool {
    if (!legacy_has_access($userId)) return false;
    try {
        $s = db()->prepare(
            'SELECT status FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $s->execute([$userId]);
        if ($s->fetchColumn() !== 'trial') return true;

        $l = db()->prepare('SELECT is_trial FROM lessons WHERE id = ? AND is_visible = 1');
        $l->execute([$lessonId]);
        return (bool)$l->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Подписка из subscriptions, приведённая к форме записи user_access,
 * чтобы вызывающий код не знал, по какой модели он сейчас работает.
 */
function legacy_access_row(int $userId): ?array {
    try {
        $s = db()->prepare(
            'SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $s->execute([$userId]);
        $sub = $s->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$sub) return null;

    $status = 'expired';
    if (in_array($sub['status'], ['active', 'trial'], true)) {
        $status = 'active';
    } elseif ($sub['status'] === 'inactive') {
        $status = 'revoked';
    }

    return [
        'id'             => (int)$sub['id'],
        'user_id'        => (int)$sub['user_id'],
        'product_id'     => club_product_id(),
        'starts_at'      => $sub['started_at'],
        'expires_at'     => $sub['expires_at'] ? $sub['expires_at'] . ' 23:59:59' : null,
        'status'         => $status,
        'is_frozen'      => (int)$sub['is_paused'],
        'frozen_at'      => $sub['pause_started_at'],
        'freeze_notes'   => $sub['pause_notes'],
        'is_trial'       => $sub['status'] === 'trial' ? 1 : 0,
        'source'         => $sub['payment_status'] ? 'payment' : 'manual',
        'order_id'       => null,
        'notes'          => $sub['notes'],
        'payment_status' => $sub['payment_status'],
        'created_at'     => $sub['created_at'],
        'updated_at'     => $sub['updated_at'],
    ];
}

/** Старая выдача подписки на клуб — только пока миграция не применена. */
function legacy_grant_club_access(int $userId, ?int $days, bool $isTrial): void {
    $days   = $days ?? 30;
    $status = $isTrial ? 'trial' : 'active';
    $expires = date('Y-m-d', strtotime("+{$days} day"));

    $s = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $s->execute([$userId]);
    $subId = $s->fetchColumn();

    if ($subId) {
        db()->prepare(
            'UPDATE subscriptions SET status = ?, is_paused = 0, started_at = CURDATE(), expires_at = ?
             WHERE id = ?'
        )->execute([$status, $expires, $subId]);
    } else {
        db()->prepare(
            'INSERT INTO subscriptions (user_id, status, started_at, expires_at) VALUES (?, ?, CURDATE(), ?)'
        )->execute([$userId, $status, $expires]);
    }
}

/**
 * Отразить доступ к клубу обратно в устаревшую таблицу subscriptions.
 * Ошибки здесь подавляются: сбой зеркалирования не должен ломать
 * настоящую выдачу доступа.
 */
function legacy_sync_subscription(int $userId): void {
    try {
        $clubId = club_product_id();
        if (!$clubId) return;

        $row = get_access_row($userId, $clubId);
        if (!$row) return;

        $status = 'inactive';
        if ($row['status'] === 'active') {
            $status = $row['is_trial'] ? 'trial' : 'active';
            if ($row['expires_at'] !== null && strtotime($row['expires_at']) <= time()) {
                $status = 'expired';
            }
        } elseif ($row['status'] === 'expired') {
            $status = 'expired';
        }

        // payment_method в живой таблице subscriptions нет: миграция 0006
        // на прод не применялась. Способ оплаты теперь живёт в
        // orders.payment_provider, зеркалить его некуда и не нужно.
        $order  = latest_order($userId, $clubId);
        $payStatus = $order && in_array($order['status'], ['paid', 'pending'], true)
            ? $order['status'] : null;

        $expires = $row['expires_at'] !== null ? date('Y-m-d', strtotime($row['expires_at'])) : null;
        $starts  = $row['starts_at'] !== null ? date('Y-m-d', strtotime($row['starts_at'])) : null;

        $exists = db()->prepare('SELECT id FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $exists->execute([$userId]);
        $subId = $exists->fetchColumn();

        if ($subId) {
            db()->prepare(
                'UPDATE subscriptions
                 SET status = ?, is_paused = ?, pause_started_at = ?, pause_notes = ?,
                     payment_status = ?, started_at = ?, expires_at = ?,
                     notes = ?
                 WHERE id = ?'
            )->execute([
                $status, (int)$row['is_frozen'], $row['frozen_at'], $row['freeze_notes'],
                $payStatus, $starts, $expires, $row['notes'], $subId,
            ]);
        } else {
            db()->prepare(
                'INSERT INTO subscriptions
                 (user_id, status, is_paused, pause_started_at, pause_notes,
                  payment_status, started_at, expires_at, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId, $status, (int)$row['is_frozen'], $row['frozen_at'], $row['freeze_notes'],
                $payStatus, $starts, $expires, $row['notes'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('legacy_sync_subscription failed for user ' . $userId . ': ' . $e->getMessage());
    }
}
