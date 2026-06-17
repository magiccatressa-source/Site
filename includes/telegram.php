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

    @file_get_contents(
        'https://api.telegram.org/bot' . $botToken
        . '/sendMessage?chat_id=' . $chatId
        . '&text=' . urlencode($text)
        . '&parse_mode=HTML'
    );
}
