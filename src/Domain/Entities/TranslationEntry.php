<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

/**
 * Domain Entity representing a single parsed translation entry.
 */
class TranslationEntry
{
    public function __construct(
        private ?int $id,
        private ?int $lessonId,
        private string $originalText,
        private string $translatedText,
        private ?string $romajiText = null,
        private int $sortOrder = 0
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLessonId(): ?int
    {
        return $this->lessonId;
    }

    public function withLessonId(int $lessonId): self
    {
        $clone = clone $this;
        $clone->lessonId = $lessonId;
        return $clone;
    }

    public function getOriginalText(): string
    {
        return $this->originalText;
    }

    public function getTranslatedText(): string
    {
        return $this->translatedText;
    }

    public function getRomajiText(): ?string
    {
        return $this->romajiText;
    }

    public function hasRomaji(): bool
    {
        return $this->romajiText !== null && trim($this->romajiText) !== '';
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lessonId,
            'original_text' => $this->originalText,
            'translated_text' => $this->translatedText,
            'romaji_text' => $this->romajiText,
            'sort_order' => $this->sortOrder,
        ];
    }
}
