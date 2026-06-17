<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);

// Обработка нажатия inline-кнопки
if (!empty($body['callback_query'])) {
    $cq     = $body['callback_query'];
    $fromId = $cq['from']['id'] ?? null;
    $data   = $cq['data'] ?? '';
    $cqId   = $cq['id'];

    if ($fromId && str_starts_with($data, 'attended:')) {
        $lessonId = (int)substr($data, 9);

        $stmt = db()->prepare('SELECT id FROM users WHERE telegram_chat_id = ?');
        $stmt->execute([$fromId]);
        $user = $stmt->fetch();

        if ($user) {
            $already = db()->prepare('SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
            $already->execute([$user['id'], $lessonId]);

            if ($already->fetch()) {
                answer_callback($cqId, 'Вы уже отмечены на этом эфире ✅');
            } else {
                db()->prepare(
                    'INSERT IGNORE INTO lesson_progress (user_id, lesson_id, completed) VALUES (?, ?, 1)'
                )->execute([$user['id'], $lessonId]);
                answer_callback($cqId, 'Готово! Эфир засчитан в ваш прогресс ✅');
            }
        } else {
            answer_callback($cqId, 'Привяжите Telegram в личном кабинете, чтобы отмечать эфиры.');
        }
    }

    echo '{}'; exit;
}

// Обработка текстовых сообщений
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

function answer_callback(string $cqId, string $text): void
{
    $token = setting('telegram_bot_token');
    if (!$token) return;
    @file_get_contents(
        'https://api.telegram.org/bot' . $token
        . '/answerCallbackQuery?callback_query_id=' . $cqId
        . '&text=' . urlencode($text)
        . '&show_alert=true'
    );
}
