<?php
$cur = basename($_SERVER['PHP_SELF']);
function nav_a(string $href, string $label, string $cur): string {
    $base = basename($href);
    $cls  = ($cur === $base) ? ' class="active"' : '';
    return '<a href="' . $href . '"' . $cls . '>' . $label . '</a>';
}
?>
<aside class="admin-sidebar">
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
</script>
