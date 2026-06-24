<?php
$cur = basename($_SERVER['PHP_SELF']);
function nav_a(string $href, string $label, string $cur): string {
    $base = basename($href);
    $cls  = ($cur === $base) ? ' class="active"' : '';
    return '<a href="' . $href . '"' . $cls . '>' . $label . '</a>';
}
?>
<!-- Mobile top bar -->
<div class="admin-mobile-bar">
  <a href="/admin/" class="admin-mobile-logo">Йога с Любовью</a>
  <button class="admin-burger" id="adminBurger" aria-label="Меню">&#9776;</button>
</div>
<!-- Overlay -->
<div class="admin-sidebar-overlay" id="adminOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <a href="/admin/" class="sidebar-logo">Йога с Любовью</a>
  <nav class="sidebar-nav">
    <span class="nav-section">Обзор</span>
    <?= nav_a('/admin/', 'Дашборд', $cur) ?>
    <span class="nav-section">Пользователи</span>
    <?= nav_a('/admin/users.php', 'Все пользователи', $cur) ?>
    <span class="nav-section">Контент</span>
    <?= nav_a('/admin/content.php', 'Уроки и темы', $cur) ?>
    <span class="nav-section">Настройки</span>
    <?= nav_a('/admin/settings.php', 'Настройки сайта', $cur) ?>
    <?= nav_a('/admin/audit-log.php', 'Журнал действий', $cur) ?>
  </nav>
  <div class="sidebar-bottom">
    <a href="/cabinet/" style="color:rgba(255,255,255,.4);font-size:12px">← На сайт</a><br>
    <a href="#" id="adminLogout" style="color:rgba(255,255,255,.4);font-size:12px">Выйти</a>
  </div>
</aside>
<script>
document.getElementById('adminLogout')?.addEventListener('click', async (e) => {
  e.preventDefault();
  await fetch('/api/admin/logout.php', { method: 'POST' });
  window.location.href = '/admin/login.php';
});

(function() {
  const burger  = document.getElementById('adminBurger');
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('adminOverlay');
  function open()  { sidebar.classList.add('open'); overlay.classList.add('open'); }
  function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  burger?.addEventListener('click', open);
  overlay?.addEventListener('click', close);
})();
</script>
