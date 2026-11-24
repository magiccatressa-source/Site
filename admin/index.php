<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();

// Stats
$totalUsers    = db()->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn();
$productStats = [];
if (access_model_ready()) {
    $activeUsers = db()->query(
        "SELECT COUNT(DISTINCT user_id) FROM user_access
         WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())"
    )->fetchColumn();

    $pendingUsers = db()->query(
        "SELECT COUNT(DISTINCT ua.user_id) FROM user_access ua
         WHERE ua.status = 'active' AND (ua.expires_at IS NULL OR ua.expires_at > NOW())
           AND (SELECT o.status FROM orders o
                 WHERE o.user_id = ua.user_id AND o.product_id = ua.product_id
                 ORDER BY o.id DESC LIMIT 1) = 'pending'"
    )->fetchColumn();

    // Активные доступы в разрезе продуктов
    $productStats = db()->query(
        "SELECT p.title, p.slug, p.is_active,
                COUNT(ua.id) AS active_count
         FROM products p
         LEFT JOIN user_access ua ON ua.product_id = p.id AND ua.status = 'active'
                                 AND (ua.expires_at IS NULL OR ua.expires_at > NOW())
         GROUP BY p.id, p.title, p.slug, p.is_active, p.sort_order
         ORDER BY p.sort_order, p.id"
    )->fetchAll();
} else {
    $activeUsers  = db()->query('SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status IN ("active","trial")')->fetchColumn();
    $pendingUsers = db()->query('SELECT COUNT(*) FROM subscriptions WHERE payment_status = "pending" AND status = "active"')->fetchColumn();
}
$recentUsers   = db()->query('SELECT COUNT(*) FROM users WHERE role = "user" AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();

// Recent audit log
$logRows = db()->query(
    'SELECT al.created_at, al.action, al.target_type, al.target_id, u.name AS admin_name,
            t.name AS target_name
     FROM admin_log al
     JOIN users u ON u.id = al.admin_id
     LEFT JOIN users t ON t.id = al.target_id AND al.target_type = "user"
     ORDER BY al.created_at DESC LIMIT 10'
)->fetchAll();

$actionLabels = [
    'admin.login'          => 'Вход в систему',
    'subscription.activate'=> 'Подписка активирована',
    'subscription.deactivate'=>'Подписка деактивирована',
    'subscription.pause'   => 'Подписка поставлена на паузу',
    'subscription.unpause' => 'Пауза снята',
    'subscription.trial'   => 'Пробный доступ выдан',
    'subscription.pending' => 'Рассрочка выставлена',
    'subscription.paid'    => 'Оплата подтверждена',
    'subscription.bulk_extend' => 'Массовое продление',
    'access.grant'         => 'Доступ выдан',
    'access.revoke'        => 'Доступ отозван',
    'access.extend'        => 'Доступ продлён',
    'access.trial'         => 'Пробный доступ выдан',
    'access.freeze'        => 'Доступ заморожен',
    'access.unfreeze'      => 'Заморозка снята',
    'access.update_expires'=> 'Дата окончания изменена',
    'access.bulk_extend'   => 'Массовое продление',
    'access.payment_confirmed' => 'Оплата подтверждена',
    'access.payment_pending'   => 'Оплата ожидает подтверждения',
    'product.create'       => 'Продукт создан',
    'product.update'       => 'Продукт обновлён',
    'product.delete'       => 'Продукт удалён',
    'pause.approve'        => 'Заморозка одобрена',
    'pause.reject'         => 'Заморозка отклонена',
    'settings.update'      => 'Настройки обновлены',
    'lesson.create'        => 'Урок добавлен',
    'lesson.update'        => 'Урок обновлён',
    'lesson.delete'        => 'Урок удалён',
    'topic.create'         => 'Тема добавлена',
    'topic.update'         => 'Тема обновлена',
    'topic.delete'         => 'Тема удалена',
];

function page_title(string $t): string { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Дашборд — Администратор</title>
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
    <h1>Дашборд</h1>
    <span style="font-size:13px;color:var(--muted)"><?= page_title($admin['name']) ?></span>
  </div>
  <div class="admin-main">

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-num"><?= (int)$totalUsers ?></div>
        <div class="stat-lbl">Всего пользователей</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= (int)$activeUsers ?></div>
        <div class="stat-lbl">Активных подписок</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= (int)$pendingUsers ?></div>
        <div class="stat-lbl">Ожидают оплаты</div>
      </div>
      <div class="stat-card">
        <div class="stat-num">+<?= (int)$recentUsers ?></div>
        <div class="stat-lbl">Новых за 7 дней</div>
      </div>
    </div>

    <?php if ($productStats): ?>
    <div class="acard">
      <div class="acard-title">Активные доступы по продуктам</div>
      <table class="data-table">
        <thead><tr><th>Продукт</th><th>Активных доступов</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($productStats as $ps): ?>
          <tr>
            <td><?= page_title($ps['title']) ?>
              <span style="color:var(--muted);font-size:12px"><?= page_title($ps['slug']) ?></span>
            </td>
            <td><?= (int)$ps['active_count'] ?></td>
            <td style="font-size:12px;color:var(--muted)">
              <?= $ps['is_active'] ? '' : 'скрыт на витрине' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="acard">
      <div class="acard-title">Последние действия</div>
      <?php if ($logRows): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Дата</th>
            <th>Действие</th>
            <th>Пользователь</th>
            <th>Кто</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logRows as $r): ?>
          <tr>
            <td style="white-space:nowrap;color:var(--muted)"><?= date('d.m H:i', strtotime($r['created_at'])) ?></td>
            <td><?= page_title($actionLabels[$r['action']] ?? $r['action']) ?></td>
            <td>
              <?php if ($r['target_name']): ?>
              <a href="/admin/user-edit.php?id=<?= (int)$r['target_id'] ?>"><?= page_title($r['target_name']) ?></a>
              <?php else: echo '—'; endif; ?>
            </td>
            <td><?= page_title($r['admin_name']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="margin-top:12px"><a href="/admin/audit-log.php">Весь журнал →</a></p>
      <?php else: ?>
      <p style="color:var(--muted)">Действий пока нет.</p>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>
</body>
</html>
