<?php
declare(strict_types=1);

namespace LessonNotes\Application\UseCases;

use InvalidArgumentException;
use LessonNotes\Application\DTO\SaveLessonRequest;
use LessonNotes\Domain\Entities\LessonRecord;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use LessonNotes\Domain\Repositories\LessonRepositoryInterface;
use LessonNotes\Domain\Services\ParserInterface;

/**
 * Use case to parse raw bilingual text and persist lesson records and translation entries.
 */
class ParseAndSaveLessonUseCase
{
    public function __construct(
        private LessonClassRepositoryInterface $classRepository,
        private LessonRepositoryInterface $lessonRepository,
        private ParserInterface $parser
    ) {
    }

    public function execute(SaveLessonRequest $request): LessonRecord
    {
        // 1. Verify class exists
        $class = $this->classRepository->findById($request->classId);
        if ($class === null) {
            throw new InvalidArgumentException("Class with ID {$request->classId} not found.");
        }

        // 2. Parse text into TranslationEntry[] collection
        $entries = $this->parser->parse($request->rawText);

        // 3. Resolve existing or new LessonRecord
        $lessonRecord = null;
        if ($request->lessonId !== null && $request->lessonId > 0) {
            $lessonRecord = $this->lessonRepository->findById($request->lessonId);
        }

        if ($lessonRecord === null) {
            $lessonRecord = $this->lessonRepository->findByClassAndDate($request->classId, $request->lessonDate);
        }

        if ($lessonRecord !== null) {
            // Update existing lesson record
            $lessonToSave = new LessonRecord(
                $lessonRecord->getId(),
                $request->classId,
                $request->lessonDate,
                $request->rawText,
                $lessonRecord->getCreatedAt(),
                $entries
            );
        } else {
            // Create new lesson record
            $lessonToSave = new LessonRecord(
                null,
                $request->classId,
                $request->lessonDate,
                $request->rawText,
                null,
                $entries
            );
        }

        // 4. Persist atomically
        return $this->lessonRepository->save($lessonToSave, $entries);
    }
}
