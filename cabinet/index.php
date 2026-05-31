<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_login();
$sub  = get_subscription($user['id']);
$statusKey = subscription_display_status($sub);

$statusLabels = [
    'active'          => 'Активна',
    'paused'          => 'Активна (на паузе)',
    'pending_payment' => 'Активна, ожидается оплата',
    'trial'           => 'Пробный доступ',
    'inactive'        => 'Не активна',
];
$statusBadges = [
    'active'          => 'badge-active',
    'paused'          => 'badge-paused',
    'pending_payment' => 'badge-pending',
    'trial'           => 'badge-trial',
    'inactive'        => 'badge-inactive',
];

$zoomLink   = setting('zoom_link');
$tgChat     = setting('telegram_chat_link');
$schedule   = setting('schedule_text');
$welcomeId  = setting('welcome_kinescope_id');
$welcomeTxt = setting('welcome_text');
$hasAccess   = has_active_access($sub);
$lessonCount = db()->query('SELECT COUNT(*) FROM lessons WHERE is_visible = 1')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Личный кабинет — Клуб йоги Любовь Лучистая</title>
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
    <a href="#" id="logoutBtn" style="color:var(--muted)">Выйти</a>
  </nav>
</header>

<main class="lk-main">

  <h1 style="font-size:32px; margin-bottom:20px">
    Привет, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>!
  </h1>

  <?php if ($hasAccess): ?>
  <div class="stats-row" id="statsRow" style="display:none; margin-bottom:28px">
    <div class="stat-card">
      <div class="stat-num" id="statLessons">—</div>
      <div class="stat-label">уроков пройдено</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" id="statHours">—</div>
      <div class="stat-label">часов практики</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Lesson catalog — FIRST -->
  <?php if ($hasAccess): ?>
  <div id="lessonCatalog" style="margin-bottom:40px">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px">
      <h2 style="font-size:24px">Уроки</h2>
      <a href="/cabinet/favorites.php" style="font-size:14px; color:var(--muted)">Избранное →</a>
    </div>
    <div id="topicsContainer">
      <p style="color:var(--muted); font-size:14px">Загружаю уроки…</p>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="background:var(--cream); border-color:var(--cream-deep); text-align:center; padding:40px; margin-bottom:40px">
    <p style="font-size:18px; font-family:'Instrument Serif',serif; margin-bottom:12px">
      Уроки доступны при активной подписке
    </p>
    <p style="color:var(--muted); font-size:14px; margin-bottom:20px">
      Оплатите подписку и получите доступ к архиву <?= $lessonCount > 0 ? $lessonCount . '+' : '' ?> уроков йоги
    </p>
    <a href="https://t.me/indicatrisa" target="_blank" class="btn btn-primary">
      Оформить подписку
    </a>
  </div>
  <?php endif; ?>

  <!-- Info row: Zoom + TG + Schedule -->
  <div class="info-row" style="margin-bottom:24px">
    <?php if ($zoomLink): ?>
    <div class="info-card">
      <span class="info-label">Zoom-занятие</span>
      <a href="<?= htmlspecialchars($zoomLink, ENT_QUOTES, 'UTF-8') ?>"
         target="_blank" rel="noopener"
         class="btn btn-primary btn-sm" style="align-self:flex-start">
        Открыть Zoom ↗
      </a>
    </div>
    <?php endif; ?>

    <?php if ($tgChat): ?>
    <div class="info-card">
      <span class="info-label">Чат участников</span>
      <a href="<?= htmlspecialchars($tgChat, ENT_QUOTES, 'UTF-8') ?>"
         target="_blank" rel="noopener"
         class="btn btn-outline btn-sm" style="align-self:flex-start">
        Telegram-чат ↗
      </a>
    </div>
    <?php endif; ?>

    <?php if ($schedule): ?>
    <div class="info-card" style="flex:2">
      <span class="info-label">Расписание занятий</span>
      <p style="font-size:15px; color:var(--ink-soft); line-height:1.6; margin:0">
        <?= nl2br(htmlspecialchars($schedule, ENT_QUOTES, 'UTF-8')) ?>
      </p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Subscription status -->
  <div class="card" style="margin-bottom:24px">
    <p class="card-title">Подписка</p>
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:16px">
      <span class="badge <?= $statusBadges[$statusKey] ?? 'badge-inactive' ?>">
        <?= $statusLabels[$statusKey] ?? 'Не активна' ?>
      </span>
      <?php if ($sub && $sub['expires_at'] && $hasAccess): ?>
      <span style="font-size:13px; color:var(--muted)">
        до <?= date('d.m.Y', strtotime($sub['expires_at'])) ?>
      </span>
      <?php endif; ?>
    </div>
    <a href="https://t.me/indicatrisa" target="_blank" rel="noopener"
       class="btn btn-primary btn-sm">
      Оплатить подписку ↗
    </a>
  </div>

  <?php if ($welcomeId): ?>
  <!-- Welcome video — hidden after first view -->
  <div id="welcomeBlock" class="card" style="margin-bottom:32px; display:none">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px">
      <p class="card-title" style="margin:0">Вводное видео</p>
      <button onclick="hideWelcome()" style="background:none;border:none;cursor:pointer;font-size:13px;color:var(--muted);font-family:inherit">Скрыть ✕</button>
    </div>
    <div class="player-wrap">
      <iframe
        src="https://kinescope.io/embed/<?= htmlspecialchars($welcomeId, ENT_QUOTES, 'UTF-8') ?>"
        allow="autoplay; fullscreen; picture-in-picture"
        allowfullscreen
      ></iframe>
    </div>
    <?php if ($welcomeTxt): ?>
    <p style="margin-top:16px; color:var(--ink-soft); font-size:15px; line-height:1.7">
      <?= nl2br(htmlspecialchars($welcomeTxt, ENT_QUOTES, 'UTF-8')) ?>
    </p>
    <?php endif; ?>
  </div>
  <!-- Link to re-open welcome video -->
  <p id="welcomeLink" style="display:none; font-size:13px; color:var(--muted); margin-bottom:24px">
    <a href="#" onclick="showWelcome(); return false;" style="color:var(--muted)">▶ Вводное видео</a>
  </p>
  <?php endif; ?>

</main>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

<?php if ($hasAccess): ?>
// Load stats
fetch('/api/cabinet/stats.php').then(r => r.json()).then(data => {
  if (!data.ok) return;
  document.getElementById('statLessons').textContent = data.completed_lessons;
  const h = data.hours, m = data.minutes;
  document.getElementById('statHours').textContent = h > 0
    ? h + (m >= 6 ? ',' + Math.round(m/6)*10 : '')
    : (m > 0 ? m + ' мин' : '0');
  if (data.completed_lessons > 0 || data.total_seconds > 0) {
    document.getElementById('statsRow').style.display = 'flex';
  }
});
<?php endif; ?>

// Welcome video: show only on first visit
<?php if ($welcomeId): ?>
(function() {
  const seen = localStorage.getItem('welcomeSeen');
  const block = document.getElementById('welcomeBlock');
  const link  = document.getElementById('welcomeLink');
  if (!seen) {
    block.style.display = 'block';
  } else {
    link.style.display = 'block';
  }
})();

function hideWelcome() {
  localStorage.setItem('welcomeSeen', '1');
  document.getElementById('welcomeBlock').style.display = 'none';
  document.getElementById('welcomeLink').style.display = 'block';
}

function showWelcome() {
  document.getElementById('welcomeBlock').style.display = 'block';
  document.getElementById('welcomeLink').style.display = 'none';
}
<?php endif; ?>

async function logout() {
  await fetch('/api/auth/logout.php', {
    method: 'POST',
    headers: { 'X-CSRF-Token': CSRF },
  });
  window.location.href = '/';
}
document.getElementById('logoutBtn').addEventListener('click', (e) => {
  e.preventDefault();
  logout();
});

<?php if ($hasAccess): ?>
async function loadTopics() {
  try {
    const res = await fetch('/api/cabinet/topics.php');
    const data = await res.json();
    if (!data.ok) return;
    renderTopics(data.topics);
  } catch {
    document.getElementById('topicsContainer').innerHTML =
      '<p style="color:var(--danger)">Ошибка загрузки уроков. Обновите страницу.</p>';
  }
}

function renderTopics(topics) {
  const container = document.getElementById('topicsContainer');
  if (!topics.length) {
    container.innerHTML = '<p style="color:var(--muted);font-size:14px">Уроки пока не добавлены.</p>';
    return;
  }
  container.innerHTML = topics.map(topic => `
    <div class="topic-item" data-id="${topic.id}">
      <div class="topic-header" onclick="toggleTopic(this.parentElement)">
        <div>
          <h3>${escHtml(topic.title)}</h3>
          <span class="topic-count">${topic.lessons.length} ${pluralLesson(topic.lessons.length)}${topic.is_current ? ' &nbsp;<span class="topic-current-badge">сейчас изучаем</span>' : ''}</span>
        </div>
        <span class="topic-toggle">›</span>
      </div>
      <div class="topic-lessons">
        ${topic.lessons.length
          ? topic.lessons.map(lesson => `
            <a class="lesson-item${lesson.completed ? ' completed' : ''}"
               href="/cabinet/lesson.php?id=${lesson.id}">
              <span class="lesson-status-icon">${lesson.completed ? '✓' : ''}</span>
              <span class="lesson-title">${escHtml(lesson.title)}</span>
              ${lesson.duration_min ? `<span class="lesson-duration">${lesson.duration_min} мин</span>` : ''}
              <button class="lesson-fav${lesson.is_favorite ? ' active' : ''}"
                      onclick="toggleFav(event, ${lesson.id}, this)"
                      title="${lesson.is_favorite ? 'Убрать из избранного' : 'В избранное'}">♥</button>
            </a>`).join('')
          : '<p style="padding:16px 20px;font-size:14px;color:var(--muted)">Уроков пока нет</p>'
        }
      </div>
    </div>
  `).join('');
}

function toggleTopic(el) {
  el.classList.toggle('open');
}

async function toggleFav(e, lessonId, btn) {
  e.preventDefault();
  e.stopPropagation();
  const isActive = btn.classList.contains('active');
  btn.classList.toggle('active', !isActive);
  try {
    await fetch('/api/cabinet/favorites.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lesson_id: lessonId, action: isActive ? 'remove' : 'add' }),
    });
  } catch {
    btn.classList.toggle('active', isActive); // revert on error
  }
}

function pluralLesson(n) {
  const mod10 = n % 10, mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return 'урок';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'урока';
  return 'уроков';
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadTopics();
<?php endif; ?>
</script>
</body>
</html>
