<?php
declare(strict_types=1);

/**
 * Bootstrap file for Eikaiwa Lesson Portal
 */

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../config/database.php';

use LessonNotes\Infrastructure\Container\DIContainer;

/**
 * Helper to obtain the configured DIContainer.
 */
function appContainer(): DIContainer
{
    static $container = null;
    if ($container === null) {
        $container = new DIContainer();
    }
    return $container;
}
