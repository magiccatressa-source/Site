<?php
/**
 * Блок оплаты подписки.
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
  <p style="font-size:12px;color:var(--muted);margin-bottom:14px">Если договорились о другой сумме — переведите её.</p>

  <p style="font-size:14px;margin-bottom:6px">Переведите через СБП по номеру телефона:</p>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
    <span style="font-size:22px;font-weight:600;letter-spacing:1px">+7 912 588-08-26</span>
    <button class="btn btn-outline btn-sm" onclick="copyPayPhone(this)">Скопировать</button>
  </div>
  <p style="font-size:12px;color:var(--muted);margin-bottom:16px">Получатель: Любовь Николаевна Б. · <strong>Т-Банк</strong></p>

  <a class="pay-done-btn btn btn-primary btn-sm"
     href="https://t.me/indicatrisa?text=<?= urlencode('Любовь, привет! Подписка оплачена! ' . $user['name']) ?>"
     style="text-decoration:none">
    Оплачено ✓
  </a>
  <p style="font-size:11px;color:var(--muted);margin-top:6px">Кнопка откроет Telegram с готовым сообщением</p>
</div>
