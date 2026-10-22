<?php
declare(strict_types=1);

/**
 * Migration script: Add Takayama's Class
 * Safe to run multiple times (checks for existing row first).
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT id FROM `lesson_classes` WHERE slug = ?');
$stmt->execute(['takayamas-class']);
$existing = $stmt->fetch();

if ($existing) {
    echo "Takayama's Class already exists in lesson_classes (ID: " . $existing['id'] . "). No action taken.\n";
} else {
    $stmt = $pdo->prepare('INSERT INTO `lesson_classes` (`name`, `slug`) VALUES (?, ?)');
    $stmt->execute(["Takayama's Class", 'takayamas-class']);
    echo "Inserted Takayama's Class successfully. ID: " . $pdo->lastInsertId() . "\n";
}
