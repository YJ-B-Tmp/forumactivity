<?php

declare(strict_types=1);

/*
 * Database settings. Never commit real credentials.
 * Override locally by copying config/database.local.example.php to
 * config/database.local.php (git-ignored), or by setting environment variables.
 */
$settings = [
    'dsn'      => getenv('FORUM_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=nested_forum;charset=utf8mb4',
    'username' => getenv('FORUM_DB_USER') ?: 'forum_app',
    'password' => getenv('FORUM_DB_PASS') ?: '',
];

$local = __DIR__ . '/database.local.php';
if (is_file($local)) {
    $settings = array_merge($settings, require $local);
}

return $settings;
