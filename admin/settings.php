<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройки — Администратор</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar"><h1>Настройки сайта</h1></div>
  <div class="admin-main">
    <div id="alert" class="alert" style="display:none"></div>

    <!-- Site settings form -->
    <div class="acard">
      <div class="acard-title">Ссылки и расписание</div>
      <form id="settingsForm">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Ссылка на Zoom-занятие</label>
          <input type="url" class="form-control" name="zoom_link" id="zoom_link" placeholder="https://us02web.zoom.us/j/...">
        </div>
        <div class="form-group">
          <label>Ссылка на Telegram-чат</label>
          <input type="url" class="form-control" name="telegram_chat_link" id="telegram_chat_link" placeholder="https://t.me/...">
        </div>
        <div class="form-group">
          <label>Расписание занятий</label>
          <textarea class="form-control" name="schedule_text" id="schedule_text" rows="3"></textarea>
          <p style="font-size:12px;color:var(--muted);margin-top:4px">Текст показывается в личном кабинете пользователя</p>
        </div>
        <button type="submit" class="btn btn-primary" id="saveSettingsBtn">Сохранить</button>
      </form>
    </div>

    <!-- Welcome video -->
    <div class="acard">
      <div class="acard-title">Приветственное видео</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Показывается всем пользователям в личном кабинете (с подпиской и без)</p>
      <form id="welcomeForm">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Kinescope ID видео</label>
          <input type="text" class="form-control" name="welcome_kinescope_id" id="welcome_kinescope_id" placeholder="UUID видео из Кинескопа">
          <p style="font-size:12px;color:var(--muted);margin-top:4px">Только UUID, например: <code>abc123-def456</code> — не полная ссылка</p>
        </div>
        <div class="form-group">
          <label>Пароль для просмотра видео (Kinescope)</label>
          <input type="text" class="form-control" name="kinescope_password" id="kinescope_password" placeholder="Пароль от проекта в Кинескопе">
          <p style="font-size:12px;color:var(--muted);margin-top:4px">Показывается пользователям с кнопкой «Скопировать» на странице каждого урока</p>
        </div>
        <div class="form-group">
          <label>Текст под приветственным видео</label>
          <textarea class="form-control" name="welcome_text" id="welcome_text" rows="4"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" id="saveWelcomeBtn">Сохранить</button>
      </form>
    </div>

    <!-- Trial lesson -->
    <div class="acard">
      <div class="acard-title">Пробный урок (страница /trial)</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Этот урок доступен по ссылке <strong>luchistaya-yoga.ru/trial</strong> без регистрации — можно давать в Instagram и рассылках</p>
      <form id="trialForm">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Урок</label>
          <select class="form-control" name="trial_lesson_id" id="trial_lesson_id">
            <option value="">— не выбран —</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" id="saveTrialBtn">Сохранить</button>
      </form>
    </div>

    <!-- Pause requests -->
    <div class="acard" id="pauseRequestsCard">
      <div class="acard-title">Заявки на заморозку</div>
      <div id="pauseList"><p style="font-size:13px;color:var(--muted)">Загрузка…</p></div>
    </div>

    <!-- Bulk extend -->
    <div class="acard" style="border-color:var(--marsala)">
      <div class="acard-title">Массовое продление подписок</div>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Продлевает дату окончания для всех активных подписчиков. Используйте при переносах занятий, праздниках или перерывах.
      </p>
      <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
          <label>Количество дней</label>
          <input type="number" id="bulkDays" class="form-control" min="1" max="365" value="7" style="width:100px">
        </div>
        <button class="btn btn-primary btn-sm" id="bulkExtendBtn">Продлить всем</button>
      </div>
      <div id="bulkResult" style="margin-top:12px;font-size:13px;display:none"></div>
    </div>

  </div>
</div>
</div>
<script>
const CSRF = document.querySelector('[name=csrf_token]')?.value ?? '';

// Load pause requests
fetch('/api/admin/pause-requests.php').then(r => r.json()).then(data => {
  const el = document.getElementById('pauseList');
  if (!data.ok || !data.requests.length) {
    el.innerHTML = '<p style="font-size:13px;color:var(--muted)">Заявок пока нет</p>';
    return;
  }
  const statusLabel = { pending: '⏳ Ожидает', approved: '✓ Одобрена', rejected: '✗ Отклонена' };
  const statusColor = { pending: 'var(--warning)', approved: 'var(--success)', rejected: 'var(--danger)' };
  el.innerHTML = data.requests.map(r => `
    <div style="padding:14px 0;border-bottom:1px solid var(--cream-deep)">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:6px">
        <strong style="font-size:14px">${r.name} ${r.last_name || ''}</strong>
        <span style="font-size:12px;color:var(--muted)">${r.email}</span>
        <span style="font-size:12px;color:${statusColor[r.status]}">${statusLabel[r.status]}</span>
        <span style="font-size:12px;color:var(--muted)">Запрос: ${r.days} дн.</span>
        ${r.expires_at ? `<span style="font-size:12px;color:var(--muted)">Подписка до: ${r.expires_at}</span>` : ''}
      </div>
      ${r.reason ? `<p style="font-size:13px;color:var(--ink-soft);margin-bottom:8px">«${r.reason}»</p>` : ''}
      ${r.status === 'pending' ? `
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <label style="font-size:12px;color:var(--muted)">Дней:</label>
          <input type="number" id="days_${r.id}" value="${r.days}" min="1" max="90" style="width:60px;padding:4px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px">
          <button class="btn btn-primary btn-sm" onclick="reviewPause(${r.id},'approve')">Одобрить</button>
          <button class="btn btn-ghost btn-sm" onclick="reviewPause(${r.id},'reject')">Отклонить</button>
        </div>
      ` : `<p style="font-size:12px;color:var(--muted)">${r.reviewed_at ? 'Рассмотрено: ' + r.reviewed_at : ''}</p>`}
    </div>
  `).join('');
});

async function reviewPause(id, action) {
  const days = parseInt(document.getElementById('days_' + id)?.value);
  const res = await fetch('/api/admin/pause-requests.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ id, action, days }),
  });
  const data = await res.json();
  if (data.ok) location.reload();
  else showAlert('Ошибка: ' + (data.error || 'неизвестная'), 'error');
}

// Load settings + lessons list in parallel
Promise.all([
  fetch('/api/admin/settings.php').then(r => r.json()),
  fetch('/api/admin/lessons-list.php').then(r => r.json()),
]).then(([settings, lessonsData]) => {
  if (settings.ok) {
    ['zoom_link','telegram_chat_link','schedule_text','welcome_kinescope_id','kinescope_password','welcome_text'].forEach(k => {
      const el = document.getElementById(k);
      if (el) el.value = settings[k] || '';
    });
  }
  const sel = document.getElementById('trial_lesson_id');
  if (lessonsData.ok) {
    lessonsData.lessons.forEach(l => {
      const opt = document.createElement('option');
      opt.value = l.id;
      opt.textContent = l.title + (l.topic_title ? ' [' + l.topic_title + ']' : '');
      sel.appendChild(opt);
    });
  }
  if (settings.ok && settings.trial_lesson_id) sel.value = settings.trial_lesson_id;
});

function showAlert(msg, type = 'success') {
  const el = document.getElementById('alert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 4000);
}

// Save main settings
document.getElementById('settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('saveSettingsBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/admin/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({
        zoom_link:          document.getElementById('zoom_link').value.trim(),
        telegram_chat_link: document.getElementById('telegram_chat_link').value.trim(),
        schedule_text:      document.getElementById('schedule_text').value,
      }),
    });
    const data = await res.json();
    showAlert(data.ok ? 'Настройки сохранены.' : 'Ошибка.', data.ok ? 'success' : 'error');
  } catch { showAlert('Ошибка соединения.','error'); }
  finally { btn.disabled = false; }
});

// Save welcome settings
document.getElementById('welcomeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('saveWelcomeBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/admin/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({
        welcome_kinescope_id: document.getElementById('welcome_kinescope_id').value.trim(),
        kinescope_password:   document.getElementById('kinescope_password').value.trim(),
        welcome_text:         document.getElementById('welcome_text').value,
      }),
    });
    const data = await res.json();
    showAlert(data.ok ? 'Приветственное видео сохранено.' : 'Ошибка.', data.ok ? 'success' : 'error');
  } catch { showAlert('Ошибка соединения.','error'); }
  finally { btn.disabled = false; }
});

// Save trial lesson
document.getElementById('trialForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('saveTrialBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/admin/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ trial_lesson_id: document.getElementById('trial_lesson_id').value }),
    });
    const data = await res.json();
    showAlert(data.ok ? 'Пробный урок сохранён.' : 'Ошибка.', data.ok ? 'success' : 'error');
  } catch { showAlert('Ошибка соединения.','error'); }
  finally { btn.disabled = false; }
});

// Bulk extend
document.getElementById('bulkExtendBtn').addEventListener('click', async () => {
  const days = parseInt(document.getElementById('bulkDays').value);
  if (!days || days < 1) return;
  if (!confirm(`Продлить подписки ВСЕХ активных пользователей на ${days} дней?`)) return;

  const btn = document.getElementById('bulkExtendBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/api/admin/bulk-extend.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ days }),
    });
    const data = await res.json();
    const resultEl = document.getElementById('bulkResult');
    if (data.ok) {
      resultEl.style.color = 'var(--success)';
      resultEl.textContent = `✓ Продлено ${data.affected} подписок на ${days} дней.`;
    } else {
      resultEl.style.color = 'var(--danger)';
      resultEl.textContent = 'Ошибка: ' + (data.error || 'неизвестная');
    }
    resultEl.style.display = 'block';
  } catch {
    showAlert('Ошибка соединения.', 'error');
  } finally { btn.disabled = false; }
});
</script>
</body>
</html>
