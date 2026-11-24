<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();

$search    = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 30;
$offset    = ($page - 1) * $perPage;

$where  = ['u.role = "user"'];
$params = [];

if ($search) {
    $where[]  = '(u.name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Доступ к клубу + статус последнего заказа по нему.
// Фильтр по статусу считается в PHP и применяется ДО постраничной
// разбивки — раньше он был заявлен в интерфейсе, но не работал.
if (access_model_ready()) {
    $clubId = club_product_id();
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.last_name, u.email, u.phone, u.vk_url, u.tg_url,
                u.created_at, u.email_verified,
                (SELECT o.status FROM orders o
                  WHERE o.user_id = u.id AND o.product_id = ?
                  ORDER BY o.id DESC LIMIT 1) AS payment_status,
                ua.status AS access_status, ua.is_frozen AS is_paused,
                ua.is_trial, ua.expires_at
         FROM users u
         LEFT JOIN user_access ua ON ua.id = (
             SELECT id FROM user_access
             WHERE user_id = u.id AND product_id = ?
             ORDER BY (status = 'active') DESC, id DESC LIMIT 1
         )
         $whereStr
         ORDER BY u.created_at DESC"
    );
    $stmt->execute(array_merge([$clubId, $clubId], $params));
} else {
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.last_name, u.email, u.phone, u.vk_url, u.tg_url,
                u.created_at, u.email_verified,
                s.payment_status,
                CASE WHEN s.status IN ('active','trial') THEN 'active' ELSE s.status END AS access_status,
                s.is_paused,
                CASE WHEN s.status = 'trial' THEN 1 ELSE 0 END AS is_trial,
                s.expires_at
         FROM users u
         LEFT JOIN subscriptions s ON s.id = (
             SELECT id FROM subscriptions WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
         )
         $whereStr
         ORDER BY u.created_at DESC"
    );
    $stmt->execute($params);
}
$allUsers = $stmt->fetchAll();

/** Ключ статуса доступа к клубу — та же логика, что в access_display_status(). */
function user_status_key(array $u): string {
    if (empty($u['access_status']) || $u['access_status'] !== 'active') return 'inactive';
    $expired = !empty($u['expires_at']) && strtotime($u['expires_at']) <= time();
    if ($expired && !$u['is_paused']) return 'inactive';
    if ($u['is_trial'])  return 'trial';
    if ($u['is_paused']) return 'paused';
    if (($u['payment_status'] ?? '') === 'pending') return 'pending_payment';
    return 'active';
}

$statusBadges = [
    'active'          => ['Активна',               'badge-active'],
    'trial'           => ['Пробный',               'badge-trial'],
    'paused'          => ['На паузе',              'badge-paused'],
    'pending_payment' => ['Ожидает подтверждения', 'badge-pending'],
    'inactive'        => ['Не активна',            'badge-inactive'],
];

// Значение 'pending' в селекте оставлено ради старых закладок
$filterAlias = ['pending' => 'pending_payment'];
$wantStatus  = $filterAlias[$statusFilter] ?? $statusFilter;

if ($wantStatus) {
    $allUsers = array_values(array_filter(
        $allUsers,
        fn(array $u): bool => user_status_key($u) === $wantStatus
    ));
}

$totalCount = count($allUsers);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$users      = array_slice($allUsers, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Пользователи — Администратор</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar">
    <h1>Пользователи</h1>
    <span style="font-size:13px;color:var(--muted)">Всего: <?= $totalCount ?></span>
  </div>
  <div class="admin-main">
    <form method="GET" class="toolbar">
      <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Поиск по имени или email…">
      <select name="status">
        <option value="">Все статусы</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Активна</option>
        <option value="trial" <?= $statusFilter === 'trial' ? 'selected' : '' ?>>Пробный</option>
        <option value="pending" <?= in_array($statusFilter, ['pending', 'pending_payment'], true) ? 'selected' : '' ?>>Ожидает подтверждения</option>
        <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>На паузе</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Не активна</option>
      </select>
      <button type="submit" class="btn btn-ghost">Найти</button>
      <?php if ($search || $statusFilter): ?>
      <a href="/admin/users.php" class="btn btn-ghost">Сбросить</a>
      <?php endif; ?>
    </form>

    <div class="acard" style="padding:0">
      <table class="data-table">
        <thead>
          <tr>
            <th>Имя</th>
            <th>Email</th>
            <th>Телефон</th>
            <th>Подписка</th>
            <th>До</th>
            <th>Регистрация</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            [$badgeLbl, $badgeCls] = $statusBadges[user_status_key($u)] ?? $statusBadges['inactive'];
          ?>
          <tr>
            <td>
              <?= htmlspecialchars($u['name'] . ($u['last_name'] ? ' ' . $u['last_name'] : ''), ENT_QUOTES, 'UTF-8') ?>
              <?php if (!$u['email_verified']): ?>
              <span style="font-size:11px;color:var(--warning)" title="Email не подтверждён">⚠</span>
              <?php endif; ?>
            </td>
            <td style="font-size:13px"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($u['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge <?= $badgeCls ?>"><?= $badgeLbl ?></span></td>
            <td style="font-size:13px;color:var(--muted)">
              <?= $u['expires_at'] ? date('d.m.Y', strtotime($u['expires_at'])) : '—' ?>
            </td>
            <td style="font-size:13px;color:var(--muted)"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td><a href="/admin/user-edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-ghost btn-sm">Управление</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">Пользователи не найдены</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;margin-top:16px">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&page=<?= $p ?>"
         class="btn btn-ghost btn-sm<?= $p === $page ? ' btn-primary' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
