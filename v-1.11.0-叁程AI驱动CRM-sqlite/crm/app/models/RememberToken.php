<?php

/**
 * Remember-me token management for persistent login.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
class RememberToken extends Model
{
    protected string $table = 'remember_tokens';

    /** Default cookie lifetime: 30 days */
    public const COOKIE_LIFETIME = 30 * 24 * 60 * 60; // 30 days in seconds

    /** Cookie name for remember-me token */
    public const COOKIE_NAME = 'crm_remember_token';

    /**
     * Create a new remember token for a user.
     *
     * @param int    $userId   User ID
     * @param int    $days     Token validity in days (default 30)
     * @return string          The plain token (to be stored in cookie)
     */
    public function createToken(int $userId, int $days = 30): string
    {
        // Clean up expired tokens for this user
        $this->cleanExpired($userId);

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 24 * 60 * 60));

        $this->create([
            'user_id'    => $userId,
            'token'      => hash('sha256', $token), // Store hashed version
            'expires_at' => $expiresAt,
        ]);

        return $token; // Return plain token for cookie
    }

    /**
     * Validate a remember token and return the associated user ID.
     *
     * @param string $token Plain token from cookie
     * @return int|null     User ID if valid, null otherwise
     */
    public function validateToken(string $token): ?int
    {
        if (empty($token)) {
            return null;
        }

        $hashedToken = hash('sha256', $token);

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT user_id FROM remember_tokens 
                 WHERE token = :token AND expires_at > datetime(\'now\')
                 LIMIT 1'
            );
            $stmt->bindValue(':token', $hashedToken);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? (int) $row['user_id'] : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Delete a specific token (used on logout).
     *
     * @param string $token Plain token from cookie
     */
    public function deleteToken(string $token): void
    {
        if (empty($token)) {
            return;
        }

        $hashedToken = hash('sha256', $token);

        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM remember_tokens WHERE token = :token');
            $stmt->bindValue(':token', $hashedToken);
            $stmt->execute();
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Delete all tokens for a user (used on password change, etc.).
     *
     * @param int $userId User ID
     */
    public function deleteAllForUser(int $userId): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Clean up expired tokens for a user (or globally).
     *
     * @param int|null $userId Optional user ID to limit cleanup
     */
    public function cleanExpired(?int $userId = null): void
    {
        try {
            $db = Database::connection();
            if ($userId !== null) {
                $stmt = $db->prepare(
                    'DELETE FROM remember_tokens WHERE user_id = :user_id AND expires_at < datetime(\'now\')'
                );
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            } else {
                $stmt = $db->prepare('DELETE FROM remember_tokens WHERE expires_at < datetime(\'now\')');
            }
            $stmt->execute();
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Set the remember-me cookie.
     *
     * @param string $token Plain token
     */
    public function setCookie(string $token): void
    {
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires'  => time() + self::COOKIE_LIFETIME,
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]
        );
    }

    /**
     * Delete the remember-me cookie.
     */
    public function deleteCookie(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]
        );
        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
