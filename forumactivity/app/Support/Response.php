<?php

declare(strict_types=1);

namespace App\Support;

final class Response
{
    /** 303 See Other: the browser follows with GET (Post/Redirect/Get). */
    public static function redirect(string $path): void
    {
        // Only local paths are ever redirected to.
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $path = '/';
        }
        header('Location: ' . $path, true, 303);
    }

    public static function error(int $status, string $title, string $message): void
    {
        View::render('errors/error', [
            'page_title' => $title,
            'status'     => $status,
            'title'      => $title,
            'message'    => $message,
        ], $status);
    }
}
