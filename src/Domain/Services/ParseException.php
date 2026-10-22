<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Services;

use DomainException;

/**
 * Exception thrown when bilingual notes parsing fails.
 */
class ParseException extends DomainException
{
    /**
     * @param string[] $errors Detailed list of validation/parsing errors
     */
    public function __construct(
        string $message,
        private array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
