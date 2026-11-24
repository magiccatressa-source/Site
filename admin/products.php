<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/response.php';

$admin = require_admin();

$ready = access_model_ready();

$products = [];
$topics   = [];
$orphans  = [];
$clubId   = 0;

if ($ready) {
    $clubId = club_product_id();
    $products = db()->query(
        'SELECT p.*,
                (SELECT COUNT(*) FROM user_access ua
                  WHERE ua.product_id = p.id AND ua.status = "active"
                    AND (ua.expires_at IS NULL OR ua.expires_at > NOW())) AS active_count,
                (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id) AS orders_count
         FROM products p ORDER BY p.sort_order, p.id'
    )->fetchAll();

    $bind = db()->query('SELECT product_id, topic_id FROM product_topics')->fetchAll();
    $byProduct = [];
    foreach ($bind as $b) {
        $byProduct[(int)$b['product_id']][] = (int)$b['topic_id'];
    }
    foreach ($products as &$p) {
        $p['topic_ids'] = $byProduct[(int)$p['id']] ?? [];
    }
    unset($p);

    $topics  = db()->query('SELECT id, title FROM topics ORDER BY sort_order, id')->fetchAll();
    $orphans = db()->query(
        'SELECT t.id, t.title FROM topics t
         WHERE NOT EXISTS (SELECT 1 FROM product_topics pt WHERE pt.topic_id = t.id)
         ORDER BY t.sort_order, t.id'
    )->fetchAll();
}

$typeLabels = ['subscription' => 'Подписка', 'one_time' => 'Разовая покупка'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Продукты — Администратор</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.pr-row   { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:16px; border-bottom:1px solid var(--cream-deep); }
.pr-row:last-child { border-bottom:none; }
.pr-slug  { font-size:12px; color:var(--muted); font-family:'Instrument Sans',sans-serif; }
.pr-hint  { font-size:11px; color:var(--muted); margin-top:3px; line-height:1.5; }
.pr-topics{ display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:6px; max-height:230px; overflow-y:auto; padding:10px; border:1px solid var(--cream-deep); }
.pr-topics label { display:flex; align-items:center; gap:7px; margin:0; font-size:13px; cursor:pointer; font-weight:400; }
</style>
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar">
    <h1>Продукты</h1>
    <?php if ($ready): ?>
    <button class="btn btn-primary btn-sm" onclick="openNew()">+ Новый продукт</button>
    <?php endif; ?>
  </div>
  <div class="admin-main">
    <div id="pageAlert" class="alert" style="display:none"></div>

    <?php if (!$ready): ?>
    <div class="acard">
      <div class="acard-title">Раздел пока не работает</div>
      <p style="font-size:14px;color:var(--muted);line-height:1.6;margin:0">
        В базе нет таблицы продуктов — значит миграция ещё не применена.
        Сайт сейчас работает по старой модели подписки. Примените
        <code>db/migrations/0007_products.sql</code>, и раздел включится сам.
      </p>
    </div>
    <?php else: ?>

    <div class="acard" style="margin-bottom:20px">
      <div class="acard-title">Как это работает</div>
      <p style="font-size:13px;color:var(--muted);line-height:1.7;margin:0">
        Продукт — это то, что человек получает: клуб, отдельный курс, мини-курс.
        К каждому продукту привязываются темы из раздела «Уроки и темы»: человек
        видит уроки только тех тем, которые входят в доступные ему продукты.<br>
        <strong>Тема может входить сразу в несколько продуктов</strong> — например,
        вводный урок и в клубе, и в курсе. А тема, не входящая никуда, не видна
        никому, даже участницам клуба.
      </p>
    </div>

    <?php if ($orphans): ?>
    <div class="alert alert-error" style="margin-bottom:20px">
      <strong>Темы без продукта — их уроки сейчас не видит никто:</strong>
      <?= e(implode(', ', array_column($orphans, 'title'))) ?>.
      Отметьте их в нужном продукте.
    </div>
    <?php endif; ?>

    <div class="acard" style="padding:0">
      <?php foreach ($products as $p): $isClub = ((int)$p['id'] === $clubId); ?>
      <div class="pr-row" data-id="<?= (int)$p['id'] ?>">
        <div style="flex:1;min-width:220px">
          <div style="font-size:15px;font-weight:500">
            <?= e((string)$p['title']) ?>
            <?php if ($isClub): ?><span class="badge" style="font-size:10px">основной</span><?php endif; ?>
            <?php if (!$p['is_active']): ?><span class="badge badge-inactive" style="font-size:10px">скрыт</span><?php endif; ?>
          </div>
          <div class="pr-slug">
            <?= e($typeLabels[$p['type']] ?? (string)$p['type']) ?>
            · <?= number_format((float)$p['price'], 0, ',', ' ') ?> ₽<?php
              if ($p['price_renewal'] !== null): ?>, продление <?= number_format((float)$p['price_renewal'], 0, ',', ' ') ?> ₽<?php endif; ?>
            <?php if ($p['duration_days']): ?> · <?= (int)$p['duration_days'] ?> дней<?php endif; ?>
            · тем: <?= count($p['topic_ids']) ?>
          </div>
        </div>
        <div style="text-align:center;min-width:90px">
          <div style="font-size:20px;font-weight:500"><?= (int)$p['active_count'] ?></div>
          <div class="pr-slug">с доступом</div>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="openEdit(<?= (int)$p['id'] ?>)">Редактировать</button>
        <button class="btn btn-ghost btn-sm" onclick="toggleActive(<?= (int)$p['id'] ?>, <?= $p['is_active'] ? 0 : 1 ?>)">
          <?= $p['is_active'] ? 'Скрыть' : 'Показать' ?>
        </button>
        <?php if (!$isClub): ?>
        <button class="btn btn-danger btn-sm" onclick="removeProduct(<?= (int)$p['id'] ?>)">Удалить</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </div>
</div>
</div>

<?php if ($ready): ?>
<!-- Модалка -->
<div id="prModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--white);padding:32px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto">
    <h2 style="font-size:18px;margin-bottom:20px" id="prModalTitle">Продукт</h2>
    <div id="modalAlert" class="alert" style="display:none"></div>
    <input type="hidden" id="prId">

    <div class="form-group">
      <label>Название *</label>
      <input type="text" id="prTitle" class="form-control" placeholder="Осанка и дыхание">
    </div>

    <div class="form-group">
      <label>Адрес *</label>
      <input type="text" id="prSlug" class="form-control" placeholder="osanka">
      <p class="pr-hint">Латиницей, строчными. Понадобится для будущей страницы курса. У клуба менять нельзя.</p>
    </div>

    <div class="form-group">
      <label>Короткое описание</label>
      <input type="text" id="prSubtitle" class="form-control" placeholder="Шесть уроков про спину и дыхание">
    </div>

    <div class="form-group">
      <label>Подробное описание</label>
      <textarea id="prDescription" class="form-control" rows="4" placeholder="Что входит, кому подойдёт, сколько длится…"></textarea>
      <p class="pr-hint">Показывается там, где человек видит, что ему предлагают купить.</p>
    </div>

    <div class="form-group">
      <label>Тип *</label>
      <select id="prType" class="form-control" onchange="typeChanged()">
        <option value="one_time">Разовая покупка — купил один раз и курс твой</option>
        <option value="subscription">Подписка — доступ на срок, который надо продлевать</option>
      </select>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:140px">
        <label>Цена, ₽ *</label>
        <input type="text" id="prPrice" class="form-control" placeholder="3000" inputmode="numeric">
      </div>
      <div class="form-group" style="flex:1;min-width:140px" id="prRenewalBox">
        <label>Цена продления, ₽</label>
        <input type="text" id="prPriceRenewal" class="form-control" placeholder="пусто = та же цена" inputmode="numeric">
      </div>
      <div class="form-group" style="flex:1;min-width:140px" id="prDurationBox">
        <label>Срок, дней</label>
        <input type="text" id="prDuration" class="form-control" placeholder="30" inputmode="numeric">
      </div>
    </div>

    <div class="form-group">
      <label>Ссылка на оплату</label>
      <input type="text" id="prPaymentUrl" class="form-control" placeholder="https://luchistaya-yoga.payform.ru">
      <p class="pr-hint">Если пусто — вместо кнопки оплаты человек увидит «Написать Любови».</p>
    </div>

    <div class="form-group">
      <label>Ссылка «написать» </label>
      <input type="text" id="prContactUrl" class="form-control" placeholder="https://t.me/indicatrisa">
    </div>

    <div class="form-group">
      <label>Какие темы входят в продукт</label>
      <div class="pr-topics" id="prTopics">
        <?php foreach ($topics as $t): ?>
        <label><input type="checkbox" value="<?= (int)$t['id'] ?>" style="width:15px;height:15px"> <?= e((string)$t['title']) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="pr-hint">Снять галочку у темы, которая больше нигде не отмечена, — значит спрятать её уроки ото всех.</p>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="prActive" style="width:16px;height:16px;cursor:pointer" checked>
      <label for="prActive" style="margin:0;cursor:pointer;font-size:14px">Продукт показывается на витрине</label>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button class="btn btn-ghost" onclick="closeModal()">Отмена</button>
      <button class="btn btn-primary" onclick="save()">Сохранить</button>
    </div>
  </div>
</div>

<script>
const CSRF     = <?= json_encode(csrf_token()) ?>;
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const CLUB_ID  = <?= (int)$clubId ?>;

const ERRORS = {
  invalid_slug:        'Адрес можно писать только латиницей, цифрами и дефисом.',
  slug_taken:          'Такой адрес уже занят другим продуктом.',
  missing_title:       'Название обязательно.',
  invalid_type:        'Неизвестный тип продукта.',
  invalid_price:       'Цена указана неверно.',
  invalid_duration:    'Срок должен быть числом от 1 до 3650 дней.',
  duration_required:   'У подписки нужно указать срок в днях — иначе непонятно, на сколько выдаётся доступ.',
  invalid_url:         'Ссылка должна начинаться с http:// или https://',
  club_slug_locked:    'У клуба нельзя менять адрес — на нём держится доступ действующих участниц.',
  club_undeletable:    'Клуб удалить нельзя.',
  product_has_access:  'У этого продукта есть выданные доступы. Удалять нельзя — скройте его кнопкой «Скрыть».',
  product_has_orders:  'По этому продукту есть заказы, история оплат не должна потеряться. Скройте его вместо удаления.',
  product_not_found:   'Продукт не найден.',
  access_model_not_ready: 'Миграция базы ещё не применена.',
};

function showAlert(msg, type = 'success') {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  setTimeout(() => el.style.display = 'none', 6000);
}

function typeChanged() {
  const isSub = document.getElementById('prType').value === 'subscription';
  document.getElementById('prRenewalBox').style.display  = isSub ? '' : 'none';
  document.getElementById('prDurationBox').style.display = isSub ? '' : 'none';
}

function fill(p) {
  document.getElementById('prId').value          = p ? p.id : '';
  document.getElementById('prTitle').value       = p ? p.title : '';
  document.getElementById('prSlug').value        = p ? p.slug : '';
  document.getElementById('prSlug').disabled     = !!(p && Number(p.id) === CLUB_ID);
  document.getElementById('prSubtitle').value    = p ? (p.subtitle || '') : '';
  document.getElementById('prDescription').value = p ? (p.description || '') : '';
  document.getElementById('prType').value        = p ? p.type : 'one_time';
  document.getElementById('prPrice').value       = p ? String(Math.round(Number(p.price))) : '';
  document.getElementById('prPriceRenewal').value = p && p.price_renewal !== null ? String(Math.round(Number(p.price_renewal))) : '';
  document.getElementById('prDuration').value    = p && p.duration_days ? String(p.duration_days) : '';
  document.getElementById('prPaymentUrl').value  = p ? (p.payment_url || '') : '';
  document.getElementById('prContactUrl').value  = p ? (p.contact_url || '') : '';
  document.getElementById('prActive').checked    = p ? !!Number(p.is_active) : true;

  const ids = p ? (p.topic_ids || []).map(Number) : [];
  document.querySelectorAll('#prTopics input[type=checkbox]').forEach(cb => {
    cb.checked = ids.includes(Number(cb.value));
  });

  typeChanged();
  document.getElementById('modalAlert').style.display = 'none';
  document.getElementById('prModal').style.display = 'flex';
}

function openNew() {
  document.getElementById('prModalTitle').textContent = 'Новый продукт';
  fill(null);
}

function openEdit(id) {
  const p = PRODUCTS.find(x => Number(x.id) === id);
  if (!p) return;
  document.getElementById('prModalTitle').textContent = 'Редактировать продукт';
  fill(p);
}

function closeModal() {
  document.getElementById('prModal').style.display = 'none';
}

function checkedTopicIds() {
  return Array.from(document.querySelectorAll('#prTopics input[type=checkbox]:checked')).map(cb => Number(cb.value));
}

async function save() {
  const id = parseInt(document.getElementById('prId').value);
  const payload = {
    title:         document.getElementById('prTitle').value.trim(),
    subtitle:      document.getElementById('prSubtitle').value.trim(),
    description:   document.getElementById('prDescription').value.trim(),
    type:          document.getElementById('prType').value,
    price:         document.getElementById('prPrice').value.trim(),
    price_renewal: document.getElementById('prPriceRenewal').value.trim(),
    duration_days: document.getElementById('prDuration').value.trim(),
    payment_url:   document.getElementById('prPaymentUrl').value.trim(),
    contact_url:   document.getElementById('prContactUrl').value.trim(),
    is_active:     document.getElementById('prActive').checked ? 1 : 0,
    topic_ids:     checkedTopicIds(),
  };
  if (!(id && Number(id) === CLUB_ID)) {
    payload.slug = document.getElementById('prSlug').value.trim().toLowerCase();
  }
  if (id) payload.id = id;

  const res = await api('/api/admin/products.php', id ? 'PUT' : 'POST', payload);
  if (res.ok) {
    if (res.orphans && res.orphans.length) {
      sessionStorage.setItem('prWarn', 'Темы остались без продукта и теперь не видны никому: '
        + res.orphans.map(t => t.title).join(', '));
    }
    location.reload();
    return;
  }

  const el = document.getElementById('modalAlert');
  el.className = 'alert alert-error';
  el.textContent = ERRORS[res.error] || 'Ошибка сохранения.';
  el.style.display = 'block';
}

async function toggleActive(id, active) {
  const res = await api('/api/admin/products.php', 'PUT', { id, is_active: active });
  if (res.ok) location.reload();
  else showAlert(ERRORS[res.error] || 'Ошибка.', 'error');
}

async function removeProduct(id) {
  const p = PRODUCTS.find(x => Number(x.id) === id);
  if (!confirm('Удалить продукт «' + (p ? p.title : '') + '»? Это нельзя отменить.')) return;
  const res = await api('/api/admin/products.php', 'DELETE', { id });
  if (res.ok) location.reload();
  else showAlert(ERRORS[res.error] || 'Ошибка удаления.', 'error');
}

async function api(url, method, payload) {
  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload),
    });
    return await res.json();
  } catch { return { ok: false, error: 'network_error' }; }
}

document.getElementById('prModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeModal();
});

const warn = sessionStorage.getItem('prWarn');
if (warn) { sessionStorage.removeItem('prWarn'); showAlert(warn, 'error'); }
</script>
<?php endif; ?>
</body>
</html>
