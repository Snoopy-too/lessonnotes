<?php
declare(strict_types=1);

namespace LessonNotes\Application\UseCases;

use LessonNotes\Application\DTO\LessonSummaryResponse;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use LessonNotes\Domain\Repositories\LessonRepositoryInterface;

/**
 * Use case to retrieve paginated lessons for a selected class.
 */
class GetClassLessonsUseCase
{
    public function __construct(
        private LessonClassRepositoryInterface $classRepository,
        private LessonRepositoryInterface $lessonRepository
    ) {
    }

    /**
     * @return array{
     *     class: \LessonNotes\Domain\Entities\LessonClass,
     *     lessons: LessonSummaryResponse[],
     *     total_lessons: int,
     *     pagination: array{
     *         total_items: int,
     *         total_pages: int,
     *         current_page: int,
     *         per_page: int,
     *         has_prev: bool,
     *         has_next: bool,
     *         prev_page: int,
     *         next_page: int
     *     }
     * }|null
     */
    public function execute(string $slug, int $page = 1, int $perPage = 8): ?array
    {
        $class = $this->classRepository->findBySlug($slug);
        if ($class === null) {
            return null;
        }

        $classId = (int)$class->getId();
        $totalLessons = $this->lessonRepository->countByClassId($classId);

        $totalPages = max(1, (int)ceil($totalLessons / $perPage));
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $perPage;

        $results = $this->lessonRepository->findByClassId($classId, $perPage, $offset);

        $lessonSummaries = [];
        foreach ($results as $item) {
            $lesson = $item['lesson'];
            $timestamp = strtotime($lesson->getLessonDate());
            $formattedDate = date('F j, Y', $timestamp);
            $formattedDateJapanese = date('Y年n月j日', $timestamp);

            $lessonSummaries[] = new LessonSummaryResponse(
                (int)$lesson->getId(),
                $lesson->getClassId(),
                $lesson->getLessonDate(),
                $item['translation_count'],
                $formattedDate,
                $formattedDateJapanese
            );
        }

        return [
            'class' => $class,
            'lessons' => $lessonSummaries,
            'total_lessons' => $totalLessons,
            'pagination' => [
                'total_items' => $totalLessons,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'has_prev' => $currentPage > 1,
                'has_next' => $currentPage < $totalPages,
                'prev_page' => $currentPage - 1,
                'next_page' => $currentPage + 1,
            ],
        ];
    }
}
