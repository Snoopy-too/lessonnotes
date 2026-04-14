<?php
/**
 * One-time migration script: Add Takayama's Class
 * Safe to run multiple times (checks for existing row first).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT id FROM classes WHERE slug = ?');
$stmt->execute(['takayamas-class']);
$existing = $stmt->fetch();

if ($existing) {
    echo "Takayama's Class already exists (ID: " . $existing['id'] . "). No action taken.\n";
} else {
    $stmt = $pdo->prepare('INSERT INTO classes (class_name, slug) VALUES (?, ?)');
    $stmt->execute(["Takayama's Class", 'takayamas-class']);
    echo "Inserted Takayama's Class successfully. ID: " . $pdo->lastInsertId() . "\n";
}
