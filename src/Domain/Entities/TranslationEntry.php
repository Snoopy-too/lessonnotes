<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use Error;
use InvalidArgumentException;

/**
 * Domain Entity representing a single parsed translation entry.
 * Cross-version compatible with PHP 8.2 through PHP 8.5+.
 */
final class TranslationEntry
{
    public function __construct(
        private ?int $id,
        private ?int $lessonId,
        private string $originalText,
        private string $translatedText,
        private ?string $romajiText = null,
        private int $sortOrder = 0
    ) {
        if (trim($this->originalText) === '') {
            throw new InvalidArgumentException('Original text cannot be empty.');
        }
        if (trim($this->translatedText) === '') {
            throw new InvalidArgumentException('Translated text cannot be empty.');
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
            'lessonId' => $this->lessonId,
            'originalText' => $this->originalText,
            'translatedText' => $this->translatedText,
            'romajiText' => $this->romajiText,
            'sortOrder' => $this->sortOrder,
            'displayOriginal' => htmlspecialchars($this->originalText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'displayTranslated' => htmlspecialchars($this->translatedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'displayRomaji' => $this->romajiText !== null ? htmlspecialchars($this->romajiText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null,
            default => throw new Error("Cannot access undefined property " . static::class . "::\${$property}"),
        };
    }

    public function __isset(string $property): bool
    {
        return in_array($property, ['id', 'lessonId', 'originalText', 'translatedText', 'romajiText', 'sortOrder', 'displayOriginal', 'displayTranslated', 'displayRomaji'], true);
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
