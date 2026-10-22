<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Repositories;

use LessonNotes\Domain\Entities\LessonClass;

/**
 * Port interface for LessonClass persistence.
 */
interface LessonClassRepositoryInterface
{
    /**
     * @return LessonClass[]
     */
    public function findAll(): array;

    public function findById(int $id): ?LessonClass;

    public function findBySlug(string $slug): ?LessonClass;

    public function save(LessonClass $lessonClass): LessonClass;
}
