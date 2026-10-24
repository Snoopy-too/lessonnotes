<?php
declare(strict_types=1);

/**
 * PSR-4 Autoloader for LessonNotes Namespace
 * Includes runtime polyfills for cross-version compatibility (PHP 8.2 - PHP 8.5+).
 */

// Polyfill PHP 8.4 array functions for PHP 8.2 / 8.3 runtimes
if (!function_exists('array_find')) {
    function array_find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('array_all')) {
    function array_all(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}

// Polyfill PHP 8.3 #[Override] attribute for PHP 8.2 runtimes
if (!class_exists('Override', false)) {
    #[Attribute(Attribute::TARGET_METHOD)]
    final class Override
    {
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'LessonNotes\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
