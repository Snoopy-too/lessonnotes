<?php
/**
 * Database Initialization Script
 * Run this once to set up the database tables
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

try {
    // Create classes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_name VARCHAR(100) NOT NULL,
            slug VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created classes table.<br>";

    // Create lessons table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lessons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            lesson_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_class_date (class_id, lesson_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created lessons table.<br>";

    // Create translations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lesson_id INT NOT NULL,
            original_text TEXT NOT NULL,
            translated_text TEXT NOT NULL,
            romaji_text TEXT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created translations table.<br>";

    // Insert default classes
    $checkClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    if ($checkClasses == 0) {
        $insertClasses = $pdo->prepare("INSERT INTO classes (class_name, slug) VALUES (?, ?)");
        $insertClasses->execute(['Thursday 9 PM', 'thursday-9pm']);
        $insertClasses->execute(['Friday 10 AM', 'friday-10am']);
        $insertClasses->execute(['Saturday 2 PM', 'saturday-2pm']);
        echo "Inserted default classes.<br>";
    }

    echo "<br><strong>Database setup completed successfully!</strong><br><br>";
    echo "<a href='../index.php'>Go to Student Portal</a> | <a href='../admin/'>Go to Teacher Dashboard</a>";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
