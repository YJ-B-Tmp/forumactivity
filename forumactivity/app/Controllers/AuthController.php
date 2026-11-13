<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class AuthController extends Controller
{
    /** password_verify() runs against this when the user is missing, so timing reveals nothing. */
    private const DUMMY_HASH = '$2y$10$s7lciWA85WcR6xrICja87eS9.tbitTO634J5njg6bYw55pRlw83t2';

    public function showRegister(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/');
            return;
        }
        View::render('auth/register', ['page_title' => 'Create an account']);
    }

    public function register(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $username = trim($this->input('username'));
        $email = trim($this->input('email'));
        $password = $this->input('password');
        $confirm = $this->input('password_confirm');
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3–30 characters: letters, numbers or underscores.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            // bcrypt only reads the first 72 bytes, so longer passwords are refused.
            $errors[] = 'Password must be 8–72 characters long.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors[] = 'The two passwords do not match.';
        }

        $users = new User();
        if ($errors === []) {
            $taken = $users->usernameOrEmailTaken($username, $email);
            if ($taken['username']) {
                $errors[] = 'That username is already taken.';
            }
            if ($taken['email']) {
                $errors[] = 'An account with that email already exists.';
            }
        }

        $id = null;
        if ($errors === []) {
            $id = $users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
            if ($id === null) {
                $errors[] = 'That username or email is already registered.';
            }
        }

        if ($errors !== []) {
            View::render('auth/register', [
                'page_title' => 'Create an account',
                'errors'     => $errors,
                'old'        => ['username' => $username, 'email' => $email],
            ], 422);
            return;
        }

        Session::logIn($id, $username);
        Session::flash('success', "Welcome, {$username}! Your account is ready.");
        Response::redirect(Session::pull('return_to') ?? '/');
    }

    public function showLogin(): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/');
            return;
        }
        View::render('auth/login', ['page_title' => 'Log in']);
    }

    public function login(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $username = trim($this->input('username'));
        $password = $this->input('password');
        $user = $username === '' ? null : (new User())->findForLogin($username);
        $hash = $user['password_hash'] ?? null;

        // A missing user or a NULL hash can never authenticate.
        $ok = is_string($hash) && $hash !== ''
            ? password_verify($password, $hash)
            : (password_verify($password, self::DUMMY_HASH) && false);

        if (!$ok) {
            View::render('auth/login', [
                'page_title' => 'Log in',
                'errors'     => ['Incorrect username or password.'],
                'old'        => ['username' => $username],
            ], 401);
            return;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            (new User())->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        Session::logIn((int) $user['id'], $user['username']);
        Session::flash('success', "Signed in as {$user['username']}.");
        Response::redirect(Session::pull('return_to') ?? '/');
    }

    public function logout(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }
        Session::logOut();
        Session::flash('success', 'You have logged out.');
        Response::redirect('/');
    }
}
