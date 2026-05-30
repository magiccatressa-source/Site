<?php
/**
 * Скрипт создания администратора.
 * Запустить ОДИН РАЗ на сервере через SSH:
 *
 *   php /home/focuspkp/luchistaya-yoga.ru/public_html/db/seed-admin.php ВАШ_ПАРОЛЬ
 *
 * После выполнения УДАЛИТЕ этот файл с сервера:
 *   rm /home/focuspkp/luchistaya-yoga.ru/public_html/db/seed-admin.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

if ($argc < 2 || strlen(trim($argv[1])) < 10) {
    fwrite(STDERR, "Использование: php seed-admin.php ВАШ_ПАРОЛЬ\n");
    fwrite(STDERR, "Пароль должен быть не короче 10 символов.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';

$email    = 'admin@lubov-yoga.ru';
$password = trim($argv[1]);
$name     = 'Любовь';

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
echo "  Логин: $email\n";
echo "\nУДАЛИТЕ ЭТОТ ФАЙЛ С СЕРВЕРА:\n";
echo "  rm /home/focuspkp/luchistaya-yoga.ru/public_html/db/seed-admin.php\n";
