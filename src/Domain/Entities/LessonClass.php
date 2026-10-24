<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use DateTimeImmutable;
use Error;
use InvalidArgumentException;

/**
 * Domain Entity representing an Eikaiwa class.
 * Cross-version compatible with PHP 8.2 through PHP 8.5+.
 */
final class LessonClass
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $scheduleDay = null,
        private ?string $scheduleTime = null,
        private ?DateTimeImmutable $createdAt = null
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Class name cannot be empty.');
        }
        if (trim($this->slug) === '') {
            throw new InvalidArgumentException('Class slug cannot be empty.');
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
            'name' => $this->name,
            'slug' => $this->slug,
            'scheduleDay' => $this->scheduleDay,
            'scheduleTime' => $this->scheduleTime,
            'createdAt' => $this->createdAt,
            'displayName' => htmlspecialchars($this->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'scheduleLabel' => ($this->scheduleDay !== null && $this->scheduleTime !== null)
                ? $this->scheduleDay . ' ' . $this->scheduleTime
                : $this->name,
            default => throw new Error("Cannot access undefined property " . static::class . "::\${$property}"),
        };
    }

    public function __isset(string $property): bool
    {
        return in_array($property, ['id', 'name', 'slug', 'scheduleDay', 'scheduleTime', 'createdAt', 'displayName', 'scheduleLabel'], true);
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
