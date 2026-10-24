<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;
use Error;
use InvalidArgumentException;

/**
 * Domain Entity representing a lesson record on a specific date.
 * Cross-version compatible with PHP 8.2 through PHP 8.5+.
 */
final class LessonRecord
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
        if ($this->classId <= 0) {
            throw new InvalidArgumentException('Class ID must be positive.');
        }
        if (trim($this->lessonDate) === '') {
            throw new InvalidArgumentException('Lesson date cannot be empty.');
        }
    }

    /**
     * Cross-version property hook and asymmetric visibility accessor.
     * Enforces public read-only access for properties.
     */
    public function __get(string $property): mixed
    {
        return match ($property) {
            'id' => $this->id,
            'classId' => $this->classId,
            'lessonDate' => $this->lessonDate,
            'rawText' => $this->rawText,
            'createdAt' => $this->createdAt,
            'translationEntries' => $this->translationEntries,
            'translationCount' => count($this->translationEntries),
            default => throw new Error("Cannot access undefined property " . static::class . "::\${$property}"),
        };
    }

    public function __isset(string $property): bool
    {
        return in_array($property, ['id', 'classId', 'lessonDate', 'rawText', 'createdAt', 'translationEntries', 'translationCount'], true);
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
            'translation_count' => count($this->translationEntries),
        ];
    }
}
