<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Persistence;

use DateTimeImmutable;
use LessonNotes\Domain\Entities\LessonClass;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use PDO;

/**
 * PDO implementation of LessonClassRepositoryInterface.
 */
class PdoLessonClassRepository implements LessonClassRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return LessonClass[]
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM `lesson_classes` ORDER BY `id` ASC');
        $classes = [];
        while ($row = $stmt->fetch()) {
            $classes[] = $this->mapRowToEntity($row);
        }
        return $classes;
    }

    public function findById(int $id): ?LessonClass
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `lesson_classes` WHERE `id` = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findBySlug(string $slug): ?LessonClass
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `lesson_classes` WHERE `slug` = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function save(LessonClass $lessonClass): LessonClass
    {
        if ($lessonClass->getId() !== null) {
            $stmt = $this->pdo->prepare('
                UPDATE `lesson_classes`
                SET `name` = :name,
                    `slug` = :slug,
                    `schedule_day` = :schedule_day,
                    `schedule_time` = :schedule_time
                WHERE `id` = :id
            ');
            $stmt->execute([
                ':name' => $lessonClass->getName(),
                ':slug' => $lessonClass->getSlug(),
                ':schedule_day' => $lessonClass->getScheduleDay(),
                ':schedule_time' => $lessonClass->getScheduleTime(),
                ':id' => $lessonClass->getId(),
            ]);
            return $lessonClass;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO `lesson_classes` (`name`, `slug`, `schedule_day`, `schedule_time`)
            VALUES (:name, :slug, :schedule_day, :schedule_time)
        ');
        $stmt->execute([
            ':name' => $lessonClass->getName(),
            ':slug' => $lessonClass->getSlug(),
            ':schedule_day' => $lessonClass->getScheduleDay(),
            ':schedule_time' => $lessonClass->getScheduleTime(),
        ]);

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

        return new LessonClass(
            (int)$row['id'],
            $row['name'],
            $row['slug'],
            $row['schedule_day'] ?? null,
            $row['schedule_time'] ?? null,
            $createdAt
        );
    }
}
