<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain Entity representing an Eikaiwa class.
 * Modernized with PHP 8.5 asymmetric visibility & property hooks.
 */
final class LessonClass
{
    public function __construct(
        public private(set) ?int $id,
        public private(set) string $name,
        public private(set) string $slug,
        public private(set) ?string $scheduleDay = null,
        public private(set) ?string $scheduleTime = null,
        public private(set) ?DateTimeImmutable $createdAt = null
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Class name cannot be empty.');
        }
        if (trim($this->slug) === '') {
            throw new InvalidArgumentException('Class slug cannot be empty.');
        }
    }

    // Property hook for sanitized display name
    public string $displayName {
        get => htmlspecialchars($this->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Property hook for schedule label
    public string $scheduleLabel {
        get => ($this->scheduleDay !== null && $this->scheduleTime !== null)
            ? $this->scheduleDay . ' ' . $this->scheduleTime
            : $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getScheduleDay(): ?string
    {
        return $this->scheduleDay;
    }

    public function getScheduleTime(): ?string
    {
        return $this->scheduleTime;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getScheduleLabel(): string
    {
        return $this->scheduleLabel;
    }

    /**
     * @return array{
     *     id: ?int,
     *     name: string,
     *     slug: string,
     *     schedule_day: ?string,
     *     schedule_time: ?string,
     *     created_at: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'schedule_day' => $this->scheduleDay,
            'schedule_time' => $this->scheduleTime,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
