<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CommentController;
use App\Controllers\DiscussionController;
use App\Support\Response;
use Bramus\Router\Router;

/**
 * Route table. Route parameters are matched loosely ([^/]+) on purpose so
 * the controller can tell a malformed ID (400) from a missing one (404).
 */
return static function (Router $router): void {
    $router->get('/', [new DiscussionController(), 'index']);
    $router->get('/discussions/([^/]+)', [new DiscussionController(), 'show']);
    $router->post('/discussions/([^/]+)/comments', [new CommentController(), 'store']);

    $router->get('/register', [new AuthController(), 'showRegister']);
    $router->post('/register', [new AuthController(), 'register']);
    $router->get('/login', [new AuthController(), 'showLogin']);
    $router->post('/login', [new AuthController(), 'login']);
    $router->post('/logout', [new AuthController(), 'logout']);

    $router->set404(static function (): void {
        Response::error(404, 'Page not found', 'That page does not exist.');
    });
};
