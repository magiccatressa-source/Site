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

$statusBadgeMap = [
    'active'  => ['label' => 'Активна',               'cls' => 'badge-active'],
    'trial'   => ['label' => 'Пробный',               'cls' => 'badge-trial'],
    'paused'  => ['label' => 'На паузе',              'cls' => 'badge-paused'],
    'pending' => ['label' => 'Ожидает оплаты',        'cls' => 'badge-pending'],
    'inactive'=> ['label' => 'Не активна',            'cls' => 'badge-inactive'],
];

// Status filter requires subquery join
$havingClause = '';
if ($statusFilter) {
    // Will handle in PHP after fetch (simpler for this scale)
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->prepare("SELECT COUNT(*) FROM users u $whereStr");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = db()->prepare(
    "SELECT u.id, u.name, u.last_name, u.email, u.phone, u.vk_url, u.tg_url, u.created_at, u.email_verified,
            s.status AS sub_status, s.is_paused, s.payment_status, s.expires_at
     FROM users u
     LEFT JOIN subscriptions s ON s.user_id = u.id AND s.id = (
       SELECT id FROM subscriptions WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
     )
     $whereStr
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

function sub_badge(?string $status, bool $paused, ?string $payment): array {
    if (!$status || $status === 'inactive' || $status === 'expired') return ['Не активна', 'badge-inactive'];
    if ($status === 'trial')  return ['Пробный', 'badge-trial'];
    if ($paused)              return ['На паузе', 'badge-paused'];
    if ($payment === 'pending') return ['Ожидает подтверждения', 'badge-pending'];
    return ['Активна', 'badge-active'];
}
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
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Ожидает оплаты</option>
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
            [$badgeLbl, $badgeCls] = sub_badge(
              $u['sub_status'], (bool)$u['is_paused'], $u['payment_status']
            );
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
      <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"
         class="btn btn-ghost btn-sm<?= $p === $page ? ' btn-primary' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
