<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/** Data access for the users table. All SQL uses bound parameters. */
final class User
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /** @return array{id:int,username:string,password_hash:?string}|null */
    public function findForLogin(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function usernameOrEmailTaken(string $username, string $email): array
    {
        $stmt = $this->db->prepare(
            'SELECT username = :u AS same_username, email = :e AS same_email
               FROM users WHERE username = :u2 OR email = :e2'
        );
        $stmt->execute(['u' => $username, 'e' => $email, 'u2' => $username, 'e2' => $email]);
        $taken = ['username' => false, 'email' => false];
        foreach ($stmt->fetchAll() as $row) {
            $taken['username'] = $taken['username'] || (bool) $row['same_username'];
            $taken['email'] = $taken['email'] || (bool) $row['same_email'];
        }
        return $taken;
    }

    /**
     * Inserts a new account with a password_hash() value.
     * Returns null when a unique key rejects a duplicate (race-safe).
     */
    public function create(string $username, string $email, string $passwordHash): ?int
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :hash)'
            );
            $stmt->execute(['username' => $username, 'email' => $email, 'hash' => $passwordHash]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }
}
