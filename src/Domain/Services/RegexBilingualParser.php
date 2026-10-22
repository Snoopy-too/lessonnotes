<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Services;

use LessonNotes\Domain\Entities\TranslationEntry;

/**
 * Robust regex-based parser for bilingual lesson notes text.
 * Supports:
 * - Format A (English to Japanese + optional Romaji)
 * - Format B (Japanese to English)
 * - Format C (Vocabulary / short phrases)
 */
class RegexBilingualParser implements ParserInterface
{
    /**
     * @return TranslationEntry[]
     * @throws ParseException
     */
    public function parse(string $rawText): array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $rawText));
        if ($normalized === '') {
            throw new ParseException('Lesson notes text cannot be empty.');
        }

        $lines = explode("\n", $normalized);
        $rawBlocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Divider pattern (e.g. ------- or =======)
            if (preg_match('/^[-=_*]{3,}$/', $trimmed)) {
                if (!empty($currentBlock)) {
                    $rawBlocks[] = $currentBlock;
                    $currentBlock = [];
                }
                continue;
            }

            // Explicit start of a new record via "Original:"
            if (preg_match('/^(?:Original|Orig)\s*:/i', $trimmed)) {
                if (!empty($currentBlock)) {
                    $rawBlocks[] = $currentBlock;
                    $currentBlock = [];
                }
                $currentBlock[] = $trimmed;
                continue;
            }

            // Empty line acts as a block boundary
            if ($trimmed === '') {
                if (!empty($currentBlock)) {
                    $rawBlocks[] = $currentBlock;
                    $currentBlock = [];
                }
                continue;
            }

            $currentBlock[] = $trimmed;
        }

        if (!empty($currentBlock)) {
            $rawBlocks[] = $currentBlock;
        }

        if (empty($rawBlocks)) {
            throw new ParseException('No readable text blocks found in the input.');
        }

        $entries = [];
        $errors = [];
        $sortOrder = 1;

        foreach ($rawBlocks as $blockIndex => $blockLines) {
            $entryNumber = $blockIndex + 1;
            $original = '';
            $jp = '';
            $english = '';
            $romaji = null;
            $genericTranslations = [];

            foreach ($blockLines as $line) {
                // 1. Original
                if (preg_match('/^(?:Original|Orig)\s*:\s*(.+)$/iu', $line, $matches)) {
                    $original = trim($matches[1]);
                    continue;
                }

                // 2. Romaji: (Romaji):, (Roma-ji):, or Romaji:
                if (preg_match('/^(?:\((?:Romaji|Roma-ji)\)|Romaji)\s*:\s*(.+)$/iu', $line, $matches)) {
                    $romaji = trim($matches[1]);
                    continue;
                }

                // 3. Japanese translation: (Jp):, (Japanese):, Jp:, Japanese:, (日本語):
                if (preg_match('/^(?:\((?:Jp|Japanese|JP|日本語)\)|Jp|Japanese)\s*:\s*(.+)$/iu', $line, $matches)) {
                    $jp = trim($matches[1]);
                    continue;
                }

                // 4. English translation: (English):, (Eng):, English:, Eng:, (英語):
                if (preg_match('/^(?:\((?:English|Eng|英語)\)|English|Eng)\s*:\s*(.+)$/iu', $line, $matches)) {
                    $english = trim($matches[1]);
                    continue;
                }

                // 5. Generic parenthetical label e.g. (English/Jp): or (Translation):
                if (preg_match('/^\(([^)]+)\)\s*:\s*(.+)$/iu', $line, $matches)) {
                    $label = strtolower(trim($matches[1]));
                    $value = trim($matches[2]);

                    if ($label === 'romaji' || $label === 'roma-ji') {
                        $romaji = $value;
                    } elseif (str_contains($label, 'jp') || str_contains($label, 'japanese')) {
                        $jp = $value;
                    } elseif (str_contains($label, 'en') || str_contains($label, 'english')) {
                        $english = $value;
                    } else {
                        $genericTranslations[$label] = $value;
                    }
                    continue;
                }

                // Fallback: If no Original tag yet and line does not look like a translation tag
                if ($original === '' && !preg_match('/^[\w\(\)\/-]+:/u', $line)) {
                    $original = $line;
                }
            }

            // Determine primary translation
            $translated = '';
            if ($jp !== '') {
                $translated = $jp;
            } elseif ($english !== '') {
                $translated = $english;
            } elseif (!empty($genericTranslations)) {
                $translated = reset($genericTranslations);
            }

            // Validate entry
            if ($original === '' && $translated === '') {
                // Entirely unrecognized block
                $preview = mb_substr(implode(' ', $blockLines), 0, 40);
                $errors[] = "Block #{$entryNumber} ('{$preview}...') could not be parsed into an Original/Translation pair.";
                continue;
            }

            if ($original === '') {
                $errors[] = "Block #{$entryNumber} is missing the 'Original:' text for translation '{$translated}'.";
                continue;
            }

            if ($translated === '') {
                $errors[] = "Block #{$entryNumber} ('{$original}') is missing a translation line (e.g. '(Jp):' or '(English):').";
                continue;
            }

            $entries[] = new TranslationEntry(
                null,
                null,
                $original,
                $translated,
                $romaji !== null && $romaji !== '' ? $romaji : null,
                $sortOrder++
            );
        }

        if (!empty($errors)) {
            $msg = 'Failed to parse ' . count($errors) . ' entry/entries. Please verify the format.';
            throw new ParseException($msg, $errors);
        }

        if (empty($entries)) {
            throw new ParseException('Could not parse any valid translation entries.');
        }

        return $entries;
    }
}
