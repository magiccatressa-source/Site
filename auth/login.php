<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

start_user_session();
$next = $_GET['next'] ?? '';
if (!preg_match('#^/cabinet/#', $next)) $next = '';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($next ?: '/cabinet/'));
    exit;
}

$verified = isset($_GET['verified']);
$error    = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Клуб йоги Любовь Лучистая</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box">
    <a href="/" class="auth-logo">Йога с Любовью</a>
    <h1 class="auth-title">Вход в кабинет</h1>
    <p class="auth-subtitle">Нет аккаунта? <a href="/auth/register.php">Зарегистрироваться</a></p>

    <?php if ($verified): ?>
    <div class="alert alert-success">Email подтверждён — теперь вы можете войти.</div>
    <?php endif; ?>
    <?php if ($error === 'invalid_token'): ?>
    <div class="alert alert-error">Ссылка недействительна или устарела.</div>
    <?php endif; ?>

    <div id="alert" class="alert" style="display:none"></div>

    <form id="loginForm">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Пароль</label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div style="margin-bottom:20px; text-align:right">
        <a href="/auth/forgot-password.php" style="font-size:13px; color:var(--muted)">Забыли пароль?</a>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%" id="submitBtn">Войти</button>
    </form>
  </div>
</div>

<script>
const form = document.getElementById('loginForm');
const alert = document.getElementById('alert');
const submitBtn = document.getElementById('submitBtn');

const errors = {
  invalid_credentials: 'Неверный email или пароль.',
  email_not_verified: 'Сначала подтвердите email — письмо отправлено при регистрации.',
  too_many_attempts: 'Слишком много попыток входа. Попробуйте позже.',
  missing_fields: 'Заполните все поля.',
};

function showAlert(msg, type = 'error') {
  alert.className = 'alert alert-' + type;
  alert.textContent = msg;
  alert.style.display = 'block';
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alert.style.display = 'none';
  submitBtn.disabled = true;
  submitBtn.textContent = 'Вхожу…';

  try {
    const res = await fetch('/api/auth/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': form.querySelector('[name=csrf_token]').value,
      },
      body: JSON.stringify({
        email:    form.email.value.trim(),
        password: form.password.value,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = <?= json_encode($next ?: '/cabinet/') ?>;
    } else {
      showAlert(errors[data.error] || 'Ошибка входа. Попробуйте ещё раз.');
    }
  } catch {
    showAlert('Ошибка соединения. Попробуйте ещё раз.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Войти';
  }
});
</script>
</body>
</html>
