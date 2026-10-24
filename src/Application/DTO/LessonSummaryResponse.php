<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

/**
 * Data Transfer Object for displaying lesson cards/items.
 * Modernized with PHP 8.5 asymmetric visibility.
 */
class LessonSummaryResponse
{
    public function __construct(
        public private(set) int $id,
        public private(set) int $classId,
        public private(set) string $lessonDate,
        public private(set) int $translationCount,
        public private(set) string $formattedDate,
        public private(set) string $formattedDateJapanese
    ) {
    }
}
