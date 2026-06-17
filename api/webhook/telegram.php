<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true);
$chatId = $body['message']['chat']['id'] ?? null;
$text   = trim($body['message']['text'] ?? '');

if (!$chatId) { echo '{}'; exit; }

// /start TOKEN — привязка аккаунта
if (str_starts_with($text, '/start ')) {
    $token = trim(substr($text, 7));
    if ($token) {
        $stmt = db()->prepare('SELECT id FROM users WHERE telegram_link_token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            db()->prepare('UPDATE users SET telegram_chat_id = ?, telegram_link_token = NULL WHERE id = ?')
                ->execute([$chatId, $user['id']]);
            send_tg_message($chatId, "Готово! Теперь когда вы нажмёте «Я был на эфире» — прогресс запишется автоматически ✅");
        } else {
            send_tg_message($chatId, "Ссылка устарела или уже использована. Откройте профиль на сайте и нажмите «Привязать Telegram» снова.");
        }
    }
    echo '{}'; exit;
}

echo '{}';

function send_tg_message(int $chatId, string $text): void
{
    $token = setting('telegram_bot_token');
    if (!$token) return;
    @file_get_contents(
        'https://api.telegram.org/bot' . $token
        . '/sendMessage?chat_id=' . $chatId
        . '&text=' . urlencode($text)
    );
}
