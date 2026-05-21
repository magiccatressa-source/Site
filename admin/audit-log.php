<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$total = db()->query('SELECT COUNT(*) FROM admin_log')->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$rows = db()->prepare(
    'SELECT al.*, u.name AS admin_name, t.name AS target_name, t.email AS target_email
     FROM admin_log al
     JOIN users u ON u.id = al.admin_id
     LEFT JOIN users t ON t.id = al.target_id AND al.target_type = "user"
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?'
);
$rows->execute([$perPage, $offset]);
$logs = $rows->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Журнал действий — Администратор</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar">
    <h1>Журнал действий</h1>
    <span style="font-size:13px;color:var(--muted)">Всего: <?= (int)$total ?></span>
  </div>
  <div class="admin-main">
    <div class="acard" style="padding:0">
      <table class="data-table">
        <thead>
          <tr>
            <th>Дата</th>
            <th>Действие</th>
            <th>Пользователь</th>
            <th>Детали</th>
            <th>Администратор</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $r): ?>
          <tr>
            <td style="white-space:nowrap;font-size:13px;color:var(--muted)"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
            <td style="font-size:13px"><?= htmlspecialchars($r['action'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="font-size:13px">
              <?php if ($r['target_name']): ?>
              <a href="/admin/user-edit.php?id=<?= (int)$r['target_id'] ?>">
                <?= htmlspecialchars($r['target_name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
              <?php else: echo '—'; endif; ?>
            </td>
            <td style="font-size:12px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= $r['details'] ? htmlspecialchars($r['details'], ENT_QUOTES, 'UTF-8') : '—' ?>
            </td>
            <td style="font-size:13px"><?= htmlspecialchars($r['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['ip'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$logs): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">Записей нет</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:8px;margin-top:16px">
      <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
      <a href="?page=<?= $p ?>" class="btn btn-ghost btn-sm<?= $p === $page ? ' btn-primary' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>
