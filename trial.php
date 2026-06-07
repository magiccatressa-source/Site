<?php
require_once __DIR__ . '/includes/db.php';

$lessonId = (int)setting('trial_lesson_id');
$lesson = null;

if ($lessonId) {
    $s = db()->prepare(
        'SELECT l.id, l.title, l.description, l.kinescope_id, l.duration_min, t.title AS topic_title
         FROM lessons l
         JOIN topics t ON t.id = l.topic_id
         WHERE l.id = ? AND l.is_visible = 1'
    );
    $s->execute([$lessonId]);
    $lesson = $s->fetch();
}

if (!$lesson) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Урок недоступен</title></head><body><h1>Урок недоступен</h1><p><a href="/">На главную</a></p></body></html>';
    exit;
}

$title       = htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars(mb_substr((string)($lesson['description'] ?? ''), 0, 120), ENT_QUOTES, 'UTF-8');
$kinescopeId = htmlspecialchars($lesson['kinescope_id'], ENT_QUOTES, 'UTF-8');
$duration    = (int)$lesson['duration_min'];
$topicTitle  = htmlspecialchars($lesson['topic_title'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?> — Пробный урок · Любовь Лучистая</title>
<meta name="description" content="Бесплатный пробный урок от Любови Лучистой. <?= $description ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css?v=3">
<style>
.trial-cta {
  background: var(--cream-deep, #f5f0e8);
  border-radius: 12px;
  padding: 28px 24px;
  text-align: center;
  margin-top: 32px;
}
.trial-cta h2 {
  font-family: 'Instrument Serif', serif;
  font-size: 22px;
  margin-bottom: 10px;
}
.trial-cta p {
  font-size: 15px;
  color: var(--ink-soft, #5a4e45);
  margin-bottom: 20px;
  line-height: 1.6;
}
.trial-cta .btn-group {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
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
  <p style="font-size:13px; color:var(--muted); margin-bottom:8px">Пробный урок</p>

  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px">
    <h1 style="font-size:28px; line-height:1.2"><?= $title ?></h1>
    <?php if ($duration): ?>
    <span style="font-size:14px; color:var(--muted); flex-shrink:0; margin-top:6px"><?= $duration ?> мин</span>
    <?php endif; ?>
  </div>

  <div class="player-wrap" style="margin-bottom:32px">
    <iframe
      src="https://kinescope.io/embed/<?= $kinescopeId ?>"
      allow="autoplay; fullscreen; picture-in-picture"
      allowfullscreen
    ></iframe>
  </div>

  <p class="rotation-hint">Для просмотра горизонтально — отключите блокировку поворота на телефоне</p>

  <?php if ($lesson['description']): ?>
  <div class="card" style="margin-bottom:32px">
    <p class="card-title">Описание урока</p>
    <div style="font-size:16px; line-height:1.8; color:var(--ink-soft)">
      <?= nl2br(htmlspecialchars((string)$lesson['description'], ENT_QUOTES, 'UTF-8')) ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="trial-cta">
    <h2>Понравился урок?</h2>
    <p>В клубе — больше 100 уроков по йоге, живые занятия в Zoom и поддерживающее сообщество.</p>
    <div class="btn-group">
      <a href="/#join" class="btn btn-primary">Вступить в клуб</a>
      <a href="/cabinet/" class="btn btn-outline">Войти в аккаунт</a>
    </div>
  </div>
</main>

</body>
</html>
