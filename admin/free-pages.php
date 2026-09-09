<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/response.php';

$admin = require_admin();

$pages = db()->query(
    'SELECT id, slug, eyebrow, heading, subline, intro_text, disclaimer,
            kinescope_id, is_vertical, meta_description, is_visible, sort_order
     FROM free_pages ORDER BY sort_order, id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Бесплатные страницы — Администратор</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Instrument+Sans:wght@400;500&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.fp-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 16px; border-bottom:1px solid var(--cream-deep); }
.fp-row:last-child { border-bottom:none; }
.fp-url { font-size:12px; color:var(--muted); font-family:'Instrument Sans',sans-serif; }
.fp-hint { font-size:11px; color:var(--muted); margin-top:3px; }
</style>
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar">
    <h1>Бесплатные страницы</h1>
    <button class="btn btn-primary btn-sm" onclick="openNew()">+ Новая страница</button>
  </div>
  <div class="admin-main">
    <div id="pageAlert" class="alert" style="display:none"></div>

    <div class="acard" style="margin-bottom:20px">
      <div class="acard-title">Как это работает</div>
      <p style="font-size:13px;color:var(--muted);line-height:1.6;margin:0">
        Каждая страница доступна по адресу <strong>/free/адрес</strong> без регистрации и входа.
        Заполните адрес, заголовок и Kinescope ID — страница появится сразу.
        Пока поле Kinescope пустое или страница скрыта, посетитель увидит «страница не найдена».
      </p>
    </div>

    <div class="acard" style="padding:0">
      <?php foreach ($pages as $i => $p): ?>
      <div class="fp-row" data-id="<?= (int)$p['id'] ?>">
        <div style="display:flex;flex-direction:column;gap:2px">
          <button class="btn btn-ghost btn-sm" style="padding:0 6px;font-size:12px;line-height:1.4"
                  onclick="move(<?= (int)$p['id'] ?>, -1)" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
          <button class="btn btn-ghost btn-sm" style="padding:0 6px;font-size:12px;line-height:1.4"
                  onclick="move(<?= (int)$p['id'] ?>, 1)" <?= $i === count($pages) - 1 ? 'disabled' : '' ?>>&darr;</button>
        </div>
        <div style="flex:1;min-width:200px">
          <div style="font-size:14px;font-weight:500"><?= e((string)$p['heading']) ?></div>
          <div class="fp-url">/free/<?= e((string)$p['slug']) ?></div>
        </div>
        <?php if ($p['is_vertical']): ?><span class="badge" style="font-size:10px">вертикальное</span><?php endif; ?>
        <?php if ($p['kinescope_id'] === ''): ?><span class="badge badge-inactive" style="font-size:10px">нет видео</span><?php endif; ?>
        <?php if (!$p['is_visible']): ?><span class="badge badge-inactive" style="font-size:10px">скрыта</span><?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="/free/<?= e((string)$p['slug']) ?>" target="_blank" rel="noopener">Открыть</a>
        <button class="btn btn-ghost btn-sm" onclick="openEdit(<?= (int)$p['id'] ?>)">Редактировать</button>
        <button class="btn btn-ghost btn-sm" onclick="toggleVisible(<?= (int)$p['id'] ?>, <?= $p['is_visible'] ? 0 : 1 ?>)"><?= $p['is_visible'] ? 'Скрыть' : 'Показать' ?></button>
        <button class="btn btn-danger btn-sm" onclick="removePage(<?= (int)$p['id'] ?>)">Удалить</button>
      </div>
      <?php endforeach; ?>
      <?php if (!$pages): ?>
      <div style="text-align:center;padding:40px">
        <p style="color:var(--muted)">Страниц пока нет. Создайте первую.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>

<!-- Модалка -->
<div id="fpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--white);padding:32px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto">
    <h2 style="font-size:18px;margin-bottom:20px" id="fpModalTitle">Страница</h2>
    <div id="modalAlert" class="alert" style="display:none"></div>
    <input type="hidden" id="fpId">

    <div class="form-group">
      <label>Адрес страницы *</label>
      <input type="text" id="fpSlug" class="form-control" placeholder="sheya">
      <p class="fp-hint">Латиницей, строчными. Получится <code>/free/sheya</code>. Менять у работающей страницы не стоит — старая ссылка перестанет открываться.</p>
    </div>
    <div class="form-group">
      <label>Kinescope ID или ссылка</label>
      <input type="text" id="fpKinescope" class="form-control" placeholder="https://kinescope.io/abc123 или abc123">
      <p class="fp-hint">Можно вставить ссылку целиком — лишнее отрежется автоматически.</p>
    </div>
    <div class="form-group" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="fpVertical" style="width:16px;height:16px;cursor:pointer">
      <label for="fpVertical" style="margin:0;cursor:pointer;font-size:14px">Вертикальное видео (снято на телефон)</label>
    </div>
    <div class="form-group">
      <label>Надзаголовок</label>
      <input type="text" id="fpEyebrow" class="form-control" placeholder="Бесплатный урок">
    </div>
    <div class="form-group">
      <label>Заголовок *</label>
      <input type="text" id="fpHeading" class="form-control" placeholder="Разгрузка шеи, плеч и лопаток">
    </div>
    <div class="form-group">
      <label>Подзаголовок</label>
      <input type="text" id="fpSubline" class="form-control" placeholder="18 минут • с подробными объяснениями">
    </div>
    <div class="form-group">
      <label>Текст под видео</label>
      <textarea id="fpIntro" class="form-control" rows="4" placeholder="Кому подойдёт этот урок и что он даёт…"></textarea>
    </div>
    <div class="form-group">
      <label>Предупреждение мелким шрифтом</label>
      <input type="text" id="fpDisclaimer" class="form-control" placeholder="При острой боли — сначала к врачу.">
    </div>
    <div class="form-group">
      <label>Описание для поисковиков</label>
      <textarea id="fpMeta" class="form-control" rows="2" placeholder="До 160 символов. Если пусто — возьмётся текст под видео."></textarea>
    </div>
    <div class="form-group" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" id="fpVisible" style="width:16px;height:16px;cursor:pointer" checked>
      <label for="fpVisible" style="margin:0;cursor:pointer;font-size:14px">Страница опубликована</label>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button class="btn btn-ghost" onclick="closeModal()">Отмена</button>
      <button class="btn btn-primary" onclick="save()">Сохранить</button>
    </div>
  </div>
</div>

<script>
const CSRF  = <?= json_encode(csrf_token()) ?>;
const PAGES = <?= json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const ERRORS = {
  invalid_slug:    'Адрес можно писать только латиницей, цифрами и дефисом.',
  slug_taken:      'Такой адрес уже занят другой страницей.',
  missing_heading: 'Заголовок обязателен.',
  invalid_field:   'Одно из полей заполнено неверно.',
  invalid_kinescope: 'Не удалось разобрать ссылку на Кинескоп. Вставьте ссылку вида https://kinescope.io/abc123 или сам ID.',
};

function showAlert(msg, type = 'success') {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 4000);
}

function fill(p) {
  document.getElementById('fpId').value         = p ? p.id : '';
  document.getElementById('fpSlug').value       = p ? p.slug : '';
  document.getElementById('fpKinescope').value  = p ? (p.kinescope_id || '') : '';
  document.getElementById('fpVertical').checked = p ? !!Number(p.is_vertical) : false;
  document.getElementById('fpEyebrow').value    = p ? (p.eyebrow || '') : 'Бесплатный урок';
  document.getElementById('fpHeading').value    = p ? p.heading : '';
  document.getElementById('fpSubline').value    = p ? (p.subline || '') : '';
  document.getElementById('fpIntro').value      = p ? (p.intro_text || '') : '';
  document.getElementById('fpDisclaimer').value = p ? (p.disclaimer || '') : '';
  document.getElementById('fpMeta').value       = p ? (p.meta_description || '') : '';
  document.getElementById('fpVisible').checked  = p ? !!Number(p.is_visible) : true;
  document.getElementById('modalAlert').style.display = 'none';
  document.getElementById('fpModal').style.display = 'flex';
}

function openNew() {
  document.getElementById('fpModalTitle').textContent = 'Новая страница';
  fill(null);
}

function openEdit(id) {
  const p = PAGES.find(x => Number(x.id) === id);
  if (!p) return;
  document.getElementById('fpModalTitle').textContent = 'Редактировать страницу';
  fill(p);
}

function closeModal() {
  document.getElementById('fpModal').style.display = 'none';
}

async function save() {
  const id = parseInt(document.getElementById('fpId').value);
  const payload = {
    slug:             document.getElementById('fpSlug').value.trim().toLowerCase(),
    kinescope_id:     document.getElementById('fpKinescope').value.trim(),
    is_vertical:      document.getElementById('fpVertical').checked ? 1 : 0,
    eyebrow:          document.getElementById('fpEyebrow').value.trim(),
    heading:          document.getElementById('fpHeading').value.trim(),
    subline:          document.getElementById('fpSubline').value.trim(),
    intro_text:       document.getElementById('fpIntro').value.trim(),
    disclaimer:       document.getElementById('fpDisclaimer').value.trim(),
    meta_description: document.getElementById('fpMeta').value.trim(),
    is_visible:       document.getElementById('fpVisible').checked ? 1 : 0,
  };
  if (id) payload.id = id;

  const res = await api('/api/admin/free-pages.php', id ? 'PUT' : 'POST', payload);
  if (res.ok) { location.reload(); return; }

  const el = document.getElementById('modalAlert');
  el.className = 'alert alert-error';
  el.textContent = ERRORS[res.error] || 'Ошибка сохранения.';
  el.style.display = 'block';
}

async function toggleVisible(id, visible) {
  const res = await api('/api/admin/free-pages.php', 'PUT', { id, is_visible: visible });
  if (res.ok) location.reload();
  else showAlert('Ошибка.', 'error');
}

async function removePage(id) {
  const p = PAGES.find(x => Number(x.id) === id);
  if (!confirm('Удалить страницу /free/' + (p ? p.slug : '') + '? Ссылка перестанет открываться.')) return;
  const res = await api('/api/admin/free-pages.php', 'DELETE', { id });
  if (res.ok) location.reload();
  else showAlert('Ошибка удаления.', 'error');
}

async function move(id, dir) {
  const rows = Array.from(document.querySelectorAll('.fp-row'));
  const idx  = rows.findIndex(r => Number(r.dataset.id) === id);
  const swap = idx + dir;
  if (idx < 0 || swap < 0 || swap >= rows.length) return;
  const otherId = Number(rows[swap].dataset.id);
  const r1 = await api('/api/admin/free-pages.php', 'PUT', { id, sort_order: swap * 10 });
  const r2 = await api('/api/admin/free-pages.php', 'PUT', { id: otherId, sort_order: idx * 10 });
  if (r1.ok && r2.ok) location.reload();
  else showAlert('Ошибка перемещения.', 'error');
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

document.getElementById('fpModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeModal();
});
</script>
</body>
</html>
