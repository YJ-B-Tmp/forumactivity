<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session-backed synchronizer token, required on every state-changing form.
 */
final class Csrf
{
    private const KEY = '_csrf_token';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        if (!isset($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    public static function isValid(mixed $submitted): bool
    {
        $expected = $_SESSION[self::KEY] ?? null;
        return is_string($expected)
            && is_string($submitted)
            && $submitted !== ''
            && hash_equals($expected, $submitted);
    }
}
