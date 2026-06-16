<?php

function notify_new_lesson(int $lessonId, string $title): void
{
    $botToken = setting('telegram_bot_token');
    $chatId   = setting('telegram_lessons_chat_id');
    if (!$botToken || !$chatId) return;

    $url       = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'luchistaya-yoga.ru') . '/cabinet/lesson.php?id=' . $lessonId;
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $text      = "Новый урок доступен для просмотра\n\n<b><a href=\"" . $url . "\">" . $safeTitle . "</a></b>";

    @file_get_contents(
        'https://api.telegram.org/bot' . $botToken
        . '/sendMessage?chat_id=' . $chatId
        . '&text=' . urlencode($text)
        . '&parse_mode=HTML'
    );
}
