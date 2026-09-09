<?php
/**
 * Публичная страница-лид-магнит: /free/<slug>
 * Данные редактируются в админке → «Бесплатные страницы».
 * Авторизация не требуется.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';  // e()

const FREE_PAGE_HOST = 'https://luchistaya-yoga.ru';

function free_page_404(): never {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<meta name="robots" content="noindex">'
       . '<title>Страница не найдена</title>'
       . '<style>body{margin:0;min-height:100vh;display:flex;flex-direction:column;'
       . 'align-items:center;justify-content:center;gap:16px;background:#F2EAD8;color:#1B1612;'
       . 'font-family:Georgia,serif;text-align:center;padding:24px}</style></head><body>'
       . '<h1 style="font-weight:400">Страница не найдена</h1>'
       . '<p><a href="/trial" style="color:#9B3A4A">Посмотреть бесплатный урок</a> · '
       . '<a href="/" style="color:#9B3A4A">На главную</a></p>'
       . '</body></html>';
    exit;
}

// Slug читаем из пути запроса, а не из $_GET: query-строку можно подделать
// (/free/sheya?slug=other), а REQUEST_URI при внутреннем rewrite остаётся исходным.
$reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
if (!preg_match('#^/free/([a-z0-9-]{1,80})/?$#', $reqPath, $m)) {
    free_page_404();
}
$slug = $m[1];

$s = db()->prepare(
    'SELECT slug, eyebrow, heading, subline, intro_text, disclaimer,
            kinescope_id, is_vertical, meta_description
     FROM free_pages
     WHERE slug = ? AND is_visible = 1'
);
$s->execute([$slug]);
$page = $s->fetch();

// Видео ещё не привязано — страницу не показываем.
if (!$page || $page['kinescope_id'] === '') {
    free_page_404();
}

$heading     = e((string)$page['heading']);
$eyebrow     = e((string)$page['eyebrow']);
$subline     = trim((string)($page['subline'] ?? ''));
$introText   = trim((string)($page['intro_text'] ?? ''));
$disclaimer  = trim((string)($page['disclaimer'] ?? ''));
$kinescopeId = e((string)$page['kinescope_id']);
$isVertical  = (int)$page['is_vertical'] === 1;
$canonical   = FREE_PAGE_HOST . '/free/' . $page['slug'];

$metaDesc = trim((string)($page['meta_description'] ?? ''));
if ($metaDesc === '') {
    $metaDesc = mb_substr($introText !== '' ? $introText : (string)$page['heading'], 0, 160);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $heading ?> — бесплатный урок · Любовь Лучистая</title>
<meta name="description" content="<?= e($metaDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="video.other">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:title" content="<?= $heading ?> — бесплатный урок">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:site_name" content="Любовь Лучистая">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css?v=4">
<link rel="stylesheet" href="/assets/css/trial.css?v=1">
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

  <div class="trial-eyebrow"><?= $eyebrow ?></div>
  <h1 class="free-title"><?= $heading ?></h1>
  <?php if ($subline !== ''): ?>
  <div class="free-subline"><?= e($subline) ?></div>
  <?php endif; ?>

  <div class="player-wrap<?= $isVertical ? ' player-wrap--vertical' : '' ?>" style="margin-bottom:32px">
    <iframe
      src="https://kinescope.io/embed/<?= $kinescopeId ?>"
      title="<?= $heading ?>"
      allow="autoplay; fullscreen; picture-in-picture"
      allowfullscreen
    ></iframe>
  </div>

  <?php if (!$isVertical): ?>
  <p class="rotation-hint">Для просмотра горизонтально — отключите блокировку поворота на телефоне</p>
  <?php endif; ?>

  <?php if ($introText !== '' || $disclaimer !== ''): ?>
  <div class="trial-desc-card">
    <div class="trial-eyebrow">Об уроке</div>
    <?php if ($introText !== ''): ?>
    <div class="trial-desc-text"><?= nl2br(e($introText)) ?></div>
    <?php endif; ?>
    <?php if ($disclaimer !== ''): ?>
    <p class="free-disclaimer"><?= e($disclaimer) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="trial-cta">
    <div class="trial-eyebrow">Клуб Любови Лучистой</div>
    <h2>Понравился урок?</h2>
    <p>В клубе — больше 100 уроков по йоге, живые занятия в Zoom и поддерживающее сообщество.</p>
    <div class="trial-cta__actions">
      <a href="/trial-extra" class="ll-btn ll-btn-ghost">Ещё бесплатные уроки →</a>
      <a href="https://t.me/indicatrisa" target="_blank" rel="noopener" class="ll-btn ll-btn-secondary">Подписаться в TG →</a>
      <a href="https://vk.com/lubov.yoga" target="_blank" rel="noopener" class="ll-btn ll-btn-secondary">Подписаться в VK →</a>
      <a href="/#pricing" class="ll-btn ll-btn-primary">Вступить в клуб →</a>
    </div>
  </div>

</main>

</body>
</html>
