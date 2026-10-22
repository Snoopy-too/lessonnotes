<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

use InvalidArgumentException;

/**
 * Data Transfer Object for saving a lesson and its translations.
 */
class SaveLessonRequest
{
    public function __construct(
        public readonly int $classId,
        public readonly string $lessonDate,
        public readonly string $rawText,
        public readonly ?int $lessonId = null
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
