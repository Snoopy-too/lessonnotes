<?php
declare(strict_types=1);

/**
 * Admin - Edit Lesson
 */

require_once __DIR__ . '/../src/bootstrap.php';

use LessonNotes\Application\DTO\SaveLessonRequest;
use LessonNotes\Domain\Entities\TranslationEntry;
use LessonNotes\Domain\Services\ParseException;
use LessonNotes\Infrastructure\Security\SanitizeHelper;

SanitizeHelper::sendSecurityHeaders();

$container = appContainer();
$authGuard = $container->getAuthGuard();
$authGuard->requireLogin('index.php');

$lessonRepository = $container->getLessonRepository();
$classRepository = $container->getLessonClassRepository();

function e(?string $text): string
{
    return SanitizeHelper::escape($text);
}

$lessonId = (int)($_GET['id'] ?? 0);
$lesson = $lessonRepository->findById($lessonId);

if (!$lesson) {
    header('Location: index.php');
    exit;
}

$class = $classRepository->findById($lesson->getClassId());
$className = $class ? $class->getName() : 'Class';

$perPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalTranslations = $lessonRepository->countTranslationsForLesson($lessonId);
$totalPages = max(1, (int)ceil($totalTranslations / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

$errorMessage = '';
$errorDetails = [];
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$authGuard->validateCsrfToken()) {
        die('Invalid security token. Please try again.');
    }

    $action = $_POST['action'] ?? 'update_all';

    if ($action === 'update_all') {
        $lessonText = trim((string)($_POST['lesson_text'] ?? ''));

        if ($lessonText === '') {
            $errorMessage = 'Please enter lesson notes.';
        } else {
            try {
                $request = new SaveLessonRequest(
                    $lesson->getClassId(),
                    $lesson->getLessonDate(),
                    $lessonText,
                    $lessonId
                );
                $useCase = $container->getParseAndSaveLessonUseCase();
                $useCase->execute($request);

                header('Location: index.php?updated=1');
                exit;
            } catch (ParseException $e) {
                $errorMessage = $e->getMessage();
                $errorDetails = $e->getErrors();
            } catch (Throwable $e) {
                $errorMessage = 'An error occurred: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_single') {
        $translationId = (int)($_POST['translation_id'] ?? 0);
        $originalText = trim((string)($_POST['original_text'] ?? ''));
        $translatedText = trim((string)($_POST['translated_text'] ?? ''));
        $romajiText = trim((string)($_POST['romaji_text'] ?? ''));

        if ($translationId > 0 && $originalText !== '' && $translatedText !== '') {
            $entry = new TranslationEntry(
                $translationId,
                $lessonId,
                $originalText,
                $translatedText,
                $romajiText !== '' ? $romajiText : null
            );
            $lessonRepository->updateTranslation($entry);
            $successMessage = 'Translation updated!';
        }
    } elseif ($action === 'delete_single') {
        $translationId = (int)($_POST['translation_id'] ?? 0);
        if ($translationId > 0) {
            $lessonRepository->deleteTranslation($translationId, $lessonId);
            $successMessage = 'Translation deleted!';
        }
    } elseif ($action === 'add_single') {
        $originalText = trim((string)($_POST['original_text'] ?? ''));
        $translatedText = trim((string)($_POST['translated_text'] ?? ''));
        $romajiText = trim((string)($_POST['romaji_text'] ?? ''));

        if ($originalText !== '' && $translatedText !== '') {
            $nextOrder = $totalTranslations + 1;
            $entry = new TranslationEntry(
                null,
                $lessonId,
                $originalText,
                $translatedText,
                $romajiText !== '' ? $romajiText : null,
                $nextOrder
            );
            $lessonRepository->saveTranslation($entry);
            $successMessage = 'Translation added!';
        }
    }

    // Refresh translation metrics after mutations
    $totalTranslations = $lessonRepository->countTranslationsForLesson($lessonId);
    $totalPages = max(1, (int)ceil($totalTranslations / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
}

$allTranslations = $lessonRepository->getTranslationsForLesson($lessonId);
$translations = $lessonRepository->getTranslationsForLesson($lessonId, $perPage, $offset);
$startIndex = ($currentPage - 1) * $perPage;

// Use stored raw_text if present, or reconstruct
$existingText = $lesson->getRawText();
if (trim($existingText) === '') {
    $blocks = [];
    foreach ($allTranslations as $t) {
        $block = "Original: " . $t->getOriginalText() . "\n";
        $block .= "(Jp): " . $t->getTranslatedText();
        if ($t->hasRomaji()) {
            $block .= "\n(Romaji): " . $t->getRomajiText();
        }
        $blocks[] = $block;
    }
    $existingText = implode("\n\n-------\n\n", $blocks);
}

$formattedDate = date('F j, Y', strtotime($lesson->getLessonDate()));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson - Teacher Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }
        .back-link:hover { color: #4a90a4; }
        .back-link svg { width: 16px; height: 16px; }
        h1 { font-size: 1.5rem; color: #333; margin-bottom: 4px; }
        .lesson-meta { font-size: 0.9rem; color: #666; }

        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid #e0e0e0;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            font-size: 0.95rem;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
        }
        .tab:hover { color: #333; }
        .tab.active {
            color: #4a90a4;
        }
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #4a90a4;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .error-list {
            margin-top: 8px;
            padding-left: 20px;
        }
        .error-list li {
            margin-bottom: 4px;
            font-size: 0.85rem;
        }

        .form-group { margin-bottom: 16px; }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #444;
            font-size: 0.9rem;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
        }
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: #4a90a4;
        }
        textarea.large {
            min-height: 300px;
            font-family: 'Consolas', 'Monaco', monospace;
            line-height: 1.6;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: #4a90a4; color: white; }
        .btn-primary:hover { background: #3d7a8c; }
        .btn-secondary { background: #f0f0f0; color: #555; }
        .btn-secondary:hover { background: #e4e4e4; }
        .btn-danger { background: #fff; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-danger:hover { background: #ffebee; }
        .btn-success { background: #e8f5e9; color: #2e7d32; }
        .btn-success:hover { background: #c8e6c9; }
        .btn svg { width: 16px; height: 16px; }

        .translation-item {
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .translation-item:hover { background: #f5f5f5; }
        .translation-number {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 8px;
        }
        .translation-fields {
            display: grid;
            gap: 12px;
        }
        .translation-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e8e8e8;
        }

        .add-section {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .add-section h3 {
            font-size: 1rem;
            color: #555;
            margin-bottom: 16px;
        }

        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .help-text {
            font-size: 0.85rem;
            color: #888;
            margin-top: 8px;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px 0;
            margin: 20px 0;
            border-top: 1px solid #e8e8e8;
            border-bottom: 1px solid #e8e8e8;
        }
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: white;
            border: 1px solid #e0e0e0;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .pagination-btn:hover:not(.disabled) {
            background: #4a90a4;
            border-color: #4a90a4;
            color: white;
        }
        .pagination-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination-btn svg {
            width: 16px;
            height: 16px;
        }
        .pagination-info {
            font-size: 0.9rem;
            color: #666;
            padding: 0 16px;
        }
        .pagination-info .current {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="index.php" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
            </a>
            <h1>Edit Lesson</h1>
            <p class="lesson-meta"><?= e($className) ?> &mdash; <?= e($formattedDate) ?></p>
        </header>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="error">
                <strong><?= e($errorMessage) ?></strong>
                <?php if (!empty($errorDetails)): ?>
                    <ul class="error-list">
                        <?php foreach ($errorDetails as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" data-tab="individual">Edit Individual</button>
            <button class="tab" data-tab="bulk">Bulk Replace</button>
        </div>

        <!-- Individual Edit Tab -->
        <div class="tab-content active" id="tab-individual">
            <?php if (empty($translations)): ?>
                <div class="card">
                    <p style="color: #888; text-align: center; padding: 20px;">No translations yet. Add one below or use Bulk Replace.</p>
                </div>
            <?php else: ?>
                <?php foreach ($translations as $index => $t): ?>
                <form method="POST" class="translation-item">
                    <?= $authGuard->getCsrfField() ?>
                    <input type="hidden" name="action" value="update_single">
                    <input type="hidden" name="translation_id" value="<?= $t->getId() ?>">

                    <div class="translation-number">#<?= $startIndex + $index + 1 ?></div>

                    <div class="translation-fields">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Original</label>
                            <input type="text" name="original_text" value="<?= e($t->getOriginalText()) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Translation</label>
                            <input type="text" name="translated_text" value="<?= e($t->getTranslatedText()) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Romaji (optional)</label>
                            <input type="text" name="romaji_text" value="<?= e($t->getRomajiText() ?? '') ?>">
                        </div>
                    </div>

                    <div class="translation-actions">
                        <button type="submit" class="btn btn-success btn-sm">Save Changes</button>
                        <button type="submit" name="action" value="delete_single" class="btn btn-danger btn-sm" onclick="return confirm('Delete this translation?')">Delete</button>
                    </div>
                </form>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Translation navigation">
                    <a href="?id=<?= $lessonId ?>&page=<?= max(1, $currentPage - 1) ?>"
                       class="pagination-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                       <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       aria-label="Previous page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </a>

                    <span class="pagination-info">
                        Page <span class="current"><?= $currentPage ?></span> of <?= $totalPages ?>
                        (<?= $totalTranslations ?> total)
                    </span>

                    <a href="?id=<?= $lessonId ?>&page=<?= min($totalPages, $currentPage + 1) ?>"
                       class="pagination-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
                       <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       aria-label="Next page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </a>
                </nav>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Add New Translation -->
            <form method="POST" class="add-section">
                <?= $authGuard->getCsrfField() ?>
                <h3>Add New Translation</h3>
                <input type="hidden" name="action" value="add_single">

                <div class="translation-fields">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Original</label>
                        <input type="text" name="original_text" placeholder="Enter original text..." required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Translation</label>
                        <input type="text" name="translated_text" placeholder="Enter translation..." required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Romaji (optional)</label>
                        <input type="text" name="romaji_text" placeholder="Enter romaji...">
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Add Translation</button>
                </div>
            </form>
        </div>

        <!-- Bulk Replace Tab -->
        <div class="tab-content" id="tab-bulk">
            <div class="card">
                <form method="POST">
                    <?= $authGuard->getCsrfField() ?>
                    <input type="hidden" name="action" value="update_all">

                    <div class="form-group">
                        <label>Lesson Notes</label>
                        <textarea name="lesson_text" class="large" placeholder="Paste your lesson notes here..."><?= e(trim($existingText)) ?></textarea>
                        <p class="help-text">This will replace ALL existing translations with the parsed content.</p>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('This will replace all existing translations. Continue?')">Replace All Translations</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>
