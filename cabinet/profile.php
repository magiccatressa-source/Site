<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_login();
$sub = get_subscription($user['id']);

$statusLabels = [
    'active'          => 'Активна',
    'paused'          => 'Активна (на паузе)',
    'pending_payment' => 'Активна, ожидается оплата',
    'trial'           => 'Пробный доступ',
    'inactive'        => 'Не активна',
];
$subStatus = subscription_display_status($sub);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Профиль — Клуб йоги</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>
<header class="lk-header">
  <a href="/" class="logo">Йога с Любовью</a>
  <nav>
    <a href="/cabinet/">Кабинет</a>
    <a href="/cabinet/favorites.php">Избранное</a>
    <a href="/cabinet/profile.php">Профиль</a>
  </nav>
</header>
<main class="lk-main">
  <h1 style="font-size:28px; margin-bottom:24px">Профиль</h1>

  <!-- Stats -->
  <div class="stats-row" id="statsRow" style="display:none">
    <div class="stat-card">
      <div class="stat-num" id="statLessons">—</div>
      <div class="stat-label">уроков пройдено</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" id="statHours">—</div>
      <div class="stat-label">часов практики</div>
    </div>
  </div>

  <div id="alertProfile" class="alert" style="display:none"></div>

  <!-- Profile data -->
  <div class="card" style="margin-bottom:24px">
    <p class="card-title">Личные данные</p>
    <form id="profileForm">
      <?= csrf_field() ?>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
        <div class="form-group" style="margin-bottom:0">
          <label>Имя *</label>
          <input class="form-control" type="text" name="name" required maxlength="100"
                 value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Фамилия</label>
          <input class="form-control" type="text" name="last_name" maxlength="100"
                 value="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:16px">
        <label>Email</label>
        <input class="form-control" type="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" disabled style="opacity:.6; cursor:default">
      </div>
      <div class="form-group">
        <label>Телефон</label>
        <input class="form-control" type="tel" name="phone"
               value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px">
        <div class="form-group">
          <label>ВКонтакте</label>
          <div style="display:flex; align-items:center">
            <span style="background:var(--cream-deep);border:1px solid #ccc;border-right:none;padding:8px 10px;border-radius:6px 0 0 6px;font-size:14px;color:var(--muted);white-space:nowrap">vk.com/</span>
            <input class="form-control" type="text" name="vk_nick" placeholder="ваш_ник" style="border-radius:0 6px 6px 0"
                   value="<?= htmlspecialchars(ltrim(str_replace('https://vk.com/', '', $user['vk_url'] ?? ''), '/'), ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Telegram</label>
          <div style="display:flex; align-items:center">
            <span style="background:var(--cream-deep);border:1px solid #ccc;border-right:none;padding:8px 10px;border-radius:6px 0 0 6px;font-size:14px;color:var(--muted);white-space:nowrap">t.me/</span>
            <input class="form-control" type="text" name="tg_nick" placeholder="ваш_ник" style="border-radius:0 6px 6px 0"
                   value="<?= htmlspecialchars(ltrim(str_replace('https://t.me/', '', $user['tg_url'] ?? ''), '/'), ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" id="saveProfileBtn">Сохранить</button>
    </form>
  </div>

  <!-- Change password -->
  <div class="card" style="margin-bottom:24px">
    <p class="card-title">Изменить пароль</p>
    <div id="alertPwd" class="alert" style="display:none"></div>
    <form id="pwdForm">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Текущий пароль</label>
        <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label>Новый пароль</label>
        <input class="form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <p class="form-hint">Минимум 8 символов</p>
      </div>
      <div class="form-group">
        <label>Повторите новый пароль</label>
        <input class="form-control" type="password" name="new_password2" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-outline btn-sm" id="savePwdBtn">Изменить пароль</button>
    </form>
  </div>

  <!-- Subscription history -->
  <div class="card" style="margin-bottom:24px">
    <p class="card-title">Подписка</p>
    <?php if ($sub): ?>
    <p style="font-size:15px">
      Статус: <strong><?= $statusLabels[$subStatus] ?? 'Не активна' ?></strong>
      <?php if ($sub['expires_at']): ?>
      · до <?= date('d.m.Y', strtotime($sub['expires_at'])) ?>
      <?php endif; ?>
    </p>
    <?php else: ?>
    <p style="color:var(--muted); font-size:14px">Подписка не оформлена.</p>
    <?php endif; ?>
    <div style="margin-top:12px">
      <a href="https://t.me/indicatrisa" target="_blank" class="btn btn-outline btn-sm">
        Оплатить / продлить подписку ↗
      </a>
    </div>
  </div>

  <!-- Delete account -->
  <div class="card" style="border-color:var(--danger-bg)">
    <p class="card-title" style="color:var(--danger)">Удаление аккаунта</p>
    <p style="font-size:14px; color:var(--muted); margin-bottom:16px">
      Удаление необратимо. Все ваши данные и прогресс будут удалены в соответствии с ФЗ-152.
    </p>
    <button class="btn btn-sm" style="background:transparent;border:1px solid var(--danger);color:var(--danger)"
            onclick="confirmDelete()">Удалить аккаунт</button>
  </div>
</main>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

// Load stats
fetch('/api/cabinet/stats.php').then(r => r.json()).then(data => {
  if (!data.ok) return;
  document.getElementById('statLessons').textContent = data.completed_lessons;
  const h = data.hours, m = data.minutes;
  document.getElementById('statHours').textContent = h > 0 ? h + (m > 0 ? ',' + Math.round(m/6)*10 : '') : (m + ' мин');
  document.getElementById('statsRow').style.display = 'flex';
});

// Save profile
const profileForm = document.getElementById('profileForm');
const alertProfile = document.getElementById('alertProfile');
profileForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertProfile.style.display = 'none';
  const btn = document.getElementById('saveProfileBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/cabinet/profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({
        name:        profileForm.querySelector('[name=name]').value.trim(),
        last_name:   profileForm.querySelector('[name=last_name]').value.trim(),
        phone:       profileForm.querySelector('[name=phone]').value.trim(),
        vk_nick: profileForm.querySelector('[name=vk_nick]').value.trim(),
        tg_nick: profileForm.querySelector('[name=tg_nick]').value.trim(),
      }),
    });
    const data = await res.json();
    alertProfile.className = data.ok ? 'alert alert-success' : 'alert alert-error';
    alertProfile.textContent = data.ok ? 'Данные сохранены.' : 'Ошибка сохранения.';
    alertProfile.style.display = 'block';
  } catch {
    alertProfile.className = 'alert alert-error';
    alertProfile.textContent = 'Ошибка соединения.';
    alertProfile.style.display = 'block';
  } finally { btn.disabled = false; }
});

// Change password
const pwdForm = document.getElementById('pwdForm');
const alertPwd = document.getElementById('alertPwd');
pwdForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertPwd.style.display = 'none';
  const np = pwdForm.querySelector('[name=new_password]').value;
  const np2 = pwdForm.querySelector('[name=new_password2]').value;
  if (np !== np2) {
    alertPwd.className = 'alert alert-error';
    alertPwd.textContent = 'Новые пароли не совпадают.';
    alertPwd.style.display = 'block';
    return;
  }
  const btn = document.getElementById('savePwdBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/cabinet/profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({
        current_password: pwdForm.querySelector('[name=current_password]').value,
        new_password: np,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      alertPwd.className = 'alert alert-success';
      alertPwd.textContent = 'Пароль изменён.';
      pwdForm.reset();
    } else {
      alertPwd.className = 'alert alert-error';
      alertPwd.textContent = data.error === 'wrong_current_password'
        ? 'Текущий пароль неверный.'
        : 'Ошибка изменения пароля.';
    }
    alertPwd.style.display = 'block';
  } catch {
    alertPwd.className = 'alert alert-error';
    alertPwd.textContent = 'Ошибка соединения.';
    alertPwd.style.display = 'block';
  } finally { btn.disabled = false; }
});

async function confirmDelete() {
  if (!confirm('Удалить аккаунт навсегда? Это действие необратимо.')) return;
  if (!confirm('Вы уверены? Все данные будут удалены.')) return;
  try {
    await fetch('/api/cabinet/delete-account.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF },
    });
    window.location.href = '/';
  } catch {
    alert('Ошибка. Попробуйте позже.');
  }
}
</script>
</body>
</html>
