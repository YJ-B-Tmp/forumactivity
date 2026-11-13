<?php

declare(strict_types=1);

namespace App\Support;

use Mustache\Engine;
use Mustache\Loader\FilesystemLoader;

/**
 * Thin wrapper around Mustache. Every {{variable}} is HTML-escaped with
 * htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE). Templates never use
 * triple mustaches or {{& }}; layouts use template inheritance instead.
 */
final class View
{
    private static ?Engine $engine = null;

    private static function engine(): Engine
    {
        if (self::$engine === null) {
            $views = dirname(__DIR__) . '/Views';
            self::$engine = new Engine([
                'loader'           => new FilesystemLoader($views),
                'partials_loader'  => new FilesystemLoader($views),
                'escape'           => static fn ($value): string =>
                    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'strict_callables' => true,
            ]);
        }
        return self::$engine;
    }

    /** Adds data every page needs (current user, CSRF token, flashes). */
    public static function render(string $template, array $data = [], int $status = 200): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $shared = [
            'app_name'   => $config['name'],
            'logged_in'  => Session::isLoggedIn(),
            'username'   => Session::username(),
            'csrf_field' => Csrf::FIELD,
            'csrf_token' => Csrf::token(),
            'flashes'    => Session::takeFlashes(),
            'year'       => gmdate('Y'),
        ];

        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::engine()->render($template, array_merge($shared, $data));
    }
}
