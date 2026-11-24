<?php
/**
 * Страница-заглушка: урок есть, доступа к нему нет.
 *
 * Подключается из cabinet/lesson.php и отдаёт полноценную страницу,
 * поэтому вызывающий файл после include сразу завершается.
 * Ожидает заданными: $user, $lesson (с topic_title).
 */

$lockedProducts = [];
foreach (lesson_product_ids((int)$lesson['id']) as $pid) {
    $p = product_by_id($pid);
    if ($p && $p['is_active']) $lockedProducts[] = $p;
}

$lessonTitle = htmlspecialchars($lesson['title'], ENT_QUOTES, 'UTF-8');
$topicTitle  = htmlspecialchars($lesson['topic_title'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $lessonTitle ?> — нужен доступ · Клуб йоги</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css?v=4">
</head>
<body>

<header class="lk-header">
  <a href="/" class="logo">Любовь Лучистая</a>
  <nav>
    <a href="/cabinet/">← Все уроки</a>
    <a href="/cabinet/favorites.php">Избранное</a>
    <a href="/cabinet/profile.php">Профиль</a>
  </nav>
</header>

<main class="lk-main">
  <p style="font-size:13px; color:var(--muted); margin-bottom:8px">
    <a href="/cabinet/" style="color:var(--muted)">Уроки</a> › <?= $topicTitle ?>
  </p>

  <h1 style="font-size:28px; line-height:1.2; margin-bottom:8px"><?= $lessonTitle ?></h1>
  <p style="color:var(--muted); font-size:15px; margin-bottom:28px">
    Этот урок входит в продукт, к которому у вас пока нет доступа.
  </p>

  <?php if (!$lockedProducts): ?>
  <div class="card" style="text-align:center; padding:40px">
    <p style="font-size:18px; font-family:'Instrument Serif',serif; margin-bottom:12px">
      Урок пока недоступен
    </p>
    <p style="color:var(--muted); font-size:14px; margin-bottom:20px">
      Напишите Любови — подскажем, как получить доступ.
    </p>
    <a href="https://t.me/indicatrisa" target="_blank" rel="noopener" class="btn btn-primary">
      Написать в Telegram →
    </a>
  </div>
  <?php else: ?>
    <?php foreach ($lockedProducts as $p):
      $price = product_price_for_user((int)$user['id'], $p);
      $cta   = $p['payment_url'] ?: $p['contact_url'];
      $ctaIsPayment = (bool)$p['payment_url'];
    ?>
    <div class="card" style="margin-bottom:20px">
      <p class="card-title" style="font-size:20px; font-family:'Instrument Serif',serif; margin-bottom:6px">
        <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>
      </p>
      <?php if ($p['subtitle']): ?>
      <p style="color:var(--muted); font-size:14px; margin-bottom:16px">
        <?= htmlspecialchars($p['subtitle'], ENT_QUOTES, 'UTF-8') ?>
      </p>
      <?php endif; ?>

      <?php if ($p['description']): ?>
      <div style="font-size:16px; line-height:1.8; color:var(--ink-soft); margin-bottom:20px">
        <?= nl2br(htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8')) ?>
      </div>
      <?php endif; ?>

      <p style="font-size:15px; margin-bottom:16px">
        Стоимость: <strong><?= number_format($price, 0, ',', ' ') ?> ₽</strong>
        <?php if ($p['type'] === 'subscription' && $p['duration_days']): ?>
        <span style="color:var(--muted); font-size:13px">
          за <?= (int)$p['duration_days'] ?> дней
        </span>
        <?php endif; ?>
      </p>

      <?php if ($cta): ?>
      <a href="<?= htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') ?>"
         <?= $ctaIsPayment ? '' : 'target="_blank" rel="noopener"' ?>
         class="btn btn-primary" style="display:inline-block; padding:12px 28px">
        <?= $ctaIsPayment ? 'Получить доступ →' : 'Написать Любови →' ?>
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="margin-top:32px">
    <a href="/cabinet/" class="btn btn-ghost">← Все уроки</a>
  </div>
</main>

</body>
</html>
