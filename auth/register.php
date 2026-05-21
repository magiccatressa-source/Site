<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

start_user_session();
if (!empty($_SESSION['user_id'])) {
    header('Location: /cabinet/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Регистрация — Клуб йоги Любовь Лучистая</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Spectral:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/cabinet.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-box" style="max-width:500px">
    <a href="/" class="auth-logo">Йога с Любовью</a>
    <h1 class="auth-title">Регистрация</h1>
    <p class="auth-subtitle">Уже есть аккаунт? <a href="/auth/login.php">Войти</a></p>

    <div id="alert" class="alert" style="display:none"></div>

    <form id="regForm">
      <?= csrf_field() ?>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
        <div class="form-group" style="margin-bottom:0">
          <label for="name">Имя <span style="color:var(--marsala)">*</span></label>
          <input class="form-control" type="text" id="name" name="name" required autocomplete="given-name" maxlength="100">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label for="last_name">Фамилия</label>
          <input class="form-control" type="text" id="last_name" name="last_name" autocomplete="family-name" maxlength="100">
        </div>
      </div>
      <div class="form-group" style="margin-top:16px">
        <label for="email">Email <span style="color:var(--marsala)">*</span></label>
        <input class="form-control" type="email" id="email" name="email" required autocomplete="email">
      </div>
      <div class="form-group">
        <label for="social_link">Ссылка на ВКонтакте или Telegram</label>
        <input class="form-control" type="url" id="social_link" name="social_link" placeholder="https://vk.com/... или https://t.me/...">
        <p class="form-hint">Необязательно — помогает мне вас найти</p>
      </div>
      <div class="form-group">
        <label for="password">Пароль <span style="color:var(--marsala)">*</span></label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
        <p class="form-hint">Минимум 8 символов</p>
      </div>
      <div class="form-group">
        <label for="password2">Повторите пароль <span style="color:var(--marsala)">*</span></label>
        <input class="form-control" type="password" id="password2" name="password2" required autocomplete="new-password">
      </div>

      <div class="form-check">
        <input type="checkbox" id="consent_pd" name="consent_pd" required>
        <label for="consent_pd">
          Я согласен(-на) с <a href="/privacy-policy.php" target="_blank">Политикой конфиденциальности</a> и даю согласие на обработку персональных данных <span style="color:var(--marsala)">*</span>
        </label>
      </div>
      <div class="form-check" style="margin-bottom:24px">
        <input type="checkbox" id="consent_offer" name="consent_offer" required>
        <label for="consent_offer">
          Я принимаю условия <a href="/public-offer.php" target="_blank">Публичной оферты</a> <span style="color:var(--marsala)">*</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%" id="submitBtn">Зарегистрироваться</button>
    </form>
  </div>
</div>

<script>
const form = document.getElementById('regForm');
const alertEl = document.getElementById('alert');
const submitBtn = document.getElementById('submitBtn');

const errors = {
  name_too_short:        'Имя слишком короткое (минимум 2 символа).',
  name_too_long:         'Имя слишком длинное.',
  invalid_email:         'Некорректный email.',
  password_too_short:    'Пароль слишком короткий (минимум 8 символов).',
  email_exists:          'Аккаунт с этим email уже существует.',
  consent_pd_required:   'Необходимо принять Политику конфиденциальности.',
  consent_offer_required:'Необходимо принять условия Публичной оферты.',
};

function showAlert(msg, type = 'error') {
  alertEl.className = 'alert alert-' + type;
  alertEl.innerHTML = msg;
  alertEl.style.display = 'block';
  alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.style.display = 'none';

  if (form.password.value !== form.password2.value) {
    showAlert('Пароли не совпадают.');
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Регистрирую…';

  try {
    const res = await fetch('/api/auth/register.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': form.querySelector('[name=csrf_token]').value,
      },
      body: JSON.stringify({
        name:          form.name.value.trim(),
        last_name:     form.last_name.value.trim(),
        email:         form.email.value.trim(),
        password:      form.password.value,
        social_link:   form.social_link.value.trim(),
        consent_pd:    form.consent_pd.checked,
        consent_offer: form.consent_offer.checked,
      }),
    });
    const data = await res.json();
    if (data.ok) {
      form.style.display = 'none';
      showAlert(
        '✓ Аккаунт создан! Мы отправили письмо с подтверждением на ваш email.<br>' +
        'Перейдите по ссылке в письме, чтобы завершить регистрацию.',
        'success'
      );
    } else {
      showAlert(errors[data.error] || 'Ошибка регистрации. Попробуйте ещё раз.');
    }
  } catch {
    showAlert('Ошибка соединения. Попробуйте ещё раз.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Зарегистрироваться';
  }
});
</script>
</body>
</html>
