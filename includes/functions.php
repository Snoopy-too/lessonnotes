<?php
/**
 * Common helper functions
 */

/**
 * Get all classes
 */
function getClasses(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Get class by slug
 */
function getClassBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE slug = ?");
    $stmt->execute([$slug]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get class by ID
 */
function getClassById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get lessons for a class (sorted by date, newest first)
 */
function getLessonsForClass(PDO $pdo, int $classId): array {
    $stmt = $pdo->prepare("
        SELECT l.*, COUNT(t.id) as translation_count
        FROM lessons l
        LEFT JOIN translations t ON t.lesson_id = l.id
        WHERE l.class_id = ?
        GROUP BY l.id
        ORDER BY l.lesson_date DESC
    ");
    $stmt->execute([$classId]);
    return $stmt->fetchAll();
}

/**
 * Get lesson by ID
 */
function getLessonById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT l.*, c.class_name, c.slug as class_slug
        FROM lessons l
        JOIN classes c ON c.id = l.class_id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get lesson by class and date
 */
function getLessonByClassAndDate(PDO $pdo, int $classId, string $date): ?array {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE class_id = ? AND lesson_date = ?");
    $stmt->execute([$classId, $date]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Create or get lesson
 */
function getOrCreateLesson(PDO $pdo, int $classId, string $date): int {
    $existing = getLessonByClassAndDate($pdo, $classId, $date);

    if ($existing) {
        return $existing['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO lessons (class_id, lesson_date) VALUES (?, ?)");
    $stmt->execute([$classId, $date]);
    return (int) $pdo->lastInsertId();
}

/**
 * Get translations for a lesson
 */
function getTranslationsForLesson(PDO $pdo, int $lessonId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM translations
        WHERE lesson_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$lessonId]);
    return $stmt->fetchAll();
}

/**
 * Delete translations for a lesson (for re-upload)
 */
function deleteTranslationsForLesson(PDO $pdo, int $lessonId): void {
    $stmt = $pdo->prepare("DELETE FROM translations WHERE lesson_id = ?");
    $stmt->execute([$lessonId]);
}

/**
 * Format date for display
 */
function formatDate(string $date): string {
    $timestamp = strtotime($date);
    return date('F j, Y', $timestamp);
}

/**
 * Format date in Japanese style
 */
function formatDateJapanese(string $date): string {
    $timestamp = strtotime($date);
    return date('Y年n月j日', $timestamp);
}

/**
 * Escape HTML output
 */
function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Simple session-based admin authentication check
 */
function isAdminLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get admin password hash from database
 */
function getAdminPasswordHash(PDO $pdo): ?string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ?: null;
    } catch (PDOException $e) {
        // Table might not exist yet
        return null;
    }
}

/**
 * Set admin password (stores hashed)
 */
function setAdminPassword(PDO $pdo, string $password): bool {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('admin_password', ?)
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$hash, $hash]);
}

/**
 * Admin login - uses database password with fallback to config constant for migration
 */
function adminLogin(string $password): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $pdo = getDBConnection();
    $storedHash = getAdminPasswordHash($pdo);

    if ($storedHash !== null) {
        // Use database password (hashed)
        if (password_verify($password, $storedHash)) {
            $_SESSION['admin_logged_in'] = true;
            return true;
        }
    } else {
        // Migration: check config constant and migrate to database
        if ($password === ADMIN_PASSWORD) {
            // Try to migrate password to database with hashing (may fail if table doesn't exist yet)
            try {
                setAdminPassword($pdo, $password);
            } catch (PDOException $e) {
                // Table might not exist yet - that's OK, login still works
            }
            $_SESSION['admin_logged_in'] = true;
            return true;
        }
    }

    return false;
}

/**
 * Change admin password
 * @return bool|string true on success, string error message on failure
 */
function changeAdminPassword(string $currentPassword, string $newPassword, string $confirmPassword) {
    if (strlen($newPassword) < 8) {
        return 'New password must be at least 8 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        return 'New passwords do not match.';
    }

    $pdo = getDBConnection();
    $storedHash = getAdminPasswordHash($pdo);

    // Verify current password
    if ($storedHash !== null) {
        if (!password_verify($currentPassword, $storedHash)) {
            return 'Current password is incorrect.';
        }
    } else {
        // Check against config constant for unmigrated systems
        if ($currentPassword !== ADMIN_PASSWORD) {
            return 'Current password is incorrect.';
        }
    }

    // Set new password
    if (setAdminPassword($pdo, $newPassword)) {
        return true;
    }

    return 'Failed to update password. Please try again.';
}

/**
 * Admin logout
 */
function adminLogout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['admin_logged_in']);
    session_destroy();
}

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token hidden input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

/**
 * Validate CSRF token
 */
function validateCsrfToken(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Initialize secure session with strict settings
 */
function initSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session settings before starting
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        session_start();
    }
}

/**
 * Count total lessons for a class
 */
function countLessonsForClass(PDO $pdo, int $classId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE class_id = ?");
    $stmt->execute([$classId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get paginated lessons for a class
 */
function getPaginatedLessonsForClass(PDO $pdo, int $classId, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("
        SELECT l.*, COUNT(t.id) as translation_count
        FROM lessons l
        LEFT JOIN translations t ON t.lesson_id = l.id
        WHERE l.class_id = ?
        GROUP BY l.id
        ORDER BY l.lesson_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$classId, $perPage, $offset]);
    return $stmt->fetchAll();
}

/**
 * Count total translations for a lesson
 */
function countTranslationsForLesson(PDO $pdo, int $lessonId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM translations WHERE lesson_id = ?");
    $stmt->execute([$lessonId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get paginated translations for a lesson
 */
function getPaginatedTranslationsForLesson(PDO $pdo, int $lessonId, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("
        SELECT * FROM translations
        WHERE lesson_id = ?
        ORDER BY sort_order ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$lessonId, $perPage, $offset]);
    return $stmt->fetchAll();
}

/**
 * Calculate pagination info
 */
function getPaginationInfo(int $totalItems, int $currentPage, int $perPage): array {
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));

    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_page' => $currentPage - 1,
        'next_page' => $currentPage + 1,
        'start_item' => ($currentPage - 1) * $perPage + 1,
        'end_item' => min($currentPage * $perPage, $totalItems)
    ];
}
