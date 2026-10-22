<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

/**
 * Data Transfer Object for displaying lesson cards/items.
 */
class LessonSummaryResponse
{
    public function __construct(
        public readonly int $id,
        public readonly int $classId,
        public readonly string $lessonDate,
        public readonly int $translationCount,
        public readonly string $formattedDate,
        public readonly string $formattedDateJapanese
    ) {
    }
}
