<?php
/**
 * Блок оплаты подписки с двумя способами: Payform и по телефону.
 * Переменные должны быть определены до подключения:
 *   $user         — массив пользователя
 *   $isReturning  — bool, был ли ранее оплачен доступ
 *   $suggestedAmount — int, рекомендованная сумма
 *   $isActive     — bool, активна ли подписка сейчас
 */
?>
<div class="payment-block" style="margin-top:16px">
  <p style="font-size:14px;margin-bottom:4px">
    Сумма: <strong><?= $suggestedAmount ?> ₽</strong>
    <span style="font-size:13px;color:var(--muted)">
      <?= $isReturning ? '(постоянный клиент)' : '(первая подписка)' ?>
    </span>
  </p>

  <!-- Основной способ: Payform (карта/СБП) -->
  <div id="payform-payment" style="margin-bottom:20px">
    <p style="font-size:14px;margin-bottom:12px;font-weight:500">Способ оплаты:</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <button class="btn btn-primary" onclick="selectPaymentMethod('card')" id="btn-card" style="background:#1B1612">
        Карта или СБП
      </button>
      <button class="btn btn-outline" onclick="selectPaymentMethod('phone')" id="btn-phone" style="border-color:#C0A080;color:#3B312A">
        По номеру телефона
      </button>
    </div>

    <!-- Payform платёж -->
    <div id="card-payment" style="display:block">
      <p style="font-size:13px;color:var(--muted);margin-bottom:12px">
        Оплата картой или через СБП
      </p>
      <a href="https://luchistaya-yoga.payform.ru?amount=<?= $suggestedAmount ?>"
         class="btn btn-primary"
         style="display:block;text-align:center;padding:12px;text-decoration:none;width:100%"
         onclick="markPaymentPending()">
        Оплатить <?= $suggestedAmount ?> ₽ →
      </a>
    </div>
  </div>

  <!-- Альтернативный способ: по телефону -->
  <div id="phone-payment" style="display:none;margin-bottom:20px">
    <p style="font-size:14px;margin-bottom:6px">Переведите через СБП по номеру телефона:</p>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
      <span style="font-size:22px;font-weight:600;letter-spacing:1px">+7 912 588-08-26</span>
      <button class="btn btn-outline btn-sm" onclick="copyPayPhone(this)">Скопировать</button>
    </div>
    <p style="font-size:13px;font-weight:500;color:#C0532C;margin-bottom:16px">Получатель: Любовь Николаевна Б. · Т-Банк</p>

    <a class="pay-done-btn btn btn-primary btn-sm"
       href="https://t.me/indicatrisa?text=<?= urlencode('Любовь, привет! Подписка оплачена! ' . $user['name'] . '.') ?>"
       style="text-decoration:none;display:block;text-align:center;padding:12px">
      Нажми после оплаты
    </a>
    <p style="font-size:11px;color:var(--muted);margin-top:6px">Кнопка отправляет мне сообщение в ТГ, чтобы я активировала подписку</p>
  </div>

  <p style="font-size:11px;color:var(--muted);margin-top:12px;padding-top:12px;border-top:1px solid #E8DEC6">
    Если у вас возникли проблемы с платежом, напишите Любови в <a href="https://t.me/indicatrisa" target="_blank">Telegram</a>
  </p>
</div>

<script>
function selectPaymentMethod(method) {
  const cardBtn = document.getElementById('btn-card');
  const phoneBtn = document.getElementById('btn-phone');
  const cardPayment = document.getElementById('card-payment');
  const phonePayment = document.getElementById('phone-payment');

  if (method === 'card') {
    cardBtn.className = 'btn btn-primary';
    cardBtn.style.background = '#1B1612';
    phoneBtn.className = 'btn btn-outline';
    phoneBtn.style.borderColor = '#C0A080';
    phoneBtn.style.color = '#3B312A';
    cardPayment.style.display = 'block';
    phonePayment.style.display = 'none';
  } else {
    cardBtn.className = 'btn btn-outline';
    cardBtn.style.background = 'transparent';
    cardBtn.style.borderColor = '#C0A080';
    cardBtn.style.color = '#3B312A';
    phoneBtn.className = 'btn btn-primary';
    phoneBtn.style.background = '#1B1612';
    cardPayment.style.display = 'none';
    phonePayment.style.display = 'block';
  }
}

function markPaymentPending() {
  // Отмечаем платёж как "в ожидании" перед переходом на Payform
  fetch('/api/payment/mark-pending.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_method: 'payform' })
  }).catch(err => console.error(err)); // Ошибка не критична, перенаправим всё равно
}
</script>
