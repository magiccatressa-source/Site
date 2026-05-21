<?php
require_once __DIR__ . '/config.php';

function send_email(string $to, string $subject, string $htmlBody): bool {
    // PHPMailer via composer or fallback to mail()
    // On Beget: install PHPMailer via composer or upload manually to /vendor/
    // For now, using PHP mail() as fallback (works with Beget)
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
}

function email_wrapper(string $title, string $content): string {
    return '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;padding:0;background:#F2EAD8;font-family:Georgia,serif;color:#1B1612}
.wrap{max-width:560px;margin:40px auto;background:#fff;padding:40px 48px}
h1{font-size:26px;font-weight:400;margin:0 0 24px;color:#1B1612}
p{line-height:1.7;margin:0 0 16px;font-size:16px}
.btn{display:inline-block;background:#9B3A4A;color:#F2EAD8;padding:14px 28px;
     text-decoration:none;font-size:15px;margin:8px 0 24px}
.footer{margin-top:32px;padding-top:20px;border-top:1px solid #e6dcc6;
        font-size:13px;color:#7a6a60}
</style></head><body>
<div class="wrap">
<p style="font-size:13px;color:#9B3A4A;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px">Клуб йоги · Любовь Лучистая</p>
<h1>' . $title . '</h1>
' . $content . '
<div class="footer">Это письмо отправлено автоматически, не отвечайте на него.<br>
<a href="' . APP_URL . '" style="color:#9B3A4A">' . APP_URL . '</a></div>
</div></body></html>';
}

function send_verify_email(string $toEmail, string $toName, string $token): bool {
    $link = APP_URL . '/api/auth/verify-email.php?token=' . urlencode($token);
    $body = email_wrapper(
        'Подтвердите ваш email',
        '<p>Привет, ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . '!</p>
         <p>Для завершения регистрации нажмите на кнопку ниже. Ссылка действительна 24 часа.</p>
         <a class="btn" href="' . $link . '">Подтвердить email</a>
         <p>Если вы не регистрировались — просто проигнорируйте это письмо.</p>'
    );
    return send_email($toEmail, 'Подтверждение регистрации в клубе йоги', $body);
}

function send_reset_email(string $toEmail, string $toName, string $token): bool {
    $link = APP_URL . '/auth/reset-password.php?token=' . urlencode($token);
    $body = email_wrapper(
        'Сброс пароля',
        '<p>Привет, ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . '!</p>
         <p>Для сброса пароля нажмите на кнопку ниже. Ссылка действительна 1 час.</p>
         <a class="btn" href="' . $link . '">Создать новый пароль</a>
         <p>Если вы не запрашивали сброс пароля — просто проигнорируйте это письмо.</p>'
    );
    return send_email($toEmail, 'Сброс пароля — клуб йоги', $body);
}
