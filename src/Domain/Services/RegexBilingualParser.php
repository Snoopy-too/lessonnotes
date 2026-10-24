<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Services;

use LessonNotes\Domain\Entities\TranslationEntry;
use Override;

/**
 * Modern regex-based parser for bilingual lesson notes text.
 * Utilizes PHP 8.5 idioms: array_find, array_any, array_all, and #[Override].
 *
 * Supported formats:
 * - Format A (English to Japanese + optional Romaji)
 * - Format B (Japanese to English)
 * - Format C (Vocabulary / short phrases e.g. (English/Jp): ...)
 */
class RegexBilingualParser implements ParserInterface
{
    /**
     * @return TranslationEntry[]
     * @throws ParseException
     */
    #[Override]
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

            // Explicit start of a new record via "Original:" / "Orig:"
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

        // Filter out empty blocks using array_any()
        $validBlocks = array_values(array_filter(
            $rawBlocks,
            fn(array $block): bool => array_any($block, fn(string $line): bool => trim($line) !== '')
        ));

        if (empty($validBlocks)) {
            throw new ParseException('No readable text blocks found in the input.');
        }

        $entries = [];
        $errors = [];
        $sortOrder = 1;

        foreach ($validBlocks as $blockIndex => $blockLines) {
            $entryNumber = $blockIndex + 1;

            // 1. Locate Original line using array_find()
            $origLine = array_find(
                $blockLines,
                fn(string $line): bool => (bool)preg_match('/^(?:Original|Orig)\s*:\s*(.+)$/iu', $line)
            );

            $original = '';
            if ($origLine !== null && preg_match('/^(?:Original|Orig)\s*:\s*(.+)$/iu', $origLine, $matches)) {
                $original = trim($matches[1]);
            } else {
                // Fallback: First line without a known translation/header prefix
                $fallbackLine = array_find(
                    $blockLines,
                    fn(string $line): bool => !preg_match('/^[\w\(\)\/-]+:/u', $line) && trim($line) !== ''
                );
                if ($fallbackLine !== null) {
                    $original = trim($fallbackLine);
                }
            }

            // 2. Locate Romaji line using array_find()
            $romajiLine = array_find(
                $blockLines,
                fn(string $line): bool => (bool)preg_match('/^(?:\((?:Romaji|Roma-ji)\)|Romaji)\s*:\s*(.+)$/iu', $line)
            );

            $romaji = null;
            if ($romajiLine !== null && preg_match('/^(?:\((?:Romaji|Roma-ji)\)|Romaji)\s*:\s*(.+)$/iu', $romajiLine, $matches)) {
                $romaji = trim($matches[1]);
            }

            // 3. Locate Japanese translation using array_find()
            $jpLine = array_find(
                $blockLines,
                fn(string $line): bool => (bool)preg_match('/^(?:\((?:Jp|Japanese|JP|日本語)\)|Jp|Japanese)\s*:\s*(.+)$/iu', $line)
            );

            $jp = null;
            if ($jpLine !== null && preg_match('/^(?:\((?:Jp|Japanese|JP|日本語)\)|Jp|Japanese)\s*:\s*(.+)$/iu', $jpLine, $matches)) {
                $jp = trim($matches[1]);
            }

            // 4. Locate English translation using array_find()
            $englishLine = array_find(
                $blockLines,
                fn(string $line): bool => (bool)preg_match('/^(?:\((?:English|Eng|英語)\)|English|Eng)\s*:\s*(.+)$/iu', $line)
            );

            $english = null;
            if ($englishLine !== null && preg_match('/^(?:\((?:English|Eng|英語)\)|English|Eng)\s*:\s*(.+)$/iu', $englishLine, $matches)) {
                $english = trim($matches[1]);
            }

            // 5. Locate generic parenthetical translation (e.g. Format C (English/Jp): or (Translation):)
            $genericLine = array_find(
                $blockLines,
                fn(string $line): bool => (bool)preg_match('/^\(([^)]+)\)\s*:\s*(.+)$/iu', $line) &&
                                         !preg_match('/^\((?:Romaji|Roma-ji)\)/iu', $line)
            );

            $genericTranslation = null;
            if ($genericLine !== null && preg_match('/^\(([^)]+)\)\s*:\s*(.+)$/iu', $genericLine, $matches)) {
                $label = strtolower(trim($matches[1]));
                $value = trim($matches[2]);

                if ($label === 'romaji' || $label === 'roma-ji') {
                    $romaji ??= $value;
                } elseif (str_contains($label, 'jp') || str_contains($label, 'japanese')) {
                    $jp ??= $value;
                } elseif (str_contains($label, 'en') || str_contains($label, 'english')) {
                    $english ??= $value;
                } else {
                    $genericTranslation = $value;
                }
            }

            // Resolve primary translation
            $translated = $jp ?? $english ?? $genericTranslation ?? '';

            // Mandatory Validation: Both original and translated text must exist before producing entity
            if ($original === '' && $translated === '') {
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

            // Construct TranslationEntry value object only after validation passes
            $entries[] = new TranslationEntry(
                null,
                null,
                $original,
                $translated,
                $romaji !== null && trim($romaji) !== '' ? $romaji : null,
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

        // Final stream verification using array_all()
        $allValid = array_all(
            $entries,
            fn(TranslationEntry $entry): bool => trim($entry->originalText) !== '' && trim($entry->translatedText) !== ''
        );

        if (!$allValid) {
            throw new ParseException('Validation failed: one or more entries contain empty original or translated text.');
        }

        return $entries;
    }
}
