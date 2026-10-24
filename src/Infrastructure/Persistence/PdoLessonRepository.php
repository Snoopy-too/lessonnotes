<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Persistence;

use DateTimeImmutable;
use LessonNotes\Domain\Entities\LessonRecord;
use LessonNotes\Domain\Entities\TranslationEntry;
use LessonNotes\Domain\Repositories\LessonRepositoryInterface;
use Override;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PDO implementation of LessonRepositoryInterface with legacy table resilience.
 * Modernized with PHP 8.5 #[Override] attributes.
 */
class PdoLessonRepository implements LessonRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    #[Override]
    public function findById(int $id): ?LessonRecord
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `lessons` WHERE `id` = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $lesson = $this->mapRowToLesson($row);
        $translations = $this->getTranslationsForLesson($id);
        $lesson->setTranslationEntries($translations);

        return $lesson;
    }

    #[Override]
    public function findByClassAndDate(int $classId, string $date): ?LessonRecord
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `lessons` WHERE `class_id` = ? AND `lesson_date` = ?');
        $stmt->execute([$classId, $date]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $lesson = $this->mapRowToLesson($row);
        $translations = $this->getTranslationsForLesson((int)$lesson->getId());
        $lesson->setTranslationEntries($translations);

        return $lesson;
    }

    /**
     * @return array<int, array{lesson: LessonRecord, translation_count: int}>
     */
    #[Override]
    public function findByClassId(int $classId, int $limit = 50, int $offset = 0): array
    {
        $transTable = $this->resolveTranslationsTable();
        $sql = "
            SELECT l.*, COUNT(t.id) AS translation_count
            FROM `lessons` l
            LEFT JOIN `{$transTable}` t ON t.lesson_id = l.id
            WHERE l.class_id = :class_id
            GROUP BY l.id
            ORDER BY l.lesson_date DESC, l.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch()) {
            $lesson = $this->mapRowToLesson($row);
            $results[] = [
                'lesson' => $lesson,
                'translation_count' => (int)($row['translation_count'] ?? 0),
            ];
        }

        return $results;
    }

    #[Override]
    public function countByClassId(int $classId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `lessons` WHERE `class_id` = ?');
        $stmt->execute([$classId]);
        return (int)$stmt->fetchColumn();
    }

    #[Override]
    public function save(LessonRecord $lesson, array $translations): LessonRecord
    {
        $this->pdo->beginTransaction();

        try {
            $lessonId = $lesson->getId();
            $hasRawText = $this->lessonsHasRawText();

            if ($lessonId !== null && $lessonId > 0) {
                if ($hasRawText) {
                    $stmt = $this->pdo->prepare('
                        UPDATE `lessons`
                        SET `class_id` = :class_id,
                            `lesson_date` = :lesson_date,
                            `raw_text` = :raw_text
                        WHERE `id` = :id
                    ');
                    $stmt->execute([
                        ':class_id' => $lesson->getClassId(),
                        ':lesson_date' => $lesson->getLessonDate(),
                        ':raw_text' => $lesson->getRawText(),
                        ':id' => $lessonId,
                    ]);
                } else {
                    $stmt = $this->pdo->prepare('
                        UPDATE `lessons`
                        SET `class_id` = :class_id,
                            `lesson_date` = :lesson_date
                        WHERE `id` = :id
                    ');
                    $stmt->execute([
                        ':class_id' => $lesson->getClassId(),
                        ':lesson_date' => $lesson->getLessonDate(),
                        ':id' => $lessonId,
                    ]);
                }
            } else {
                if ($hasRawText) {
                    $stmt = $this->pdo->prepare('
                        INSERT INTO `lessons` (`class_id`, `lesson_date`, `raw_text`)
                        VALUES (:class_id, :lesson_date, :raw_text)
                    ');
                    $stmt->execute([
                        ':class_id' => $lesson->getClassId(),
                        ':lesson_date' => $lesson->getLessonDate(),
                        ':raw_text' => $lesson->getRawText(),
                    ]);
                } else {
                    $stmt = $this->pdo->prepare('
                        INSERT INTO `lessons` (`class_id`, `lesson_date`)
                        VALUES (:class_id, :lesson_date)
                    ');
                    $stmt->execute([
                        ':class_id' => $lesson->getClassId(),
                        ':lesson_date' => $lesson->getLessonDate(),
                    ]);
                }
                $lessonId = (int)$this->pdo->lastInsertId();
            }

            $transTable = $this->resolveTranslationsTable();

            // Delete existing translations
            $delStmt = $this->pdo->prepare("DELETE FROM `{$transTable}` WHERE `lesson_id` = ?");
            $delStmt->execute([$lessonId]);

            // Insert new translations
            $insertStmt = $this->pdo->prepare("
                INSERT INTO `{$transTable}` (`lesson_id`, `original_text`, `translated_text`, `romaji_text`, `sort_order`)
                VALUES (:lesson_id, :original_text, :translated_text, :romaji_text, :sort_order)
            ");

            $savedTranslations = [];
            foreach ($translations as $index => $entry) {
                $sortOrder = $entry->getSortOrder() > 0 ? $entry->getSortOrder() : ($index + 1);
                $insertStmt->execute([
                    ':lesson_id' => $lessonId,
                    ':original_text' => $entry->getOriginalText(),
                    ':translated_text' => $entry->getTranslatedText(),
                    ':romaji_text' => $entry->getRomajiText(),
                    ':sort_order' => $sortOrder,
                ]);

                $entryId = (int)$this->pdo->lastInsertId();
                $savedTranslations[] = new TranslationEntry(
                    $entryId,
                    $lessonId,
                    $entry->getOriginalText(),
                    $entry->getTranslatedText(),
                    $entry->getRomajiText(),
                    $sortOrder
                );
            }

            $this->pdo->commit();

            return new LessonRecord(
                $lessonId,
                $lesson->getClassId(),
                $lesson->getLessonDate(),
                $lesson->getRawText(),
                $lesson->getCreatedAt() ?? new DateTimeImmutable(),
                $savedTranslations
            );

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Failed to persist lesson record: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    #[Override]
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `lessons` WHERE `id` = ?');
        return $stmt->execute([$id]);
    }

    /**
     * @return TranslationEntry[]
     */
    #[Override]
    public function getTranslationsForLesson(int $lessonId, int $limit = 0, int $offset = 0): array
    {
        $transTable = $this->resolveTranslationsTable();

        if ($limit > 0) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM `{$transTable}`
                WHERE `lesson_id` = :lesson_id
                ORDER BY `sort_order` ASC, `id` ASC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':lesson_id', $lessonId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM `{$transTable}`
                WHERE `lesson_id` = ?
                ORDER BY `sort_order` ASC, `id` ASC
            ");
            $stmt->execute([$lessonId]);
        }

        $entries = [];
        while ($row = $stmt->fetch()) {
            $entries[] = $this->mapRowToTranslation($row);
        }

        return $entries;
    }

    #[Override]
    public function countTranslationsForLesson(int $lessonId): int
    {
        $transTable = $this->resolveTranslationsTable();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$transTable}` WHERE `lesson_id` = ?");
        $stmt->execute([$lessonId]);
        return (int)$stmt->fetchColumn();
    }

    #[Override]
    public function saveTranslation(TranslationEntry $entry): TranslationEntry
    {
        $transTable = $this->resolveTranslationsTable();
        $stmt = $this->pdo->prepare("
            INSERT INTO `{$transTable}` (`lesson_id`, `original_text`, `translated_text`, `romaji_text`, `sort_order`)
            VALUES (:lesson_id, :original_text, :translated_text, :romaji_text, :sort_order)
        ");
        $stmt->execute([
            ':lesson_id' => $entry->getLessonId(),
            ':original_text' => $entry->getOriginalText(),
            ':translated_text' => $entry->getTranslatedText(),
            ':romaji_text' => $entry->getRomajiText(),
            ':sort_order' => $entry->getSortOrder(),
        ]);

        return new TranslationEntry(
            (int)$this->pdo->lastInsertId(),
            $entry->getLessonId(),
            $entry->getOriginalText(),
            $entry->getTranslatedText(),
            $entry->getRomajiText(),
            $entry->getSortOrder()
        );
    }

    #[Override]
    public function updateTranslation(TranslationEntry $entry): bool
    {
        $transTable = $this->resolveTranslationsTable();
        $stmt = $this->pdo->prepare("
            UPDATE `{$transTable}`
            SET `original_text` = :original_text,
                `translated_text` = :translated_text,
                `romaji_text` = :romaji_text,
                `sort_order` = :sort_order
            WHERE `id` = :id AND `lesson_id` = :lesson_id
        ");
        return $stmt->execute([
            ':original_text' => $entry->getOriginalText(),
            ':translated_text' => $entry->getTranslatedText(),
            ':romaji_text' => $entry->getRomajiText(),
            ':sort_order' => $entry->getSortOrder(),
            ':id' => $entry->getId(),
            ':lesson_id' => $entry->getLessonId(),
        ]);
    }

    #[Override]
    public function deleteTranslation(int $translationId, int $lessonId): bool
    {
        $transTable = $this->resolveTranslationsTable();
        $stmt = $this->pdo->prepare("DELETE FROM `{$transTable}` WHERE `id` = ? AND `lesson_id` = ?");
        return $stmt->execute([$translationId, $lessonId]);
    }

    private function resolveTranslationsTable(): string
    {
        try {
            $this->pdo->query('SELECT 1 FROM `translation_entries` LIMIT 1');
            return 'translation_entries';
        } catch (Throwable) {
            return 'translations';
        }
    }

    private function lessonsHasRawText(): bool
    {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `lessons` LIKE 'raw_text'")->fetchAll();
            return !empty($cols);
        } catch (Throwable) {
            return false;
        }
    }

    private function mapRowToLesson(array $row): LessonRecord
    {
        $createdAt = !empty($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;

        return new LessonRecord(
            (int)$row['id'],
            (int)$row['class_id'],
            $row['lesson_date'],
            $row['raw_text'] ?? '',
            $createdAt
        );
    }

    private function mapRowToTranslation(array $row): TranslationEntry
    {
        return new TranslationEntry(
            (int)$row['id'],
            (int)$row['lesson_id'],
            $row['original_text'],
            $row['translated_text'],
            $row['romaji_text'] ?: null,
            (int)($row['sort_order'] ?? 0)
        );
    }
}
