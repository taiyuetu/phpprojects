<?php
namespace App\Core;

use App\Models\User;

/**
 * Auth — tiny session-based authentication helper.
 * Kept separate from the User model so "who is logged in" logic
 * lives in one obvious place.
 */
class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findBy('email', $email);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            return true;
        }

        return false;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . Router::url('/login'));
            exit;
        }
    }
}
