<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$token = trim($_GET['token'] ?? '');
if (!$token) {
    header('Location: /auth/login.php?error=invalid_token');
    exit;
}

start_user_session();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Новый пароль — Клуб йоги</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <a href="/" class="auth-logo">Йога с Любовью</a>
    <h1 class="auth-title">Новый пароль</h1>

    <div id="alert" class="alert" style="display:none"></div>

    <form id="resetForm">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-group">
        <label for="password">Новый пароль</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <p class="form-hint">Минимум 8 символов</p>
      </div>
      <div class="form-group">
        <label for="password2">Повторите пароль</label>
        <input class="form-control" type="password" id="password2" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" id="submitBtn">Сохранить пароль</button>
    </form>
  </div>
</div>
<script>
const form = document.getElementById('resetForm');
const alertEl = document.getElementById('alert');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.style.display = 'none';

  if (form.password.value !== form.password2.value) {
    alertEl.className = 'alert alert-error';
    alertEl.textContent = 'Пароли не совпадают.';
    alertEl.style.display = 'block';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Сохраняю…';

  try {
    const res = await fetch('/api/auth/reset-password.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': form.querySelector('[name=csrf_token]').value,
      },
      body: JSON.stringify({
        token:    form.querySelector('[name=token]').value,
        password: form.password.value,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      form.style.display = 'none';
      alertEl.className = 'alert alert-success';
      alertEl.innerHTML = 'Пароль изменён! <a href="/auth/login.php">Войти</a>';
      alertEl.style.display = 'block';
    } else {
      alertEl.className = 'alert alert-error';
      alertEl.textContent = data.error === 'invalid_or_expired_token'
        ? 'Ссылка для сброса пароля устарела. Запросите новую.'
        : 'Ошибка. Попробуйте ещё раз.';
      alertEl.style.display = 'block';
    }
  } catch {
    alertEl.className = 'alert alert-error';
    alertEl.textContent = 'Ошибка соединения. Попробуйте ещё раз.';
    alertEl.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Сохранить пароль';
  }
});
</script>
</body>
</html>
