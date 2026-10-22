<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Factory for creating configured PDO instances with strict error handling and UTF-8 mb4.
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
        $host = $config['host'] ?? (defined('DB_HOST') ? DB_HOST : 'localhost');
        $dbname = $config['dbname'] ?? (defined('DB_NAME') ? DB_NAME : 'lessnotes');
        $user = $config['user'] ?? (defined('DB_USER') ? DB_USER : 'root');
        $pass = $config['pass'] ?? (defined('DB_PASS') ? DB_PASS : '');
        $charset = $config['charset'] ?? (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
        $port = $config['port'] ?? 3306;

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
