<?php

function notify_new_lesson(int $lessonId, string $title, int $isLive = 0, ?string $liveDate = null): void
{
    $botToken = setting('telegram_bot_token');
    $chatId   = setting('telegram_lessons_chat_id');
    if (!$botToken || !$chatId) return;

    $url       = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'luchistaya-yoga.ru') . '/cabinet/lesson.php?id=' . $lessonId;
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    if ($isLive && $liveDate) {
        $months = ['', 'января','февраля','марта','апреля','мая','июня',
                       'июля','августа','сентября','октября','ноября','декабря'];
        $dt     = new DateTime($liveDate);
        $dateRu = $dt->format('j') . ' ' . $months[(int)$dt->format('n')];
        $text = "Загружен эфир от " . $dateRu . " — <b>«" . $safeTitle . "»</b>\n\n<a href=\"" . $url . "\">→ Смотреть запись</a>";
    } else {
        $text = "Новый урок — <b>«" . $safeTitle . "»</b>\n\n<a href=\"" . $url . "\">→ Смотреть запись</a>";
    }

    $params = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];

    if ($isLive) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => [[
                ['text' => 'Засчитать присутствие', 'callback_data' => 'attended:' . $lessonId],
            ]]
        ]);
    }

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($params),
    ]]);
    @file_get_contents('https://api.telegram.org/bot' . $botToken . '/sendMessage', false, $ctx);
}
