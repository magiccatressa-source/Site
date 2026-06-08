<?php
require_once __DIR__ . '/includes/db.php';

function fetchLesson(int $id): ?array {
    if (!$id) return null;
    $s = db()->prepare(
        'SELECT l.id, l.title, l.description, l.kinescope_id, l.duration_min, t.title AS topic_title
         FROM lessons l
         JOIN topics t ON t.id = l.topic_id
         WHERE l.id = ? AND l.is_visible = 1'
    );
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

$lesson2 = fetchLesson((int)setting('trial_lesson_id_2'));
$lesson3 = fetchLesson((int)setting('trial_lesson_id_3'));

if (!$lesson2 && !$lesson3) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Уроки недоступны</title></head><body><h1>Уроки недоступны</h1><p><a href="/trial">Вернуться к пробному уроку</a></p></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ещё зарядки — бесплатно · Любовь Лучистая</title>
<meta name="description" content="Ещё два бесплатных урока йоги от Любови Лучистой.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css?v=3">
<style>
.trial-eyebrow {
  font-family: 'Instrument Sans', sans-serif;
  font-size: 11px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #C0532C;
  margin-bottom: 12px;
}
.trial-desc-card {
  background: #E8DEC6;
  padding: 24px;
  margin-bottom: 32px;
}
.trial-desc-card .trial-eyebrow { margin-bottom: 10px; }
.trial-desc-text {
  font-family: 'Spectral', Georgia, serif;
  font-size: 17px;
  font-weight: 300;
  line-height: 1.65;
  color: #3B312A;
}
.trial-lesson-block {
  margin-bottom: 56px;
  padding-bottom: 56px;
  border-bottom: 1px solid #C9BBA0;
}
.trial-lesson-block:last-of-type {
  border-bottom: none;
}
.trial-cta {
  background: #E8DEC6;
  border-radius: 0;
  padding: 48px 40px;
  margin-top: 40px;
}
.trial-cta h2 {
  font-family: 'Instrument Serif', Georgia, serif;
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 400;
  line-height: 1.05;
  letter-spacing: -0.01em;
  color: #1B1612;
  margin-bottom: 14px;
}
.trial-cta p {
  font-family: 'Spectral', Georgia, serif;
  font-size: 18px;
  font-weight: 300;
  line-height: 1.6;
  color: #3B312A;
  max-width: 520px;
  margin-bottom: 32px;
}
.trial-cta__actions { display: flex; gap: 12px; flex-wrap: wrap; }
.ll-btn {
  font-family: 'Instrument Sans', -apple-system, sans-serif;
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.04em;
  padding: 16px 28px;
  border-radius: 0;
  cursor: pointer;
  display: inline-block;
  text-decoration: none;
  transition: opacity 0.15s, background 0.15s;
  white-space: nowrap;
}
.ll-btn-primary  { background: #1B1612; color: #F2EAD8; border: 1.5px solid #1B1612; }
.ll-btn-primary:hover  { opacity: 0.85; }
.ll-btn-secondary { background: transparent; color: #1B1612; border: 1.5px solid #1B1612; }
.ll-btn-secondary:hover { background: rgba(27,22,18,0.06); }
@media (max-width: 600px) {
  .trial-cta { padding: 32px 20px; }
  .trial-cta__actions { flex-direction: column; }
  .ll-btn { text-align: center; width: 100%; }
}
</style>
</head>
<body>

<header class="lk-header">
  <a href="/" class="logo">Любовь Лучистая</a>
  <nav>
    <a href="/#about">О клубе</a>
    <a href="/cabinet/" class="btn btn-sm btn-primary" style="padding:6px 16px">Войти</a>
  </nav>
</header>

<main class="lk-main">

  <div class="trial-eyebrow">Бесплатные уроки</div>
  <h1 style="font-family:'Instrument Serif',Georgia,serif; font-size:clamp(32px,5vw,52px); font-weight:400; letter-spacing:-0.01em; line-height:1.05; margin-bottom:40px">Ещё два урока</h1>

  <?php foreach ([$lesson2, $lesson3] as $i => $lesson): ?>
  <?php if (!$lesson) continue; ?>
  <?php
    $title       = htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8');
    $description = $lesson['description'] ?? '';
    $kinescopeId = htmlspecialchars($lesson['kinescope_id'], ENT_QUOTES, 'UTF-8');
    $duration    = (int)$lesson['duration_min'];
  ?>
  <div class="trial-lesson-block">
    <div class="trial-eyebrow">Урок <?= $i + 2 ?> · бесплатно</div>

    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px">
      <h2 style="font-family:'Instrument Serif',Georgia,serif; font-size:clamp(22px,3vw,36px); font-weight:400; letter-spacing:-0.01em; line-height:1.1"><?= $title ?></h2>
      <?php if ($duration): ?>
      <span style="font-family:'Instrument Sans',sans-serif; font-size:13px; letter-spacing:0.06em; color:#3B312A; flex-shrink:0; margin-top:6px"><?= $duration ?> мин</span>
      <?php endif; ?>
    </div>

    <div class="player-wrap" style="margin-bottom:24px">
      <iframe
        src="https://kinescope.io/embed/<?= $kinescopeId ?>"
        allow="autoplay; fullscreen; picture-in-picture"
        allowfullscreen
      ></iframe>
    </div>

    <p class="rotation-hint">Для просмотра горизонтально — отключите блокировку поворота на телефоне</p>

    <?php if ($description): ?>
    <div class="trial-desc-card">
      <div class="trial-eyebrow">Описание урока</div>
      <div class="trial-desc-text">
        <?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="trial-cta">
    <div class="trial-eyebrow">Клуб Любови Лучистой</div>
    <h2>Хотите заниматься каждый день?</h2>
    <p>В клубе — больше 100 уроков по йоге, живые занятия в Zoom и поддерживающее сообщество.</p>
    <div class="trial-cta__actions">
      <a href="https://t.me/indicatrisa" target="_blank" rel="noopener" class="ll-btn ll-btn-secondary">Подписаться в TG →</a>
      <a href="https://vk.com/lubov.yoga" target="_blank" rel="noopener" class="ll-btn ll-btn-secondary">Подписаться в VK →</a>
      <a href="/#join" class="ll-btn ll-btn-primary">Вступить в клуб →</a>
    </div>
  </div>

</main>

</body>
</html>
