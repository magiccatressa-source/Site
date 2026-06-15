<?php
/**
 * Блок оплаты подписки с двумя способами: Payform и по телефону.
 * Переменные должны быть определены до подключения:
 *   $user            — массив пользователя
 *   $isReturning     — bool, был ли ранее оплачен доступ
 *   $suggestedAmount — int, рекомендованная сумма
 *   $isActive        — bool, активна ли подписка сейчас
 */
?>
<div class="payment-block" style="margin-top:16px">
  <p style="font-size:14px;margin-bottom:4px">
    Сумма: <strong><?= $suggestedAmount ?> ₽</strong>
    <span style="font-size:13px;color:var(--muted)">
      <?= $isReturning ? '(постоянный клиент)' : '(первая подписка)' ?>
    </span>
  </p>

  <p style="font-size:14px;margin-bottom:10px;font-weight:500">Способ оплаты:</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
    <button class="btn btn-primary pay-tab" onclick="selectPaymentMethod('card', this)">
      Карта или СБП
    </button>
    <button class="btn btn-outline pay-tab" onclick="selectPaymentMethod('phone', this)">
      По номеру телефона
    </button>
  </div>

  <!-- Payform платёж -->
  <div class="pay-section pay-section--card">
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px">Оплата картой или через СБП</p>
    <a href="https://luchistaya-yoga.payform.ru?amount=<?= $suggestedAmount ?>"
       class="btn btn-primary"
       style="display:block;text-align:center;padding:14px;text-decoration:none"
       onclick="markPaymentPending()">
      Оплатить <?= $suggestedAmount ?> ₽ →
    </a>
  </div>

  <!-- Оплата по телефону -->
  <div class="pay-section pay-section--phone" style="display:none">
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
function selectPaymentMethod(method, clickedBtn) {
  const block = clickedBtn.closest('.payment-block');
  block.querySelectorAll('.pay-tab').forEach(btn => {
    btn.className = 'btn btn-outline pay-tab';
  });
  clickedBtn.className = 'btn btn-primary pay-tab';

  block.querySelectorAll('.pay-section').forEach(s => s.style.display = 'none');
  block.querySelector('.pay-section--' + method).style.display = 'block';
}

function markPaymentPending() {
  fetch('/api/payment/mark-pending.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_method: 'payform' })
  }).catch(err => console.error(err));
}
</script>
