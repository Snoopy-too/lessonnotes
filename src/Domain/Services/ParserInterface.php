<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Services;

use LessonNotes\Domain\Entities\TranslationEntry;

/**
 * Interface for bilingual lesson notes text parsing.
 */
interface ParserInterface
{
    /**
     * Parse raw teacher notes into a collection of TranslationEntry entities.
     *
     * @param string $rawText
     * @return TranslationEntry[]
     * @throws ParseException If the text contains malformed blocks or lacks required translation pairs.
     */
    public function parse(string $rawText): array;
}
