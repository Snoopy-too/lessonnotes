<?php
declare(strict_types=1);

/**
 * Database Initialization Script
 * Hexagonal Architecture Schema Setup
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

try {
    // 1. lesson_classes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `lesson_classes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `schedule_day` VARCHAR(20) DEFAULT NULL,
            `schedule_time` VARCHAR(20) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created or verified lesson_classes table.<br>";

    // 2. lessons table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `lessons` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `class_id` INT UNSIGNED NOT NULL,
            `lesson_date` DATE NOT NULL,
            `raw_text` MEDIUMTEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_lessons_class` FOREIGN KEY (`class_id`) REFERENCES `lesson_classes` (`id`) ON DELETE RESTRICT,
            INDEX `idx_class_date` (`class_id`, `lesson_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created or verified lessons table.<br>";

    // 3. translation_entries table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `translation_entries` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `lesson_id` INT UNSIGNED NOT NULL,
            `original_text` TEXT NOT NULL,
            `translated_text` TEXT NOT NULL,
            `romaji_text` TEXT DEFAULT NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT `fk_translations_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
            INDEX `idx_lesson_order` (`lesson_id`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created or verified translation_entries table.<br>";

    // 4. settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT NOT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created or verified settings table.<br>";

    // 5. Insert default classes if empty
    $checkClasses = (int)$pdo->query("SELECT COUNT(*) FROM `lesson_classes`")->fetchColumn();
    if ($checkClasses === 0) {
        $insert = $pdo->prepare("
            INSERT INTO `lesson_classes` (`name`, `slug`, `schedule_day`, `schedule_time`)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute(['Thursday 7 PM', 'thursday-7pm', 'Thursday', '7 PM']);
        $insert->execute(['Thursday 9 PM', 'thursday-9pm', 'Thursday', '9 PM']);
        $insert->execute(['Friday 10 AM', 'friday-10am', 'Friday', '10 AM']);
        $insert->execute(['Saturday 2 PM', 'saturday-2pm', 'Saturday', '2 PM']);
        $insert->execute(["Takayama's Class", 'takayamas-class', null, null]);
        echo "Inserted default classes.<br>";
    }

    echo "<br><strong>Database setup completed successfully!</strong><br><br>";
    echo "<a href='../index.php'>Go to Student Portal</a> | <a href='../admin/'>Go to Teacher Dashboard</a>";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
