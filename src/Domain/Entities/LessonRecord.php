<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;

/**
 * Domain Entity representing a lesson record on a specific date.
 */
class LessonRecord
{
    /**
     * @param TranslationEntry[] $translationEntries
     */
    public function __construct(
        private ?int $id,
        private int $classId,
        private string $lessonDate,
        private string $rawText,
        private ?DateTimeImmutable $createdAt = null,
        private array $translationEntries = []
    ) {
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
        return count($this->translationEntries);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->classId,
            'lesson_date' => $this->lessonDate,
            'raw_text' => $this->rawText,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'translation_count' => $this->getTranslationCount(),
        ];
    }
}
