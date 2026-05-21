<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin();

$topics = db()->query(
    'SELECT t.id, t.title, t.sort_order, t.is_visible,
            COUNT(l.id) AS lesson_count
     FROM topics t
     LEFT JOIN lessons l ON l.topic_id = t.id
     GROUP BY t.id
     ORDER BY t.sort_order, t.id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Уроки и темы — Администратор</title>
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.lesson-edit-form { display:none; background:#faf9f5; padding:16px; margin-top:8px; }
.lesson-edit-form.open { display:block; }
.kinescope-hint { font-size:11px; color:var(--muted); margin-top:3px; }
</style>
</head>
<body>
<div class="admin-wrap">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-content">
  <div class="admin-topbar">
    <h1>Уроки и темы</h1>
    <button class="btn btn-primary btn-sm" onclick="openNewTopicForm()">+ Новая тема</button>
  </div>
  <div class="admin-main">
    <div id="pageAlert" class="alert" style="display:none"></div>

    <!-- New topic form -->
    <div id="newTopicForm" class="acard" style="display:none;margin-bottom:20px">
      <div class="acard-title">Новая тема</div>
      <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0;flex:1;min-width:200px">
          <label>Название темы *</label>
          <input type="text" id="newTopicTitle" class="form-control" placeholder="Например: Растяжка">
        </div>
        <button class="btn btn-primary btn-sm" onclick="createTopic()">Создать</button>
        <button class="btn btn-ghost btn-sm" onclick="document.getElementById('newTopicForm').style.display='none'">Отмена</button>
      </div>
    </div>

    <!-- Topics list -->
    <div id="topicsList">
      <?php foreach ($topics as $t): ?>
      <div class="topic-block" data-id="<?= (int)$t['id'] ?>">
        <div class="topic-block-header" onclick="toggleTopicBlock(this.parentElement)">
          <strong><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></strong>
          <span style="font-size:12px;color:var(--muted)"><?= (int)$t['lesson_count'] ?> уроков</span>
          <?php if (!$t['is_visible']): ?><span class="badge badge-inactive">скрыта</span><?php endif; ?>
          <div style="display:flex;gap:6px" onclick="event.stopPropagation()">
            <button class="btn btn-ghost btn-sm" onclick="editTopic(<?= (int)$t['id'] ?>, '<?= htmlspecialchars(addslashes($t['title']), ENT_QUOTES, 'UTF-8') ?>')">Переименовать</button>
            <button class="btn btn-ghost btn-sm" onclick="toggleTopicVisible(<?= (int)$t['id'] ?>, <?= $t['is_visible'] ? 0 : 1 ?>)"><?= $t['is_visible'] ? 'Скрыть' : 'Показать' ?></button>
            <button class="btn btn-danger btn-sm" onclick="deleteTopic(<?= (int)$t['id'] ?>)">Удалить</button>
          </div>
        </div>
        <div class="topic-block-body" id="lessons-<?= (int)$t['id'] ?>">
          <p style="font-size:13px;color:var(--muted);margin:8px 0">Загрузка…</p>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$topics): ?>
      <div class="acard" style="text-align:center;padding:40px">
        <p style="color:var(--muted)">Тем пока нет. Создайте первую тему.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
</div>

<!-- Lesson edit modal -->
<div id="lessonModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--white);padding:32px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto">
    <h2 style="font-size:18px;margin-bottom:20px" id="lessonModalTitle">Урок</h2>
    <div id="modalAlert" class="alert" style="display:none"></div>
    <input type="hidden" id="lessonId">
    <input type="hidden" id="lessonTopicId">
    <div class="form-group">
      <label>Название урока *</label>
      <input type="text" id="lessonTitle" class="form-control" required>
    </div>
    <div class="form-group">
      <label>Kinescope ID *</label>
      <input type="text" id="lessonKinescopeId" class="form-control" placeholder="Вставьте UUID видео из Кинескопа">
      <p class="kinescope-hint">Только UUID, например: <code>abc12345-6789-def0</code></p>
    </div>
    <div class="form-group">
      <label>Длительность (минуты)</label>
      <input type="number" id="lessonDuration" class="form-control" min="1" max="300" placeholder="Например: 45" style="width:120px">
    </div>
    <div class="form-group">
      <label>Описание урока</label>
      <textarea id="lessonDescription" class="form-control" rows="5" placeholder="Текстовое описание урока, которое появится под видео…"></textarea>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button class="btn btn-ghost" onclick="closeLessonModal()">Отмена</button>
      <button class="btn btn-primary" id="saveLessonBtn" onclick="saveLesson()">Сохранить</button>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

function showAlert(msg, type = 'success') {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
  setTimeout(() => el.style.display = 'none', 4000);
}

function openNewTopicForm() {
  document.getElementById('newTopicForm').style.display = 'block';
  document.getElementById('newTopicTitle').focus();
}

async function createTopic() {
  const title = document.getElementById('newTopicTitle').value.trim();
  if (!title) return;
  const res = await apiPost('/api/admin/topics.php', 'POST', { title });
  if (res.ok) { location.reload(); }
  else showAlert('Ошибка создания темы.', 'error');
}

function toggleTopicBlock(el) {
  const wasOpen = el.classList.contains('open');
  el.classList.toggle('open');
  if (!wasOpen) loadLessons(el.dataset.id);
}

async function loadLessons(topicId) {
  const container = document.getElementById('lessons-' + topicId);
  const res = await fetch('/api/admin/lessons.php?topic_id=' + topicId);
  const data = await res.json();
  if (!data.ok) { container.innerHTML = '<p style="color:var(--danger)">Ошибка загрузки</p>'; return; }

  let html = data.lessons.map(l => `
    <div class="lesson-row" data-id="${l.id}">
      <span style="flex:1;font-size:14px">${escHtml(l.title)}</span>
      ${l.duration_min ? `<span style="font-size:12px;color:var(--muted)">${l.duration_min} мин</span>` : ''}
      ${!l.is_visible ? '<span class="badge badge-inactive" style="font-size:10px">скрыт</span>' : ''}
      <button class="btn btn-ghost btn-sm" onclick="openEditLesson(${l.id}, ${topicId})">Редактировать</button>
      <button class="btn btn-ghost btn-sm" onclick="toggleLessonVisible(${l.id}, ${l.is_visible ? 0 : 1}, ${topicId})">${l.is_visible ? 'Скрыть' : 'Показать'}</button>
      <button class="btn btn-danger btn-sm" onclick="deleteLesson(${l.id}, ${topicId})">Удалить</button>
    </div>
  `).join('');

  html += `<button class="btn btn-ghost btn-sm" style="margin-top:12px"
            onclick="openNewLesson(${topicId})">+ Добавить урок</button>`;
  container.innerHTML = html;
}

function openNewLesson(topicId) {
  document.getElementById('lessonModalTitle').textContent = 'Новый урок';
  document.getElementById('lessonId').value = '';
  document.getElementById('lessonTopicId').value = topicId;
  document.getElementById('lessonTitle').value = '';
  document.getElementById('lessonKinescopeId').value = '';
  document.getElementById('lessonDuration').value = '';
  document.getElementById('lessonDescription').value = '';
  document.getElementById('lessonModal').style.display = 'flex';
}

async function openEditLesson(lessonId, topicId) {
  const res = await fetch('/api/admin/lessons.php?topic_id=' + topicId);
  const data = await res.json();
  const lesson = data.lessons?.find(l => l.id == lessonId);
  if (!lesson) return;
  document.getElementById('lessonModalTitle').textContent = 'Редактировать урок';
  document.getElementById('lessonId').value = lessonId;
  document.getElementById('lessonTopicId').value = topicId;
  document.getElementById('lessonTitle').value = lesson.title;
  document.getElementById('lessonKinescopeId').value = lesson.kinescope_id;
  document.getElementById('lessonDuration').value = lesson.duration_min || '';
  document.getElementById('lessonDescription').value = lesson.description || '';
  document.getElementById('lessonModal').style.display = 'flex';
}

function closeLessonModal() {
  document.getElementById('lessonModal').style.display = 'none';
  document.getElementById('modalAlert').style.display = 'none';
}

async function saveLesson() {
  const id = parseInt(document.getElementById('lessonId').value);
  const topicId = parseInt(document.getElementById('lessonTopicId').value);
  const payload = {
    topic_id:     topicId,
    title:        document.getElementById('lessonTitle').value.trim(),
    kinescope_id: document.getElementById('lessonKinescopeId').value.trim(),
    duration_min: document.getElementById('lessonDuration').value || null,
    description:  document.getElementById('lessonDescription').value,
  };
  if (id) payload.id = id;

  const method = id ? 'PUT' : 'POST';
  const res = await apiPost('/api/admin/lessons.php', method, payload);
  if (res.ok) {
    closeLessonModal();
    loadLessons(topicId);
    showAlert(id ? 'Урок обновлён.' : 'Урок добавлен.');
  } else {
    const alertEl = document.getElementById('modalAlert');
    alertEl.className = 'alert alert-error';
    alertEl.textContent = res.error || 'Ошибка сохранения.';
    alertEl.style.display = 'block';
  }
}

async function toggleLessonVisible(lessonId, visible, topicId) {
  await apiPost('/api/admin/lessons.php', 'PUT', { id: lessonId, is_visible: visible });
  loadLessons(topicId);
}

async function deleteLesson(lessonId, topicId) {
  if (!confirm('Удалить урок?')) return;
  const res = await apiPost('/api/admin/lessons.php', 'DELETE', { id: lessonId });
  if (res.ok) loadLessons(topicId);
  else showAlert('Ошибка удаления.', 'error');
}

async function editTopic(id, currentTitle) {
  const title = prompt('Новое название темы:', currentTitle);
  if (!title || title.trim() === currentTitle) return;
  const res = await apiPost('/api/admin/topics.php', 'PUT', { id, title: title.trim() });
  if (res.ok) location.reload();
  else showAlert('Ошибка переименования.', 'error');
}

async function toggleTopicVisible(id, visible) {
  const res = await apiPost('/api/admin/topics.php', 'PUT', { id, is_visible: visible });
  if (res.ok) location.reload();
}

async function deleteTopic(id) {
  if (!confirm('Удалить тему и все её уроки?')) return;
  const res = await apiPost('/api/admin/topics.php', 'DELETE', { id });
  if (res.ok) location.reload();
  else showAlert('Ошибка удаления.', 'error');
}

async function apiPost(url, method, payload) {
  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload),
    });
    return await res.json();
  } catch { return { ok: false, error: 'network_error' }; }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modal on backdrop click
document.getElementById('lessonModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeLessonModal();
});
</script>
</body>
</html>
