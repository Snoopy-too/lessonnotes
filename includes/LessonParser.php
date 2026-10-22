<?php
declare(strict_types=1);

/**
 * Backward-compatible adapter for LessonParser
 * Powered by Hexagonal Architecture RegexBilingualParser
 */

require_once __DIR__ . '/../src/autoload.php';

use LessonNotes\Domain\Services\RegexBilingualParser;

class LessonParser
{
    private RegexBilingualParser $parser;

    public function __construct()
    {
        $this->parser = new RegexBilingualParser();
    }

    /**
     * @return array<int, array{original_text: string, translated_text: string, romaji_text: ?string, sort_order: int}>
     */
    public function parse(string $text): array
    {
        try {
            $entries = $this->parser->parse($text);
            $results = [];
            foreach ($entries as $entry) {
                $results[] = [
                    'original_text' => $entry->getOriginalText(),
                    'translated_text' => $entry->getTranslatedText(),
                    'romaji_text' => $entry->getRomajiText(),
                    'sort_order' => $entry->getSortOrder(),
                ];
            }
            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, array{original_text: string, translated_text: string, romaji_text?: ?string, sort_order?: int}> $entries
     */
    public function saveEntries(PDO $pdo, int $lessonId, array $entries): int
    {
        $stmt = $pdo->prepare('
            INSERT INTO `translation_entries` (`lesson_id`, `original_text`, `translated_text`, `romaji_text`, `sort_order`)
            VALUES (?, ?, ?, ?, ?)
        ');

        $count = 0;
        foreach ($entries as $entry) {
            $stmt->execute([
                $lessonId,
                $entry['original_text'],
                $entry['translated_text'],
                $entry['romaji_text'] ?? null,
                $entry['sort_order'] ?? ($count + 1),
            ]);
            $count++;
        }

        return $count;
    }
}
