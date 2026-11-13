<?php

declare(strict_types=1);

/*
 * Front controller: the only public PHP entry point.
 * Local run:  php -S localhost:8000 -t public public/index.php
 */

// Let PHP's built-in server serve real files in public/ (CSS, JS, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Response;
use App\Support\Session;

ini_set('display_errors', '0');
date_default_timezone_set('UTC');

// Security headers for every response.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

Session::start();

$router = new \Bramus\Router\Router();
(require dirname(__DIR__) . '/config/routes.php')($router);

try {
    $router->run();
} catch (Throwable $e) {
    // Log the class and location only: never passwords, tokens or request bodies.
    error_log(sprintf('[forum] %s at %s:%d', get_class($e), $e->getFile(), $e->getLine()));
    Response::error(500, 'Something went wrong', 'The server could not finish that request.');
}
