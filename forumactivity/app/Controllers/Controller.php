<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Csrf;
use App\Support\Response;

abstract class Controller
{
    protected array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
    }

    /**
     * Route parameters arrive as strings. A valid ID is a positive integer
     * written with digits only (no sign, spaces, decimals or leading zeros).
     */
    protected function parseId(string $raw): ?int
    {
        if (!preg_match('/^[1-9][0-9]{0,9}$/', $raw)) {
            return null;
        }
        $id = (int) $raw;
        return $id <= 4294967295 ? $id : null; // INT UNSIGNED range
    }

    /** Sends 403 and returns false when the CSRF token is missing or wrong. */
    protected function checkCsrf(): bool
    {
        if (Csrf::isValid($_POST[Csrf::FIELD] ?? null)) {
            return true;
        }
        Response::error(403, 'Request blocked', 'The security token on that form was missing or out of date. Go back, reload the page and try again.');
        return false;
    }

    protected function input(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    protected static function formatUtc(string $timestamp): array
    {
        $time = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));
        return [
            'iso'     => $time->format('Y-m-d\TH:i:s\Z'),
            'display' => $time->format('j M Y, H:i') . ' UTC',
        ];
    }
}
