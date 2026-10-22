<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Security;

use PDO;
use PDOException;

/**
 * Authentication and Session Guard for Teacher Access.
 */
class AuthGuard
{
    private const SESSION_KEY = 'admin_logged_in';
    private const CSRF_KEY = 'csrf_token';

    public function __construct(
        private PDO $pdo
    ) {
        $this->initSession();
    }

    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
        }
    }

    public function isLoggedIn(): bool
    {
        $this->initSession();
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }

    public function login(string $password): bool
    {
        $this->initSession();
        $storedHash = $this->getStoredPasswordHash();

        if ($storedHash !== null) {
            if (password_verify($password, $storedHash)) {
                // Rehash if newer algorithm/cost available
                if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                    $this->setStoredPassword($password);
                }
                session_regenerate_id(true);
                $_SESSION[self::SESSION_KEY] = true;
                return true;
            }
        } else {
            // Fallback for unseeded setups
            $fallback = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'sensei2024';
            if ($password === $fallback) {
                try {
                    $this->setStoredPassword($password);
                } catch (PDOException) {
                    // Ignore if settings table unready
                }
                session_regenerate_id(true);
                $_SESSION[self::SESSION_KEY] = true;
                return true;
            }
        }

        return false;
    }

    public function logout(): void
    {
        $this->initSession();
        unset($_SESSION[self::SESSION_KEY]);
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * @return true|string Error string on failure, true on success
     */
    public function changePassword(string $currentPassword, string $newPassword, string $confirmPassword): bool|string
    {
        if (strlen($newPassword) < 8) {
            return 'New password must be at least 8 characters long.';
        }

        if ($newPassword !== $confirmPassword) {
            return 'New passwords do not match.';
        }

        $storedHash = $this->getStoredPasswordHash();
        if ($storedHash !== null) {
            if (!password_verify($currentPassword, $storedHash)) {
                return 'Current password is incorrect.';
            }
        } else {
            $fallback = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : 'sensei2024';
            if ($currentPassword !== $fallback) {
                return 'Current password is incorrect.';
            }
        }

        if ($this->setStoredPassword($newPassword)) {
            return true;
        }

        return 'Failed to update password. Please try again.';
    }

    public function generateCsrfToken(): string
    {
        $this->initSession();
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public function getCsrfField(): string
    {
        $token = SanitizeHelper::escape($this->generateCsrfToken());
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public function validateCsrfToken(?string $submittedToken = null): bool
    {
        $this->initSession();
        $token = $submittedToken ?? ($_POST['csrf_token'] ?? '');
        $sessionToken = $_SESSION[self::CSRF_KEY] ?? '';

        if ($token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public function requireLogin(string $redirectUrl = 'index.php'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    private function getStoredPasswordHash(): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'admin_password'");
            $stmt->execute();
            $result = $stmt->fetchColumn();
            return is_string($result) ? $result : null;
        } catch (PDOException) {
            return null;
        }
    }

    private function setStoredPassword(string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO `settings` (`setting_key`, `setting_value`)
            VALUES ('admin_password', :val1)
            ON DUPLICATE KEY UPDATE `setting_value` = :val2, `updated_at` = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([':val1' => $hash, ':val2' => $hash]);
    }
}
