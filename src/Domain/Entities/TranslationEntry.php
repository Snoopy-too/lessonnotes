<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use InvalidArgumentException;

/**
 * Domain Entity representing a single parsed translation entry.
 * Modernized with PHP 8.5 asymmetric visibility & property hooks.
 */
final class TranslationEntry
{
    public function __construct(
        public private(set) ?int $id,
        public private(set) ?int $lessonId,
        public private(set) string $originalText,
        public private(set) string $translatedText,
        public private(set) ?string $romajiText = null,
        public private(set) int $sortOrder = 0
    ) {
        if (trim($this->originalText) === '') {
            throw new InvalidArgumentException('Original text cannot be empty.');
        }
        if (trim($this->translatedText) === '') {
            throw new InvalidArgumentException('Translated text cannot be empty.');
        }
    }

    // Property hook for sanitized display text
    public string $displayOriginal {
        get => htmlspecialchars($this->originalText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public string $displayTranslated {
        get => htmlspecialchars($this->translatedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public ?string $displayRomaji {
        get => $this->romajiText !== null ? htmlspecialchars($this->romajiText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
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
        return new self(
            $this->id,
            $lessonId,
            $this->originalText,
            $this->translatedText,
            $this->romajiText,
            $this->sortOrder
        );
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

    /**
     * @return array{
     *     id: ?int,
     *     lesson_id: ?int,
     *     original_text: string,
     *     translated_text: string,
     *     romaji_text: ?string,
     *     sort_order: int
     * }
     */
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
