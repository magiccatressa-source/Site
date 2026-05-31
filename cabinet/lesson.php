<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_login();

$sub = get_subscription($user['id']);
if (!has_active_access($sub)) {
    header('Location: /cabinet/');
    exit;
}

$kinescopePassword = setting('kinescope_password');
$lessonId = (int)($_GET['id'] ?? 0);
if (!$lessonId) {
    header('Location: /cabinet/');
    exit;
}

$s = db()->prepare(
    'SELECT l.id, l.title, l.description, l.kinescope_id, l.duration_min, t.title AS topic_title
     FROM lessons l
     JOIN topics t ON t.id = l.topic_id
     WHERE l.id = ? AND l.is_visible = 1'
);
$s->execute([$lessonId]);
$lesson = $s->fetch();

if (!$lesson) {
    header('Location: /cabinet/');
    exit;
}

// Progress
$progress = db()->prepare('SELECT completed FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
$progress->execute([$user['id'], $lessonId]);
$prog = $progress->fetch();
$isCompleted = $prog && $prog['completed'];

// Favorite
$favQ = db()->prepare('SELECT id FROM lesson_favorites WHERE user_id = ? AND lesson_id = ?');
$favQ->execute([$user['id'], $lessonId]);
$isFavorite = (bool)$favQ->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?> — Клуб йоги</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>

<header class="lk-header">
  <a href="/" class="logo">Йога с Любовью</a>
  <nav>
    <a href="/cabinet/">← Все уроки</a>
    <a href="/cabinet/favorites.php">Избранное</a>
    <a href="/cabinet/profile.php">Профиль</a>
  </nav>
</header>

<main class="lk-main">
  <p style="font-size:13px; color:var(--muted); margin-bottom:8px">
    <a href="/cabinet/" style="color:var(--muted)">Уроки</a>
    › <?= htmlspecialchars($lesson['topic_title'], ENT_QUOTES, 'UTF-8') ?>
  </p>

  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px">
    <h1 style="font-size:28px; line-height:1.2">
      <?= htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <div style="display:flex; gap:12px; align-items:center; flex-shrink:0">
      <?php if ($lesson['duration_min']): ?>
      <span style="font-size:14px; color:var(--muted)"><?= (int)$lesson['duration_min'] ?> мин</span>
      <?php endif; ?>
      <?php if ($isCompleted): ?>
      <span class="badge badge-active" style="font-size:12px">✓ Просмотрено</span>
      <?php endif; ?>
      <button id="favBtn"
              class="btn btn-outline btn-sm<?= $isFavorite ? ' active' : '' ?>"
              style="<?= $isFavorite ? 'background:var(--marsala);color:var(--cream)' : '' ?>">
        <?= $isFavorite ? '♥ В избранном' : '♡ В избранное' ?>
      </button>
    </div>
  </div>

  <?php if ($kinescopePassword): ?>
  <div style="display:inline-flex;align-items:center;gap:10px;background:var(--cream-deep);border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:14px">
    <span style="color:var(--muted)">Пароль для видео:</span>
    <span id="kinescopePass" style="font-weight:500;letter-spacing:0.03em"><?= htmlspecialchars($kinescopePassword, ENT_QUOTES, 'UTF-8') ?></span>
    <button onclick="copyPass()" id="copyPassBtn" style="background:none;border:none;cursor:pointer;font-size:13px;color:var(--marsala);padding:0;font-family:inherit">Скопировать</button>
  </div>
  <?php endif; ?>

  <!-- Kinescope Player -->
  <div class="player-wrap" style="margin-bottom:32px">
    <iframe
      id="kinescopePlayer"
      src="https://kinescope.io/embed/<?= htmlspecialchars($lesson['kinescope_id'], ENT_QUOTES, 'UTF-8') ?>"
      allow="autoplay; fullscreen; picture-in-picture"
      allowfullscreen
    ></iframe>
  </div>

  <!-- Done button -->
  <div style="text-align:center; margin-bottom:32px">
    <button id="doneBtn" class="btn-done<?= $isCompleted ? ' done' : '' ?>"
            onclick="toggleDone()">
      <?= $isCompleted ? '✓ Сделано!' : 'Я позанималась / позанимался!' ?>
    </button>
  </div>

  <?php if ($lesson['description']): ?>
  <div class="card">
    <p class="card-title">Описание урока</p>
    <div style="font-size:16px; line-height:1.8; color:var(--ink-soft)">
      <?= nl2br(htmlspecialchars($lesson['description'], ENT_QUOTES, 'UTF-8')) ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="margin-top:32px">
    <a href="/cabinet/" class="btn btn-ghost">← Все уроки</a>
  </div>

  <!-- Confetti canvas -->
  <canvas id="confettiCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:999;display:none"></canvas>
</main>

<script>
const LESSON_ID = <?= (int)$lessonId ?>;
const CSRF      = <?= json_encode(csrf_token()) ?>;
let   progressSent = false;

// Favorite toggle
const favBtn = document.getElementById('favBtn');
let isFav = <?= $isFavorite ? 'true' : 'false' ?>;

favBtn.addEventListener('click', async () => {
  isFav = !isFav;
  favBtn.textContent = isFav ? '♥ В избранном' : '♡ В избранное';
  favBtn.style.background = isFav ? 'var(--marsala)' : '';
  favBtn.style.color = isFav ? 'var(--cream)' : '';
  try {
    await fetch('/api/cabinet/favorites.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lesson_id: LESSON_ID, action: isFav ? 'add' : 'remove' }),
    });
  } catch {}
});

// Kinescope Player API for progress tracking
window.addEventListener('message', (event) => {
  if (!event.data || typeof event.data !== 'object') return;
  const { type, payload } = event.data;

  if (type === 'kinescopeTimeUpdate' && payload && !progressSent) {
    const { currentTime, duration } = payload;
    if (duration > 0 && currentTime / duration >= 0.8) {
      progressSent = true;
      sendProgress(currentTime, true);
    }
  }
  if (type === 'kinescopeEnded') {
    progressSent = true;
    sendProgress(0, true);
  }
});

async function sendProgress(watchSeconds, completed) {
  try {
    await fetch('/api/cabinet/progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lesson_id: LESSON_ID, watch_seconds: Math.round(watchSeconds), completed }),
    });
    if (completed) {
      document.title = '✓ ' + document.title;
    }
  } catch {}
}

// Done button
let isDone = <?= $isCompleted ? 'true' : 'false' ?>;

async function toggleDone() {
  isDone = !isDone;
  const btn = document.getElementById('doneBtn');
  btn.classList.toggle('done', isDone);
  btn.textContent = isDone ? '✓ Сделано!' : 'Я позанималась / позанимался!';
  if (isDone) launchConfetti();
  try {
    await fetch('/api/cabinet/progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lesson_id: LESSON_ID, watch_seconds: 0, completed: isDone }),
    });
  } catch {}
}

// Confetti
function launchConfetti() {
  const canvas = document.getElementById('confettiCanvas');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  canvas.style.display = 'block';

  const colors = ['#8b3a4a','#c4956a','#d4b896','#7aab7a','#e8d5c4','#f0c040'];
  const pieces = Array.from({length: 120}, () => ({
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height - canvas.height,
    r: Math.random() * 6 + 3,
    d: Math.random() * 120 + 80,
    color: colors[Math.floor(Math.random() * colors.length)],
    tilt: Math.random() * 10 - 10,
    tiltAngle: 0,
    tiltSpeed: Math.random() * 0.07 + 0.05,
  }));

  let angle = 0, tick = 0;
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    angle += 0.01;
    tick++;
    pieces.forEach(p => {
      p.tiltAngle += p.tiltSpeed;
      p.y += (Math.cos(angle + p.d) + 2) * 1.8;
      p.x += Math.sin(angle) * 1.2;
      p.tilt = Math.sin(p.tiltAngle) * 12;
      ctx.beginPath();
      ctx.lineWidth = p.r;
      ctx.strokeStyle = p.color;
      ctx.moveTo(p.x + p.tilt + p.r / 2, p.y);
      ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 2);
      ctx.stroke();
    });
    if (tick < 200) requestAnimationFrame(draw);
    else { ctx.clearRect(0, 0, canvas.width, canvas.height); canvas.style.display = 'none'; }
  }
  draw();
}

function copyPass() {
  const pass = document.getElementById('kinescopePass')?.textContent;
  if (!pass) return;
  navigator.clipboard.writeText(pass).then(() => {
    const btn = document.getElementById('copyPassBtn');
    btn.textContent = 'Скопировано ✓';
    btn.style.color = 'var(--success)';
    setTimeout(() => { btn.textContent = 'Скопировать'; btn.style.color = 'var(--marsala)'; }, 2000);
  });
}

// Periodic progress save every 30s
setInterval(async () => {
  if (progressSent) return;
  const iframe = document.getElementById('kinescopePlayer');
  if (iframe) {
    iframe.contentWindow?.postMessage({ type: 'kinescopeGetCurrentTime' }, '*');
  }
}, 30000);
</script>
</body>
</html>
