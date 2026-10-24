<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

/**
 * Data Transfer Object for displaying lesson cards/items.
 * Cross-version compatible with PHP 8.2 - PHP 8.5+.
 */
class LessonSummaryResponse
{
    public function __construct(
        public int $id,
        public int $classId,
        public string $lessonDate,
        public int $translationCount,
        public string $formattedDate,
        public string $formattedDateJapanese
    ) {
    }
}
