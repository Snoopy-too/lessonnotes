<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain Entity representing a lesson record on a specific date.
 * Modernized with PHP 8.5 asymmetric visibility & property hooks.
 */
final class LessonRecord
{
    /**
     * @param TranslationEntry[] $translationEntries
     */
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $classId,
        public private(set) string $lessonDate,
        public private(set) string $rawText,
        public private(set) ?DateTimeImmutable $createdAt = null,
        private array $translationEntries = []
    ) {
        if ($this->classId <= 0) {
            throw new InvalidArgumentException('Class ID must be positive.');
        }
        if (trim($this->lessonDate) === '') {
            throw new InvalidArgumentException('Lesson date cannot be empty.');
        }
    }

    // Property hook for translation count
    public int $translationCount {
        get => count($this->translationEntries);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClassId(): int
    {
        return $this->classId;
    }

    public function getLessonDate(): string
    {
        return $this->lessonDate;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return TranslationEntry[]
     */
    public function getTranslationEntries(): array
    {
        return $this->translationEntries;
    }

    /**
     * @param TranslationEntry[] $entries
     */
    public function setTranslationEntries(array $entries): void
    {
        $this->translationEntries = $entries;
    }

    public function addTranslationEntry(TranslationEntry $entry): void
    {
        $this->translationEntries[] = $entry;
    }

    public function getTranslationCount(): int
    {
        return $this->translationCount;
    }

    /**
     * @return array{
     *     id: ?int,
     *     class_id: int,
     *     lesson_date: string,
     *     raw_text: string,
     *     created_at: ?string,
     *     translation_count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->classId,
            'lesson_date' => $this->lessonDate,
            'raw_text' => $this->rawText,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'translation_count' => $this->translationCount,
        ];
    }
}
