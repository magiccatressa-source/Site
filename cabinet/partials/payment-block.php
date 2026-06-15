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
  <p style="font-size:14px;margin-bottom:14px">
    Сумма: <strong><?= $suggestedAmount ?> ₽</strong>
    <span style="font-size:13px;color:var(--muted)">
      <?= $isReturning ? '(постоянный клиент)' : '(первая подписка)' ?>
    </span>
  </p>

  <p style="font-size:13px;font-weight:500;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.08em">Как оплатить?</p>

  <label class="pay-choice" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:8px;padding:12px 14px;border:1px solid var(--ink);background:var(--cream)">
    <input type="radio" name="pay-method" value="phone" checked style="margin-top:3px;accent-color:var(--ink);flex-shrink:0">
    <span style="font-size:14px;line-height:1.4">Переведу по номеру телефона на Т-Банк</span>
  </label>

  <label class="pay-choice" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:16px;padding:12px 14px;border:1px solid var(--cream-deep);background:transparent">
    <input type="radio" name="pay-method" value="card" style="margin-top:3px;accent-color:var(--ink);flex-shrink:0">
    <span style="font-size:14px;line-height:1.4">Я новый клиент, хочу оплатить картой или СБП</span>
  </label>

  <!-- Оплата по телефону (по умолчанию) -->
  <div class="pay-section pay-section--phone">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
      <span style="font-size:22px;font-weight:600;letter-spacing:1px">+7 912 588-08-26</span>
      <button class="btn btn-outline btn-sm" onclick="copyPayPhone(this)">Скопировать</button>
    </div>
    <p style="font-size:13px;font-weight:500;color:#C0532C;margin-bottom:16px">Получатель: Любовь Николаевна Б. · Т-Банк</p>
    <a class="btn btn-primary"
       href="https://t.me/indicatrisa?text=<?= urlencode('Любовь, привет! Подписка оплачена! ' . $user['name'] . '.') ?>"
       style="text-decoration:none;display:block;text-align:center;padding:12px">
      Написать Любови после оплаты →
    </a>
    <p style="font-size:11px;color:var(--muted);margin-top:6px">Я активирую подписку вручную, обычно в течение нескольких часов</p>
  </div>

  <!-- Payform -->
  <div class="pay-section pay-section--card" style="display:none">
    <a href="https://luchistaya-yoga.payform.ru?amount=<?= $suggestedAmount ?>"
       class="btn btn-primary"
       style="display:block;text-align:center;padding:14px;text-decoration:none"
       onclick="markPaymentPending()">
      Оплатить <?= $suggestedAmount ?> ₽ →
    </a>
    <p style="font-size:11px;color:var(--muted);margin-top:6px">После оплаты подписка активируется автоматически</p>
  </div>

  <p style="font-size:11px;color:var(--muted);margin-top:14px;padding-top:12px;border-top:1px solid #E8DEC6">
    Если что-то пошло не так — напишите Любови в <a href="https://t.me/indicatrisa" target="_blank">Telegram</a>
  </p>
</div>

<script>
(function() {
  var blocks = document.querySelectorAll('.payment-block');
  blocks.forEach(function(block) {
    var radios = block.querySelectorAll('input[type=radio]');
    radios.forEach(function(radio) {
      radio.addEventListener('change', function() {
        // Переключаем секции
        block.querySelectorAll('.pay-section').forEach(function(s) { s.style.display = 'none'; });
        block.querySelector('.pay-section--' + this.value).style.display = 'block';

        // Подсвечиваем выбранный label
        block.querySelectorAll('.pay-choice').forEach(function(l) {
          l.style.borderColor = 'var(--cream-deep)';
          l.style.background = 'transparent';
        });
        this.closest('.pay-choice').style.borderColor = 'var(--ink)';
        this.closest('.pay-choice').style.background = 'var(--cream)';
      });
    });
  });
})();

function markPaymentPending() {
  fetch('/api/payment/mark-pending.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ payment_method: 'payform' })
  }).catch(function(err) { console.error(err); });
}
</script>
