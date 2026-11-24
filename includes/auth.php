<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/access.php';

// ── Session bootstrap ──────────────────────────────────────────
function start_user_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(SESSION_USER_KEY);
        session_start();
    }
}

function start_admin_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(SESSION_ADMIN_KEY);
        session_start();
    }
}

// ── Auth checks ───────────────────────────────────────────────
function current_user(): ?array {
    start_user_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $s = db()->prepare('SELECT id, name, last_name, email, phone, vk_url, tg_url, role, telegram_chat_id FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    return $s->fetch() ?: null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            json_err('unauthenticated', 401);
        }
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /auth/login.php' . ($next ? '?next=' . $next : ''));
        exit;
    }
    return $user;
}

function current_admin(): ?array {
    start_admin_session();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $s = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND role = "admin"');
    $s->execute([$_SESSION['admin_id']]);
    return $s->fetch() ?: null;
}

function require_admin(): array {
    $admin = current_admin();
    if (!$admin) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            json_err('unauthenticated', 401);
        }
        header('Location: /admin/login.php');
        exit;
    }
    return $admin;
}

// ── Subscription helpers (УСТАРЕЛИ) ───────────────────────────
// Модель доступа переехала в includes/access.php: продукты и
// user_access вместо одной подписки на пользователя. Функции ниже
// оставлены обёртками над новой моделью, чтобы ни одно пропущенное
// место не считало доступ по старой таблице. В новом коде вызывайте
// has_access() / can_view_lesson() / access_display_status() напрямую.

/**
 * @deprecated Используйте get_access_row($userId, $productId).
 * Читает старую таблицу subscriptions. Данные там актуальны, пока
 * включено зеркалирование в legacy_sync_subscription().
 */
function get_subscription(int $userId): ?array {
    $s = db()->prepare(
        'SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
    );
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

/** @deprecated Используйте has_paid_product_before(). */
function has_paid_before(int $userId): bool {
    return has_paid_product_before($userId, club_product_id());
}

/** @deprecated Используйте has_access() или user_has_any_access(). */
function has_active_access(?array $sub): bool {
    if (!$sub || empty($sub['user_id'])) return false;
    return has_access((int)$sub['user_id'], club_product_id());
}

/** @deprecated Используйте access_display_status($userId, $productId). */
function subscription_display_status(?array $sub): string {
    if (!$sub || empty($sub['user_id'])) return 'inactive';
    return access_display_status((int)$sub['user_id'], club_product_id());
}

// ── Admin audit log ───────────────────────────────────────────
function log_admin(int $adminId, string $action, string $targetType = '', int $targetId = 0, array $details = []): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    db()->prepare(
        'INSERT INTO admin_log (admin_id, action, target_type, target_id, details, ip)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $adminId,
        $action,
        $targetType ?: null,
        $targetId ?: null,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        $ip,
    ]);
}

// ── Rate limiting (login) ─────────────────────────────────────
function check_rate_limit(string $ip): void {
    $key = 'rl_' . md5($ip);
    $attempts = (int)($_SESSION[$key . '_count'] ?? 0);
    $blocked_until = (int)($_SESSION[$key . '_blocked'] ?? 0);

    if ($blocked_until && time() < $blocked_until) {
        json_err('too_many_attempts', 429, ['retry_after' => $blocked_until - time()]);
    }
    if ($blocked_until && time() >= $blocked_until) {
        unset($_SESSION[$key . '_count'], $_SESSION[$key . '_blocked'], $_SESSION[$key . '_first']);
    }
}

function record_failed_login(string $ip): void {
    $key = 'rl_' . md5($ip);
    if (!isset($_SESSION[$key . '_first'])) {
        $_SESSION[$key . '_first'] = time();
        $_SESSION[$key . '_count'] = 0;
    }
    $_SESSION[$key . '_count'] = ($_SESSION[$key . '_count'] ?? 0) + 1;
    if ($_SESSION[$key . '_count'] >= 5 && (time() - $_SESSION[$key . '_first']) <= 900) {
        $_SESSION[$key . '_blocked'] = time() + 1800;
    }
}

function clear_rate_limit(string $ip): void {
    $key = 'rl_' . md5($ip);
    unset($_SESSION[$key . '_count'], $_SESSION[$key . '_blocked'], $_SESSION[$key . '_first']);
}
