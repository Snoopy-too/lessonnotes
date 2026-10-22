<?php
declare(strict_types=1);

/**
 * Migration Script for Hexagonal Architecture Schema
 * Aligns database with PHP_UPDATE_AGENT_INSTRUCTIONS.md (Section 4)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Hexagonal Architecture Database Migration...\n";

    // 1. Create lesson_classes table
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
    echo "- Verified/created lesson_classes table.\n";

    // Migrate from legacy 'classes' if lesson_classes is empty
    $classCount = (int)$pdo->query("SELECT COUNT(*) FROM `lesson_classes`")->fetchColumn();
    if ($classCount === 0) {
        $legacyExists = $pdo->query("SHOW TABLES LIKE 'classes'")->fetchColumn();
        if ($legacyExists) {
            $legacyClasses = $pdo->query("SELECT * FROM `classes` ORDER BY `id`")->fetchAll(PDO::FETCH_ASSOC);
            $insertClass = $pdo->prepare("
                INSERT INTO `lesson_classes` (`id`, `name`, `slug`, `schedule_day`, `schedule_time`, `created_at`)
                VALUES (:id, :name, :slug, :schedule_day, :schedule_time, :created_at)
            ");

            foreach ($legacyClasses as $row) {
                $name = $row['class_name'] ?? $row['name'] ?? '';
                $slug = $row['slug'] ?? '';
                $id = (int)$row['id'];
                $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');

                $day = null;
                $time = null;
                if (preg_match('/^(Thursday|Friday|Saturday|Sunday|Monday|Tuesday|Wednesday)\s+(.+)$/i', $name, $matches)) {
                    $day = ucfirst(strtolower($matches[1]));
                    $time = trim($matches[2]);
                }

                $insertClass->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':schedule_day' => $day,
                    ':schedule_time' => $time,
                    ':created_at' => $createdAt,
                ]);
            }
            echo "- Migrated " . count($legacyClasses) . " classes from legacy 'classes' to 'lesson_classes'.\n";
        }
    }

    // 2. Ensure lessons table exists and conforms to schema
    $lessonsExists = $pdo->query("SHOW TABLES LIKE 'lessons'")->fetchColumn();
    if (!$lessonsExists) {
        $pdo->exec("
            CREATE TABLE `lessons` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `class_id` INT UNSIGNED NOT NULL,
                `lesson_date` DATE NOT NULL,
                `raw_text` MEDIUMTEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_lessons_class` FOREIGN KEY (`class_id`) REFERENCES `lesson_classes` (`id`) ON DELETE RESTRICT,
                INDEX `idx_class_date` (`class_id`, `lesson_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "- Created lessons table.\n";
    } else {
        // Drop any foreign key constraints on translations referencing lessons first if needed
        $transFkRows = $pdo->query("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'translations'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($transFkRows as $tFk) {
            try {
                $pdo->exec("ALTER TABLE `translations` DROP FOREIGN KEY `{$tFk}`");
                echo "- Dropped legacy foreign key `{$tFk}` from translations.\n";
            } catch (Throwable $e) {
                // ignore
            }
        }

        // Drop any foreign key constraints on lessons
        $fkRows = $pdo->query("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'lessons'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fkRows as $fkName) {
            try {
                $pdo->exec("ALTER TABLE `lessons` DROP FOREIGN KEY `{$fkName}`");
                echo "- Dropped legacy foreign key `{$fkName}` from lessons.\n";
            } catch (Throwable $e) {
                // ignore
            }
        }

        // Modify lessons columns to INT UNSIGNED
        $pdo->exec("ALTER TABLE `lessons` MODIFY `id` INT UNSIGNED AUTO_INCREMENT");
        $pdo->exec("ALTER TABLE `lessons` MODIFY `class_id` INT UNSIGNED NOT NULL");
        echo "- Converted lessons.id and lessons.class_id to INT UNSIGNED.\n";

        // Check for raw_text column
        $cols = $pdo->query("SHOW COLUMNS FROM `lessons` LIKE 'raw_text'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `raw_text` MEDIUMTEXT NOT NULL DEFAULT '' AFTER `lesson_date`");
            echo "- Added raw_text column to lessons.\n";
        }

        // Check index idx_class_date
        $indices = $pdo->query("SHOW INDEX FROM `lessons` WHERE Key_name = 'idx_class_date'")->fetchAll();
        if (empty($indices)) {
            $pdo->exec("ALTER TABLE `lessons` ADD INDEX `idx_class_date` (`class_id`, `lesson_date`)");
            echo "- Added index idx_class_date to lessons.\n";
        }

        // Add foreign key constraint to lesson_classes
        try {
            $pdo->exec("
                ALTER TABLE `lessons`
                ADD CONSTRAINT `fk_lessons_class`
                FOREIGN KEY (`class_id`) REFERENCES `lesson_classes` (`id`) ON DELETE RESTRICT
            ");
            echo "- Added foreign key fk_lessons_class referencing lesson_classes(id).\n";
        } catch (Throwable $e) {
            echo "- Foreign key fk_lessons_class note: " . $e->getMessage() . "\n";
        }
    }

    // 3. Create translation_entries table
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
    echo "- Verified/created translation_entries table.\n";

    // Migrate from legacy 'translations' if translation_entries is empty
    $entryCount = (int)$pdo->query("SELECT COUNT(*) FROM `translation_entries`")->fetchColumn();
    if ($entryCount === 0) {
        $legacyTranslationsExists = $pdo->query("SHOW TABLES LIKE 'translations'")->fetchColumn();
        if ($legacyTranslationsExists) {
            $legacyTranslations = $pdo->query("SELECT * FROM `translations` ORDER BY `lesson_id`, `sort_order`, `id`")->fetchAll(PDO::FETCH_ASSOC);
            $insertEntry = $pdo->prepare("
                INSERT INTO `translation_entries` (`id`, `lesson_id`, `original_text`, `translated_text`, `romaji_text`, `sort_order`)
                VALUES (:id, :lesson_id, :original_text, :translated_text, :romaji_text, :sort_order)
            ");
            foreach ($legacyTranslations as $t) {
                $insertEntry->execute([
                    ':id' => (int)$t['id'],
                    ':lesson_id' => (int)$t['lesson_id'],
                    ':original_text' => $t['original_text'],
                    ':translated_text' => $t['translated_text'],
                    ':romaji_text' => $t['romaji_text'] ?: null,
                    ':sort_order' => (int)($t['sort_order'] ?? 0),
                ]);
            }
            echo "- Migrated " . count($legacyTranslations) . " translation entries from legacy 'translations'.\n";

            // Reconstruct raw_text for existing lessons if empty
            $lessonsWithoutRaw = $pdo->query("SELECT id FROM `lessons` WHERE `raw_text` IS NULL OR `raw_text` = ''")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($lessonsWithoutRaw as $lId) {
                $stmt = $pdo->prepare("SELECT * FROM `translation_entries` WHERE `lesson_id` = ? ORDER BY `sort_order`, `id`");
                $stmt->execute([$lId]);
                $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rawBlocks = [];
                foreach ($entries as $e) {
                    $block = "Original: " . $e['original_text'] . "\n(Jp): " . $e['translated_text'];
                    if (!empty($e['romaji_text'])) {
                        $block .= "\n(Romaji): " . $e['romaji_text'];
                    }
                    $rawBlocks[] = $block;
                }
                $rawText = implode("\n\n-------\n\n", $rawBlocks);
                $upd = $pdo->prepare("UPDATE `lessons` SET `raw_text` = ? WHERE `id` = ?");
                $upd->execute([$rawText, $lId]);
            }
            echo "- Reconstructed raw_text for existing lessons.\n";
        }
    }

    // 4. Ensure settings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT NOT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- Verified/created settings table.\n";

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
