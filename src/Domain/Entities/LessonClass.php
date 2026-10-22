<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;

/**
 * Domain Entity representing an Eikaiwa class.
 */
class LessonClass
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $scheduleDay = null,
        private ?string $scheduleTime = null,
        private ?DateTimeImmutable $createdAt = null
    ) {
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
        if ($this->scheduleDay !== null && $this->scheduleTime !== null) {
            return $this->scheduleDay . ' ' . $this->scheduleTime;
        }
        return $this->name;
    }

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
