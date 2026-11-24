<?php
/**
 * Управление доступом пользователя из админки.
 *
 * Названия действий сохранены с тех пор, когда модель была «одна
 * подписка на пользователя», чтобы не ломать admin/user-edit.php.
 * Внутри всё идёт через includes/access.php — ту же точку входа,
 * которой пользуется оплата. Необязательный product_id позволяет
 * управлять доступом к любому продукту; по умолчанию — клуб.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$admin = require_admin();
csrf_verify();

$data   = body();
$userId = (int)($data['user_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$userId) json_err('missing_user_id');

// Check user exists
$u = db()->prepare('SELECT id, name FROM users WHERE id = ? AND role = "user"');
$u->execute([$userId]);
$targetUser = $u->fetch();
if (!$targetUser) json_err('user_not_found', 404);

$productId = (int)($data['product_id'] ?? 0) ?: club_product_id();

// Сумма заказа — цена того продукта, которым управляем, а не всегда клуба.
$product = product_by_id($productId);
$amount  = $product
    ? product_price_for_user($userId, $product)
    : (float)club_price_for_user($userId);

switch ($action) {

  case 'activate':
    $startDate = $data['started_at'] ?? date('Y-m-d');
    // Пустая дата окончания = бессрочный доступ (так выдаются разовые
    // курсы). Если поля нет вовсе — месяц, как было раньше.
    $expiresDate = array_key_exists('expires_at', $data)
        ? (trim((string)$data['expires_at']) ?: null)
        : date('Y-m-d', strtotime('+30 days'));
    $payStatus = ($data['payment_pending'] ?? false) ? 'pending' : 'paid';

    settle_order($userId, $productId, $amount, 'manual', null, $payStatus);
    set_access($userId, $productId, $startDate, $expiresDate, false, 'manual', $admin['id']);
    break;

  case 'deactivate':
    revoke_access($userId, $productId, $admin['id']);
    break;

  case 'trial':
    $expiresDate = $data['expires_at'] ?? date('Y-m-d', strtotime('+7 days'));
    set_access($userId, $productId, date('Y-m-d'), $expiresDate, true, 'promo', $admin['id']);
    break;

  case 'pause':
    $pauseNotes = trim($data['pause_notes'] ?? '');
    freeze_access($userId, $productId, $pauseNotes, $admin['id']);
    break;

  case 'unpause':
    $row = get_access_row($userId, $productId);
    if (!$row || !$row['is_frozen']) json_err('not_paused');
    unfreeze_access($userId, $productId, $admin['id']);
    break;

  case 'confirm_payment':
    settle_order($userId, $productId, $amount, 'manual', null, 'paid');
    log_admin($admin['id'], 'access.payment_confirmed', 'user', $userId);
    break;

  case 'set_pending':
    settle_order($userId, $productId, $amount, 'manual', null, 'pending');
    log_admin($admin['id'], 'access.payment_pending', 'user', $userId);
    break;

  case 'update_expires':
    $expiresDate = $data['expires_at'] ?? '';
    if (!$expiresDate) json_err('missing_expires_at');
    set_access_expiry($userId, $productId, $expiresDate, $admin['id']);
    break;

  case 'set_notes':
    set_access_notes($userId, $productId, $data['notes'] ?? null);
    break;

  default:
    json_err('unknown_action');
}

json_ok();
