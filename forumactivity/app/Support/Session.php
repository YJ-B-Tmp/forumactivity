<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session wrapper: hardened cookie settings, login state and flash messages.
 * Never logs session IDs.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('forum_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,   // Secure cookies whenever the site runs over HTTPS.
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function username(): ?string
    {
        $name = $_SESSION['username'] ?? null;
        return is_string($name) ? $name : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() !== null;
    }

    /** Called after a successful password check. */
    public static function logIn(int $userId, string $username): void
    {
        session_regenerate_id(true);   // prevents session fixation
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        Csrf::rotate();
    }

    public static function logOut(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
        session_destroy();
        // Start a fresh, empty session so the next page can still show a flash message.
        session_start();
        session_regenerate_id(true);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($flashes) ? $flashes : [];
    }

    public static function remember(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function pull(string $key): mixed
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }
}
