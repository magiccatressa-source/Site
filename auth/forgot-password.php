<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
start_user_session();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Восстановление пароля — Клуб йоги</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <a href="/" class="auth-logo">Йога с Любовью</a>
    <h1 class="auth-title">Забыли пароль?</h1>
    <p class="auth-subtitle">Введите ваш email — пришлём ссылку для сброса пароля.</p>

    <div id="alert" class="alert" style="display:none"></div>

    <form id="forgotForm">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" id="submitBtn">Отправить ссылку</button>
      <p style="margin-top:16px; text-align:center; font-size:14px">
        <a href="/auth/login.php" style="color:var(--muted)">← Вернуться ко входу</a>
      </p>
    </form>
  </div>
</div>
<script>
const form = document.getElementById('forgotForm');
const alertEl = document.getElementById('alert');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.style.display = 'none';
  submitBtn.disabled = true;
  submitBtn.textContent = 'Отправляю…';

  try {
    const res = await fetch('/api/auth/forgot-password.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': form.querySelector('[name=csrf_token]').value,
      },
      body: JSON.stringify({ email: form.email.value.trim() }),
    });
    alertEl.className = 'alert alert-success';
    alertEl.textContent = 'Если аккаунт с таким email существует, мы отправили ссылку для сброса пароля.';
    alertEl.style.display = 'block';
    form.email.value = '';
  } catch {
    alertEl.className = 'alert alert-error';
    alertEl.textContent = 'Ошибка соединения. Попробуйте ещё раз.';
    alertEl.style.display = 'block';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Отправить ссылку';
  }
});
</script>
</body>
</html>
