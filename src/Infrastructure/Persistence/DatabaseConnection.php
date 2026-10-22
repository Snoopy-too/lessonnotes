<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Factory for creating configured PDO instances with strict error handling,
 * auto-migration, and shared hosting compatibility.
 */
class DatabaseConnection
{
    /**
     * @param array{
     *     host?: string,
     *     dbname?: string,
     *     user?: string,
     *     pass?: string,
     *     charset?: string,
     *     port?: int
     * } $config
     */
    public static function create(array $config = []): PDO
    {
        // 1. If getDBConnection() is already defined in config/database.php, prioritize it
        if (empty($config) && function_exists('getDBConnection')) {
            try {
                $pdo = getDBConnection();
                if ($pdo instanceof PDO) {
                    self::autoMigrate($pdo);
                    return $pdo;
                }
            } catch (Throwable) {
                // Fall back to direct connection below
            }
        }

        $host = $config['host'] ?? (defined('DB_HOST') ? DB_HOST : 'localhost');
        $dbname = $config['dbname'] ?? (defined('DB_NAME') ? DB_NAME : 'lessnotes');
        $user = $config['user'] ?? (defined('DB_USER') ? DB_USER : 'root');
        $pass = $config['pass'] ?? (defined('DB_PASS') ? DB_PASS : '');
        $charset = $config['charset'] ?? (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

        // Build standard PDO DSN without forcing port if localhost (to allow UNIX sockets on shared hosts)
        if (isset($config['port']) && $config['port'] > 0 && $host !== 'localhost') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $config['port'], $dbname, $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            self::autoMigrate($pdo);
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Self-healing migration: Automatically ensures new hexagonal tables exist.
     * Guarantees zero downtime or manual DB scripts when deployed to production.
     */
    public static function autoMigrate(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            // Check if lesson_classes table exists
            $tableExists = $pdo->query("SHOW TABLES LIKE 'lesson_classes'")->fetchColumn();
            if ($tableExists) {
                return; // Already migrated
            }

            // 1. Create lesson_classes table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `lesson_classes` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(100) NOT NULL UNIQUE,
                    `schedule_day` VARCHAR(20) DEFAULT NULL,
                    `schedule_time` VARCHAR(20) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Migrate from legacy 'classes' if it exists
            $legacyClassesExist = $pdo->query("SHOW TABLES LIKE 'classes'")->fetchColumn();
            if ($legacyClassesExist) {
                $classes = $pdo->query("SELECT * FROM `classes` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO `lesson_classes` (`id`, `name`, `slug`, `schedule_day`, `schedule_time`, `created_at`)
                    VALUES (:id, :name, :slug, :day, :time, :created_at)
                ");
                foreach ($classes as $c) {
                    $name = $c['class_name'] ?? $c['name'] ?? '';
                    $slug = $c['slug'] ?? '';
                    $id = (int)$c['id'];
                    $createdAt = $c['created_at'] ?? date('Y-m-d H:i:s');

                    $day = null;
                    $time = null;
                    if (preg_match('/^(Thursday|Friday|Saturday|Sunday|Monday|Tuesday|Wednesday)\s+(.+)$/i', $name, $m)) {
                        $day = ucfirst(strtolower($m[1]));
                        $time = trim($m[2]);
                    }

                    $stmt->execute([
                        ':id' => $id,
                        ':name' => $name,
                        ':slug' => $slug,
                        ':day' => $day,
                        ':time' => $time,
                        ':created_at' => $createdAt,
                    ]);
                }
            } else {
                // Seed default classes if neither table had data
                $defaultClasses = [
                    ['Thursday 7 PM', 'thursday-7pm', 'Thursday', '7 PM'],
                    ['Thursday 9 PM', 'thursday-9pm', 'Thursday', '9 PM'],
                    ['Friday 10 AM', 'friday-10am', 'Friday', '10 AM'],
                    ['Saturday 2 PM', 'saturday-2pm', 'Saturday', '2 PM'],
                    ["Takayama's Class", 'takayamas-class', null, null],
                ];
                $stmt = $pdo->prepare("INSERT IGNORE INTO `lesson_classes` (`name`, `slug`, `schedule_day`, `schedule_time`) VALUES (?, ?, ?, ?)");
                foreach ($defaultClasses as $c) {
                    $stmt->execute($c);
                }
            }

            // 2. Create translation_entries table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `translation_entries` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `lesson_id` INT UNSIGNED NOT NULL,
                    `original_text` TEXT NOT NULL,
                    `translated_text` TEXT NOT NULL,
                    `romaji_text` TEXT DEFAULT NULL,
                    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    INDEX `idx_lesson_order` (`lesson_id`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Migrate legacy translations if present
            $legacyTransExist = $pdo->query("SHOW TABLES LIKE 'translations'")->fetchColumn();
            if ($legacyTransExist) {
                $trans = $pdo->query("SELECT * FROM `translations` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO `translation_entries` (`id`, `lesson_id`, `original_text`, `translated_text`, `romaji_text`, `sort_order`)
                    VALUES (:id, :lesson_id, :original_text, :translated_text, :romaji_text, :sort_order)
                ");
                foreach ($trans as $t) {
                    $stmt->execute([
                        ':id' => (int)$t['id'],
                        ':lesson_id' => (int)$t['lesson_id'],
                        ':original_text' => $t['original_text'],
                        ':translated_text' => $t['translated_text'],
                        ':romaji_text' => $t['romaji_text'] ?: null,
                        ':sort_order' => (int)($t['sort_order'] ?? 0),
                    ]);
                }
            }

            // 3. Ensure lessons table has raw_text column
            $hasLessons = $pdo->query("SHOW TABLES LIKE 'lessons'")->fetchColumn();
            if ($hasLessons) {
                $cols = $pdo->query("SHOW COLUMNS FROM `lessons` LIKE 'raw_text'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `raw_text` MEDIUMTEXT NOT NULL DEFAULT '' AFTER `lesson_date`");
                }
            }

            // 4. Ensure settings table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                    `setting_key` VARCHAR(50) PRIMARY KEY,
                    `setting_value` TEXT NOT NULL,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

        } catch (Throwable $e) {
            error_log('Database autoMigrate warning: ' . $e->getMessage());
        }
    }
}
