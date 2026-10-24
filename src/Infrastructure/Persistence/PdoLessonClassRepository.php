<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Persistence;

use DateTimeImmutable;
use LessonNotes\Domain\Entities\LessonClass;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use Override;
use PDO;
use Throwable;

/**
 * PDO implementation of LessonClassRepositoryInterface with legacy table resilience.
 * Modernized with PHP 8.5 #[Override] attributes.
 */
class PdoLessonClassRepository implements LessonClassRepositoryInterface
{
    private ?string $lastError = null;

    public function __construct(
        private PDO $pdo
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return LessonClass[]
     */
    #[Override]
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM `lesson_classes` ORDER BY `id` ASC');
            $classes = [];
            while ($row = $stmt->fetch()) {
                $classes[] = $this->mapRowToEntity($row);
            }
            return $classes;
        } catch (Throwable $e1) {
            $this->lastError = 'lesson_classes query failed: ' . $e1->getMessage();
            try {
                $stmt = $this->pdo->query('SELECT * FROM `classes` ORDER BY `id` ASC');
                $classes = [];
                while ($row = $stmt->fetch()) {
                    $classes[] = $this->mapRowToEntity($row);
                }
                return $classes;
            } catch (Throwable $e2) {
                $this->lastError .= ' | classes query failed: ' . $e2->getMessage();
                error_log('Failed to fetch classes: ' . $e2->getMessage());
                return [];
            }
        }
    }

    #[Override]
    public function findById(int $id): ?LessonClass
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM `lesson_classes` WHERE `id` = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? $this->mapRowToEntity($row) : null;
        } catch (Throwable) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM `classes` WHERE `id` = ?');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                return $row ? $this->mapRowToEntity($row) : null;
            } catch (Throwable) {
                return null;
            }
        }
    }

    #[Override]
    public function findBySlug(string $slug): ?LessonClass
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM `lesson_classes` WHERE `slug` = ?');
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            return $row ? $this->mapRowToEntity($row) : null;
        } catch (Throwable) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM `classes` WHERE `slug` = ?');
                $stmt->execute([$slug]);
                $row = $stmt->fetch();
                return $row ? $this->mapRowToEntity($row) : null;
            } catch (Throwable) {
                return null;
            }
        }
    }

    #[Override]
    public function save(LessonClass $lessonClass): LessonClass
    {
        $table = 'lesson_classes';
        try {
            $this->pdo->query('SELECT 1 FROM `lesson_classes` LIMIT 1');
        } catch (Throwable) {
            $table = 'classes';
        }

        if ($lessonClass->getId() !== null) {
            if ($table === 'lesson_classes') {
                $stmt = $this->pdo->prepare("
                    UPDATE `{$table}`
                    SET `name` = :name,
                        `slug` = :slug,
                        `schedule_day` = :schedule_day,
                        `schedule_time` = :schedule_time
                    WHERE `id` = :id
                ");
                $stmt->execute([
                    ':name' => $lessonClass->getName(),
                    ':slug' => $lessonClass->getSlug(),
                    ':schedule_day' => $lessonClass->getScheduleDay(),
                    ':schedule_time' => $lessonClass->getScheduleTime(),
                    ':id' => $lessonClass->getId(),
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE `classes`
                    SET `class_name` = :name,
                        `slug` = :slug
                    WHERE `id` = :id
                ");
                $stmt->execute([
                    ':name' => $lessonClass->getName(),
                    ':slug' => $lessonClass->getSlug(),
                    ':id' => $lessonClass->getId(),
                ]);
            }
            return $lessonClass;
        }

        if ($table === 'lesson_classes') {
            $stmt = $this->pdo->prepare("
                INSERT INTO `lesson_classes` (`name`, `slug`, `schedule_day`, `schedule_time`)
                VALUES (:name, :slug, :schedule_day, :schedule_time)
            ");
            $stmt->execute([
                ':name' => $lessonClass->getName(),
                ':slug' => $lessonClass->getSlug(),
                ':schedule_day' => $lessonClass->getScheduleDay(),
                ':schedule_time' => $lessonClass->getScheduleTime(),
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO `classes` (`class_name`, `slug`)
                VALUES (:name, :slug)
            ");
            $stmt->execute([
                ':name' => $lessonClass->getName(),
                ':slug' => $lessonClass->getSlug(),
            ]);
        }

        $newId = (int)$this->pdo->lastInsertId();
        return new LessonClass(
            $newId,
            $lessonClass->getName(),
            $lessonClass->getSlug(),
            $lessonClass->getScheduleDay(),
            $lessonClass->getScheduleTime(),
            new DateTimeImmutable()
        );
    }

    private function mapRowToEntity(array $row): LessonClass
    {
        $createdAt = !empty($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;

        $name = $row['name'] ?? $row['class_name'] ?? '';

        return new LessonClass(
            (int)$row['id'],
            $name,
            $row['slug'],
            $row['schedule_day'] ?? null,
            $row['schedule_time'] ?? null,
            $createdAt
        );
    }
}
