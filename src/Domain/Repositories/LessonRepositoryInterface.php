<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Repositories;

use LessonNotes\Domain\Entities\LessonRecord;
use LessonNotes\Domain\Entities\TranslationEntry;

/**
 * Port interface for LessonRecord and TranslationEntry persistence.
 */
interface LessonRepositoryInterface
{
    public function findById(int $id): ?LessonRecord;

    public function findByClassAndDate(int $classId, string $date): ?LessonRecord;

    /**
     * @return array<int, array{lesson: LessonRecord, translation_count: int}>
     */
    public function findByClassId(int $classId, int $limit = 50, int $offset = 0): array;

    public function countByClassId(int $classId): int;

    /**
     * Save lesson and replace its translation entries in an atomic transaction.
     *
     * @param LessonRecord $lesson
     * @param TranslationEntry[] $translations
     * @return LessonRecord
     */
    public function save(LessonRecord $lesson, array $translations): LessonRecord;

    public function delete(int $id): bool;

    /**
     * @return TranslationEntry[]
     */
    public function getTranslationsForLesson(int $lessonId, int $limit = 0, int $offset = 0): array;

    public function countTranslationsForLesson(int $lessonId): int;

    public function saveTranslation(TranslationEntry $entry): TranslationEntry;

    public function updateTranslation(TranslationEntry $entry): bool;

    public function deleteTranslation(int $translationId, int $lessonId): bool;
}
