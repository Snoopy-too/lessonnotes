<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Security;

/**
 * Security helper for XSS defense and HTTP security headers.
 */
class SanitizeHelper
{
    /**
     * Escape HTML output strictly.
     */
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Send Content-Security-Policy and standard security response headers.
     */
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Content-Security-Policy
        $csp = "default-src 'self'; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "script-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data:; " .
               "connect-src 'self';";

        header("Content-Security-Policy: {$csp}");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
