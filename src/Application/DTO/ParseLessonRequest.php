<?php
declare(strict_types=1);

namespace LessonNotes\Application\DTO;

use InvalidArgumentException;

/**
 * Data Transfer Object for parsing lesson notes.
 */
class ParseLessonRequest
{
    public function __construct(
        public int $classId,
        public string $lessonDate,
        public string $rawText
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
