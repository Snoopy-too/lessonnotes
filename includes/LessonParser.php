<?php
/**
 * Lesson Notes Parser
 * Parses bilingual translation entries from pasted text
 */

class LessonParser {
    /**
     * Parse the raw lesson notes text into structured entries
     *
     * @param string $text Raw pasted text from teacher
     * @return array Array of parsed translation entries
     */
    public function parse(string $text): array {
        $entries = [];

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Split by empty lines or dividers (--------)
        $blocks = preg_split('/\n\s*\n|\n-{3,}\n/', $text);

        $sortOrder = 1;

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            $entry = $this->parseBlock($block);

            if ($entry !== null) {
                $entry['sort_order'] = $sortOrder++;
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Parse a single block of text into a translation entry
     *
     * @param string $block Single entry block
     * @return array|null Parsed entry or null if invalid
     */
    private function parseBlock(string $block): ?array {
        $lines = array_filter(array_map('trim', explode("\n", $block)));

        if (empty($lines)) {
            return null;
        }

        $entry = [
            'original_text' => '',
            'translated_text' => '',
            'romaji_text' => null,
        ];

        $foundOriginal = false;
        $translations = [];

        foreach ($lines as $line) {
            // Match Original: pattern
            if (preg_match('/^Original:\s*(.+)$/i', $line, $matches)) {
                $entry['original_text'] = trim($matches[1]);
                $foundOriginal = true;
                continue;
            }

            // Match (Jp): pattern - Japanese translation
            if (preg_match('/^\(Jp\):\s*(.+)$/i', $line, $matches)) {
                $translations['jp'] = trim($matches[1]);
                continue;
            }

            // Match (Japanese): pattern - alternative Japanese label
            if (preg_match('/^\(Japanese\):\s*(.+)$/i', $line, $matches)) {
                $translations['jp'] = trim($matches[1]);
                continue;
            }

            // Match (English): pattern - English translation
            if (preg_match('/^\(English\):\s*(.+)$/i', $line, $matches)) {
                $translations['english'] = trim($matches[1]);
                continue;
            }

            // Match (Romaji): pattern - Romaji transliteration
            if (preg_match('/^\(Romaji\):\s*(.+)$/i', $line, $matches)) {
                $entry['romaji_text'] = trim($matches[1]);
                continue;
            }

            // Match generic translation pattern (English/Jp):
            if (preg_match('/^\(([^)]+)\):\s*(.+)$/i', $line, $matches)) {
                $label = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                if ($label === 'romaji') {
                    $entry['romaji_text'] = $value;
                } else {
                    $translations[$label] = $value;
                }
                continue;
            }
        }

        // If no Original: tag found, try to use the first line as original
        if (!$foundOriginal && !empty($lines)) {
            $firstLine = reset($lines);
            if (!preg_match('/^\([^)]+\):/', $firstLine)) {
                $entry['original_text'] = $firstLine;
            }
        }

        // Determine the main translation
        if (!empty($translations['jp'])) {
            $entry['translated_text'] = $translations['jp'];
        } elseif (!empty($translations['english'])) {
            $entry['translated_text'] = $translations['english'];
        } elseif (!empty($translations)) {
            // Use first available translation
            $entry['translated_text'] = reset($translations);
        }

        // Validate entry has required fields
        if (empty($entry['original_text']) || empty($entry['translated_text'])) {
            return null;
        }

        return $entry;
    }

    /**
     * Save parsed entries to database
     *
     * @param PDO $pdo Database connection
     * @param int $lessonId Lesson ID to associate entries with
     * @param array $entries Parsed entries from parse()
     * @return int Number of entries saved
     */
    public function saveEntries(PDO $pdo, int $lessonId, array $entries): int {
        $stmt = $pdo->prepare("
            INSERT INTO translations (lesson_id, original_text, translated_text, romaji_text, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        $count = 0;
        foreach ($entries as $entry) {
            $stmt->execute([
                $lessonId,
                $entry['original_text'],
                $entry['translated_text'],
                $entry['romaji_text'],
                $entry['sort_order']
            ]);
            $count++;
        }

        return $count;
    }
}
