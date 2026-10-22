<?php
declare(strict_types=1);

namespace LessonNotes\Application\UseCases;

use LessonNotes\Domain\Entities\LessonClass;
use LessonNotes\Domain\Entities\LessonRecord;
use LessonNotes\Domain\Entities\TranslationEntry;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use LessonNotes\Domain\Repositories\LessonRepositoryInterface;

/**
 * Use case to retrieve lesson details and translation cards for student presentation.
 */
class GetLessonDetailUseCase
{
    public function __construct(
        private LessonRepositoryInterface $lessonRepository,
        private LessonClassRepositoryInterface $classRepository
    ) {
    }

    /**
     * @return array{
     *     lesson: LessonRecord,
     *     class: LessonClass,
     *     translations: TranslationEntry[],
     *     total_translations: int,
     *     formatted_date: string,
     *     formatted_date_japanese: string
     * }|null
     */
    public function execute(int $lessonId): ?array
    {
        if ($lessonId <= 0) {
            return null;
        }

        $lesson = $this->lessonRepository->findById($lessonId);
        if ($lesson === null) {
            return null;
        }

        $class = $this->classRepository->findById($lesson->getClassId());
        if ($class === null) {
            return null;
        }

        $translations = $this->lessonRepository->getTranslationsForLesson($lessonId);

        $timestamp = strtotime($lesson->getLessonDate());
        $formattedDate = date('F j, Y', $timestamp);
        $formattedDateJapanese = date('Y年n月j日', $timestamp);

        return [
            'lesson' => $lesson,
            'class' => $class,
            'translations' => $translations,
            'total_translations' => count($translations),
            'formatted_date' => $formattedDate,
            'formatted_date_japanese' => $formattedDateJapanese,
        ];
    }
}
