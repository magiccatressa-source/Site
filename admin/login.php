<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

start_admin_session();
if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Администратор</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
  <div class="admin-login-box">
    <h1>Панель управления</h1>
    <p style="font-size:13px;color:var(--muted);margin-bottom:24px">Клуб йоги · Любовь Лучистая</p>

    <div id="alert" class="alert" style="display:none"></div>

    <form id="loginForm">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Email</label>
        <input class="form-control" type="email" id="email" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>Пароль</label>
        <input class="form-control" type="password" id="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" id="submitBtn">Войти</button>
    </form>
  </div>
</div>
<script>
const form = document.getElementById('loginForm');
const alertEl = document.getElementById('alert');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.style.display = 'none';
  submitBtn.disabled = true;

  try {
    const res = await fetch('/api/admin/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': form.querySelector('[name=csrf_token]').value,
      },
      body: JSON.stringify({
        email:    document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = '/admin/';
    } else {
      alertEl.className = 'alert alert-error';
      alertEl.textContent = 'Неверный email или пароль.';
      alertEl.style.display = 'block';
    }
  } catch {
    alertEl.className = 'alert alert-error';
    alertEl.textContent = 'Ошибка соединения.';
    alertEl.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
  }
});
</script>
</body>
</html>
