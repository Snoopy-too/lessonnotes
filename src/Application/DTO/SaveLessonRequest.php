<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

use InvalidArgumentException;

/**
 * Data Transfer Object for saving a lesson and its translations.
 * Modernized with PHP 8.5 asymmetric visibility.
 */
class SaveLessonRequest
{
    public function __construct(
        public private(set) int $classId,
        public private(set) string $lessonDate,
        public private(set) string $rawText,
        public private(set) ?int $lessonId = null
    ) {
        if ($this->classId <= 0) {
            throw new InvalidArgumentException('A valid class must be selected.');
        }

        if (trim($this->lessonDate) === '') {
            throw new InvalidArgumentException('Lesson date cannot be empty.');
        }

        if (trim($this->rawText) === '') {
            throw new InvalidArgumentException('Lesson notes text cannot be empty.');
        }
    }
}
