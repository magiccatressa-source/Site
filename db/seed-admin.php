<?php
/**
 * Скрипт создания администратора.
 * Запустить ОДИН РАЗ на сервере через SSH или Beget SSH-консоль:
 *
 *   php /home/focuspkp/luchistaya-yoga.ru/public_html/db/seed-admin.php
 *
 * После выполнения УДАЛИТЕ этот файл с сервера!
 */

require_once __DIR__ . '/../includes/db.php';

$email    = 'admin@lubov-yoga.ru';
$password = 'Yoga2026!Luchistaya#';
$name     = 'Любовь';

// Check if admin already exists
$s = db()->prepare('SELECT id FROM users WHERE email = ?');
$s->execute([$email]);
if ($s->fetch()) {
    echo "Администратор с email $email уже существует.\n";
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

db()->prepare(
    'INSERT INTO users (name, email, password_hash, role, email_verified, consent_pd, consent_at)
     VALUES (?, ?, ?, "admin", 1, 1, NOW())'
)->execute([$name, $email, $hash]);

echo "✓ Администратор создан.\n";
echo "  Логин:  $email\n";
echo "  Пароль: $password\n";
echo "\nУДАЛИТЕ ЭТОТ ФАЙЛ С СЕРВЕРА!\n";
echo "  rm /home/focuspkp/luchistaya-yoga.ru/public_html/db/seed-admin.php\n";
