<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_login();
$sub = get_subscription($user['id']);
$hasAccess = has_active_access($sub);

$favorites = [];
if ($hasAccess) {
    $s = db()->prepare(
        'SELECT l.id, l.title, l.duration_min, t.title AS topic_title
         FROM lesson_favorites lf
         JOIN lessons l ON l.id = lf.lesson_id AND l.is_visible = 1
         JOIN topics t ON t.id = l.topic_id
         WHERE lf.user_id = ?
         ORDER BY lf.created_at DESC'
    );
    $s->execute([$user['id']]);
    $favorites = $s->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Избранное — Клуб йоги</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css?v=3">
</head>
<body>
<header class="lk-header">
  <a href="/" class="logo">Любовь Лучистая</a>
  <nav>
    <a href="/cabinet/">Кабинет</a>
    <a href="/cabinet/favorites.php">Избранное</a>
    <a href="/cabinet/profile.php">Профиль</a>
  </nav>
</header>
<main class="lk-main">
  <h1 style="font-size:28px; margin-bottom:24px">Избранное</h1>

  <?php if (!$hasAccess): ?>
  <div class="alert alert-info">Избранное доступно при активной подписке.</div>

  <?php elseif (empty($favorites)): ?>
  <div class="card" style="text-align:center; padding:40px">
    <p style="font-size:18px; font-family:'Instrument Serif',serif; margin-bottom:12px">Пока ничего нет</p>
    <p style="color:var(--muted); margin-bottom:20px">Добавляйте уроки в избранное, нажав ♥ рядом с уроком</p>
    <a href="/cabinet/" class="btn btn-outline">Перейти к урокам</a>
  </div>

  <?php else: ?>
  <div id="favList">
    <?php foreach ($favorites as $f): ?>
    <div class="lesson-item" id="fav-<?= (int)$f['id'] ?>" style="border:1px solid var(--cream-deep); margin-bottom:8px; cursor:default">
      <div style="flex:1; padding-left:0">
        <p style="font-size:12px; color:var(--muted); margin-bottom:2px">
          <?= htmlspecialchars($f['topic_title'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <a href="/cabinet/lesson.php?id=<?= (int)$f['id'] ?>" class="lesson-title" style="font-size:15px; color:var(--ink); text-decoration:none">
          <?= htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8') ?>
        </a>
      </div>
      <?php if ($f['duration_min']): ?>
      <span class="lesson-duration"><?= (int)$f['duration_min'] ?> мин</span>
      <?php endif; ?>
      <button class="lesson-fav active" onclick="removeFav(<?= (int)$f['id'] ?>, this)" title="Убрать из избранного">♥</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

async function removeFav(lessonId, btn) {
  btn.disabled = true;
  try {
    await fetch('/api/cabinet/favorites.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ lesson_id: lessonId, action: 'remove' }),
    });
    const el = document.getElementById('fav-' + lessonId);
    if (el) el.remove();
    const list = document.getElementById('favList');
    if (list && !list.children.length) location.reload();
  } catch {
    btn.disabled = false;
  }
}
</script>
</body>
</html>
