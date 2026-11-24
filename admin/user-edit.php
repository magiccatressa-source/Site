<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: /admin/users.php'); exit; }

$u = db()->prepare('SELECT * FROM users WHERE id = ? AND role = "user"');
$u->execute([$userId]);
$user = $u->fetch();
if (!$user) { header('Location: /admin/users.php'); exit; }

$clubId    = club_product_id();
$access    = get_access_row($userId, $clubId);
$payStatus = product_payment_status($userId, $clubId);
$statusKey = access_display_status($userId, $clubId);

// Остальные продукты — курсы и мини-продукты. Скрытые с витрины тоже
// показываем: доступ к ним выдавать можно, продажи просто закрыты.
$otherProducts = [];
if (access_model_ready()) {
    foreach (all_products(false) as $p) {
        if ((int)$p['id'] === $clubId) continue;
        $p['access'] = get_access_row($userId, (int)$p['id']);
        $otherProducts[] = $p;
    }
}

$logRows = db()->prepare(
    'SELECT al.created_at, al.action, al.details, u.name AS admin_name
     FROM admin_log al
     JOIN users u ON u.id = al.admin_id
     WHERE al.target_type = "user" AND al.target_id = ?
     ORDER BY al.created_at DESC LIMIT 20'
);
$logRows->execute([$userId]);
$logs = $logRows->fetchAll();

$statusLabels = [
    'active'          => 'Активна',
    'trial'           => 'Пробный доступ',
    'paused'          => 'На паузе',
    'pending_payment' => 'Ожидает подтверждения',
    'inactive'        => 'Не активна',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?> — Управление</title>
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
    <h1><a href="/admin/users.php" style="color:var(--muted);font-weight:400">Пользователи</a> / <?= htmlspecialchars($user['name'] . ($user['last_name'] ? ' '.$user['last_name'] : ''), ENT_QUOTES, 'UTF-8') ?></h1>
  </div>
  <div class="admin-main">
    <div id="pageAlert" class="alert" style="display:none"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <!-- User info -->
      <div class="acard">
        <div class="acard-title">Данные пользователя</div>
        <table style="font-size:14px;width:100%;border-collapse:collapse">
          <tr><td style="color:var(--muted);padding:4px 0;width:110px">Имя</td><td><?= htmlspecialchars($user['name'] . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><td style="color:var(--muted);padding:4px 0">Email</td><td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?><?= !$user['email_verified'] ? ' <span style="color:var(--warning);font-size:11px">⚠ не подтверждён</span>' : '' ?></td></tr>
          <tr><td style="color:var(--muted);padding:4px 0">Телефон</td><td><?= htmlspecialchars($user['phone'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><td style="color:var(--muted);padding:4px 0">ВКонтакте</td><td><?= $user['vk_url'] ? '<a href="'.htmlspecialchars($user['vk_url'], ENT_QUOTES, 'UTF-8').'" target="_blank">'.htmlspecialchars($user['vk_url'], ENT_QUOTES, 'UTF-8').'</a>' : '—' ?></td></tr>
          <tr><td style="color:var(--muted);padding:4px 0">Telegram</td><td><?= $user['tg_url'] ? '<a href="'.htmlspecialchars($user['tg_url'], ENT_QUOTES, 'UTF-8').'" target="_blank">'.htmlspecialchars($user['tg_url'], ENT_QUOTES, 'UTF-8').'</a>' : '—' ?></td></tr>
          <tr><td style="color:var(--muted);padding:4px 0">Регистрация</td><td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td></tr>
        </table>
      </div>

      <!-- Subscription status -->
      <div class="acard">
        <div class="acard-title">Клуб: <?= $statusLabels[$statusKey] ?? 'Не активна' ?></div>
        <?php if ($access): ?>
        <table style="font-size:13px;width:100%;border-collapse:collapse;margin-bottom:16px">
          <tr><td style="color:var(--muted);padding:3px 0;width:110px">Статус</td><td><?= htmlspecialchars($access['status'], ENT_QUOTES, 'UTF-8') ?><?= $access['is_trial'] ? ', пробный' : '' ?><?= $access['is_frozen'] ? ' (заморозка)' : '' ?></td></tr>
          <tr><td style="color:var(--muted);padding:3px 0">Оплата</td><td><?= $payStatus === 'pending' ? 'Ожидает подтверждения' : ($payStatus === 'paid' ? 'Оплачена' : '—') ?></td></tr>
          <tr><td style="color:var(--muted);padding:3px 0">Выдан</td><td><?= htmlspecialchars($access['source'], ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><td style="color:var(--muted);padding:3px 0">Начало</td><td><?= $access['starts_at'] ? date('d.m.Y', strtotime($access['starts_at'])) : '—' ?></td></tr>
          <tr><td style="color:var(--muted);padding:3px 0">До</td><td><?= $access['expires_at'] ? date('d.m.Y', strtotime($access['expires_at'])) : 'бессрочно' ?></td></tr>
          <?php if ($access['is_frozen'] && $access['frozen_at']): ?>
          <tr><td style="color:var(--muted);padding:3px 0">Заморозка с</td><td><?= date('d.m.Y', strtotime($access['frozen_at'])) ?></td></tr>
          <?php endif; ?>
        </table>
        <?php endif; ?>
        <?php if ($access && $access['notes']): ?>
        <p style="font-size:13px;color:var(--muted);font-style:italic;margin-bottom:12px">Заметка: <?= htmlspecialchars($access['notes'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div class="acard">
      <div class="acard-title">Управление подпиской</div>

      <!-- Activate -->
      <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--cream-deep)">
        <p style="font-weight:500;margin-bottom:10px">Активировать подписку</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Дата начала</label>
            <input type="date" id="startDate" class="form-control" value="<?= $access && $access['starts_at'] ? date('Y-m-d', strtotime($access['starts_at'])) : date('Y-m-d') ?>" style="width:auto">
          </div>
          <div class="form-group" style="margin:0">
            <label>Дата окончания</label>
            <input type="date" id="expiresDate" class="form-control" value="<?= $access && $access['expires_at'] ? date('Y-m-d', strtotime($access['expires_at'])) : date('Y-m-d', strtotime('+30 days')) ?>" style="width:auto">
          </div>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
            <input type="checkbox" id="payPending"> Рассрочка (ожидается оплата)
          </label>
          <button class="btn btn-success btn-sm" onclick="subAction('activate')">Активировать</button>
        </div>
      </div>

      <!-- Trial -->
      <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--cream-deep)">
        <p style="font-weight:500;margin-bottom:10px">Пробный доступ</p>
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
          <div class="form-group" style="margin:0">
            <label>До даты</label>
            <input type="date" id="trialExpires" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" style="width:auto">
          </div>
          <button class="btn btn-ghost btn-sm" onclick="subAction('trial')">Дать пробный доступ</button>
        </div>
      </div>

      <!-- Pause / Unpause / Deactivate -->
      <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--cream-deep)">
        <p style="font-weight:500;margin-bottom:10px">Статус</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <?php if ($access && $access['is_frozen']): ?>
          <button class="btn btn-success btn-sm" onclick="subAction('unpause')">Снять паузу</button>
          <?php else: ?>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="text" id="pauseNotes" class="form-control" placeholder="Причина паузы (необязательно)" style="width:220px">
            <button class="btn btn-ghost btn-sm" onclick="subAction('pause')">Поставить на паузу</button>
          </div>
          <?php endif; ?>
          <button class="btn btn-danger btn-sm" onclick="if(confirm('Деактивировать подписку?')) subAction('deactivate')">Деактивировать</button>
        </div>
      </div>

      <!-- Payment -->
      <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--cream-deep)">
        <p style="font-weight:500;margin-bottom:10px">Оплата</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-success btn-sm" onclick="subAction('confirm_payment')">Подтвердить оплату</button>
          <button class="btn btn-ghost btn-sm" onclick="subAction('set_pending')">Ожидается оплата</button>
        </div>
      </div>

      <!-- Update expires_at -->
      <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--cream-deep)">
        <p style="font-weight:500;margin-bottom:10px">Изменить дату окончания</p>
        <div style="display:flex;gap:10px;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Новая дата</label>
            <input type="date" id="newExpires" class="form-control"
                   value="<?= $access && $access['expires_at'] ? date('Y-m-d', strtotime($access['expires_at'])) : date('Y-m-d') ?>" style="width:auto">
          </div>
          <button class="btn btn-ghost btn-sm" onclick="subAction('update_expires')">Сохранить</button>
        </div>
      </div>

      <!-- Notes -->
      <div>
        <p style="font-weight:500;margin-bottom:10px">Заметка администратора</p>
        <div style="display:flex;gap:10px">
          <input type="text" id="adminNotes" class="form-control"
                 placeholder="Заметка…"
                 value="<?= htmlspecialchars($access['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-ghost btn-sm" onclick="subAction('set_notes')">Сохранить</button>
        </div>
      </div>
    </div>

    <!-- Доступы к другим продуктам -->
    <?php if ($otherProducts): ?>
    <div class="acard">
      <div class="acard-title">Другие продукты</div>
      <?php foreach ($otherProducts as $p):
        $pa      = $p['access'];
        $pid     = (int)$p['id'];
        $isSub   = $p['type'] === 'subscription';
        $hasNow  = $pa && $pa['status'] === 'active'
                   && ($pa['expires_at'] === null || strtotime($pa['expires_at']) > time());
        $defDate = $isSub
            ? date('Y-m-d', strtotime('+' . max(1, (int)($p['duration_days'] ?: 30)) . ' days'))
            : '';
      ?>
      <div style="padding:14px 0;border-bottom:1px solid var(--cream-deep)">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
          <div style="flex:1;min-width:200px">
            <span style="font-weight:500"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <span style="font-size:12px;color:var(--muted)">
              · <?= $isSub ? 'подписка' : 'разовая покупка' ?>
              <?php if (!$p['is_active']): ?> · скрыт с витрины<?php endif; ?>
            </span>
          </div>
          <?php if ($hasNow): ?>
          <span class="badge badge-active" style="font-size:11px">
            есть доступ<?= $pa['expires_at'] ? ' до ' . date('d.m.Y', strtotime($pa['expires_at'])) : ' бессрочно' ?>
          </span>
          <?php else: ?>
          <span class="badge badge-inactive" style="font-size:11px">нет доступа</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <div class="form-group" style="margin:0">
            <label style="font-size:12px">До даты<?= $isSub ? '' : ' (пусто = навсегда)' ?></label>
            <input type="date" class="form-control" id="pexp<?= $pid ?>"
                   value="<?= $pa && $pa['expires_at'] ? date('Y-m-d', strtotime($pa['expires_at'])) : $defDate ?>"
                   style="width:auto">
          </div>
          <button class="btn btn-success btn-sm" onclick="productAction(<?= $pid ?>, 'activate')">
            <?= $hasNow ? 'Обновить доступ' : 'Выдать доступ' ?>
          </button>
          <?php if ($hasNow): ?>
          <button class="btn btn-danger btn-sm"
                  onclick="if(confirm('Забрать доступ к продукту «<?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>»?')) productAction(<?= $pid ?>, 'deactivate')">
            Забрать
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <p style="font-size:12px;color:var(--muted);margin-top:12px">
        Продукты создаются в разделе <a href="/admin/products.php">«Продукты»</a>.
        Разовый курс обычно выдают без даты окончания — тогда он остаётся у человека навсегда.
      </p>
    </div>
    <?php endif; ?>

    <!-- Audit log for this user -->
    <?php if ($logs): ?>
    <div class="acard">
      <div class="acard-title">История изменений</div>
      <table class="data-table">
        <thead>
          <tr><th>Дата</th><th>Действие</th><th>Кто</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td style="white-space:nowrap;color:var(--muted)"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
            <td><?= htmlspecialchars($l['action'], ENT_QUOTES, 'UTF-8') ?>
              <?php if ($l['details']): ?>
              <span style="color:var(--muted);font-size:12px"> — <?= htmlspecialchars($l['details'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($l['admin_name'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
const USER_ID = <?= (int)$userId ?>;
const CSRF = <?= json_encode(csrf_token()) ?>;

function showAlert(msg, type = 'success') {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  setTimeout(() => el.style.display = 'none', 4000);
}

async function subAction(action) {
  const payload = { user_id: USER_ID, action };

  if (action === 'activate') {
    payload.started_at    = document.getElementById('startDate').value;
    payload.expires_at    = document.getElementById('expiresDate').value;
    payload.payment_pending = document.getElementById('payPending').checked;
  }
  if (action === 'trial') {
    payload.expires_at = document.getElementById('trialExpires').value;
  }
  if (action === 'pause') {
    payload.pause_notes = document.getElementById('pauseNotes').value.trim();
  }
  if (action === 'update_expires') {
    payload.expires_at = document.getElementById('newExpires').value;
  }
  if (action === 'set_notes') {
    payload.notes = document.getElementById('adminNotes').value.trim();
  }

  await send(payload);
}

// Доступ к курсу или мини-продукту. Пустая дата = бессрочно.
async function productAction(productId, action) {
  const payload = { user_id: USER_ID, action, product_id: productId };
  if (action === 'activate') {
    payload.started_at      = new Date().toISOString().slice(0, 10);
    payload.expires_at      = document.getElementById('pexp' + productId).value || '';
    payload.payment_pending = false;
  }
  await send(payload);
}

async function send(payload) {
  try {
    const res = await fetch('/api/admin/subscription.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.ok) {
      showAlert('Сохранено.');
      setTimeout(() => location.reload(), 1000);
    } else {
      showAlert(data.error || 'Ошибка.', 'error');
    }
  } catch {
    showAlert('Ошибка соединения.', 'error');
  }
}
</script>
</body>
</html>
