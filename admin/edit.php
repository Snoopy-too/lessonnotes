<?php
/**
 * Admin - Edit Lesson
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/LessonParser.php';

initSecureSession();

if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

$lessonId = (int)($_GET['id'] ?? 0);
$lesson = getLessonById($pdo, $lessonId);

if (!$lesson) {
    header('Location: index.php');
    exit;
}

// Pagination settings for individual edit view
$perPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalTranslations = countTranslationsForLesson($pdo, $lessonId);
$pagination = getPaginationInfo($totalTranslations, $currentPage, $perPage);
$currentPage = $pagination['current_page']; // Normalized

// Get all translations for bulk edit textarea
$allTranslations = getTranslationsForLesson($pdo, $lessonId);
// Get paginated translations for individual edit view
$translations = getPaginatedTranslationsForLesson($pdo, $lessonId, $currentPage, $perPage);

// Calculate starting index for display
$startIndex = ($currentPage - 1) * $perPage;

$classes = getClasses($pdo);

$errorMessage = '';
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        die('Invalid security token. Please try again.');
    }
    $action = $_POST['action'] ?? 'update_all';

    if ($action === 'update_all') {
        // Re-parse and replace all translations
        $lessonText = $_POST['lesson_text'] ?? '';

        if (empty(trim($lessonText))) {
            $errorMessage = 'Please enter lesson notes.';
        } else {
            try {
                $parser = new LessonParser();
                $entries = $parser->parse($lessonText);

                if (empty($entries)) {
                    $errorMessage = 'Could not parse any translations from the text.';
                } else {
                    deleteTranslationsForLesson($pdo, $lessonId);
                    $parser->saveEntries($pdo, $lessonId, $entries);

                    header('Location: index.php?updated=1');
                    exit;
                }
            } catch (Exception $e) {
                $errorMessage = 'An error occurred: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_single') {
        // Update a single translation
        $translationId = (int)($_POST['translation_id'] ?? 0);
        $originalText = trim($_POST['original_text'] ?? '');
        $translatedText = trim($_POST['translated_text'] ?? '');
        $romajiText = trim($_POST['romaji_text'] ?? '');

        if ($translationId > 0 && !empty($originalText) && !empty($translatedText)) {
            $stmt = $pdo->prepare("UPDATE translations SET original_text = ?, translated_text = ?, romaji_text = ? WHERE id = ? AND lesson_id = ?");
            $stmt->execute([$originalText, $translatedText, $romajiText ?: null, $translationId, $lessonId]);
            $successMessage = 'Translation updated!';
            // Refresh translations
            $totalTranslations = countTranslationsForLesson($pdo, $lessonId);
            $pagination = getPaginationInfo($totalTranslations, $currentPage, $perPage);
            $allTranslations = getTranslationsForLesson($pdo, $lessonId);
            $translations = getPaginatedTranslationsForLesson($pdo, $lessonId, $pagination['current_page'], $perPage);
        }
    } elseif ($action === 'delete_single') {
        // Delete a single translation
        $translationId = (int)($_POST['translation_id'] ?? 0);
        if ($translationId > 0) {
            $stmt = $pdo->prepare("DELETE FROM translations WHERE id = ? AND lesson_id = ?");
            $stmt->execute([$translationId, $lessonId]);
            $successMessage = 'Translation deleted!';
            // Refresh translations
            $totalTranslations = countTranslationsForLesson($pdo, $lessonId);
            $pagination = getPaginationInfo($totalTranslations, $currentPage, $perPage);
            $allTranslations = getTranslationsForLesson($pdo, $lessonId);
            $translations = getPaginatedTranslationsForLesson($pdo, $lessonId, $pagination['current_page'], $perPage);
        }
    } elseif ($action === 'add_single') {
        // Add a new translation
        $originalText = trim($_POST['original_text'] ?? '');
        $translatedText = trim($_POST['translated_text'] ?? '');
        $romajiText = trim($_POST['romaji_text'] ?? '');

        if (!empty($originalText) && !empty($translatedText)) {
            // Get max sort order
            $maxOrder = $pdo->prepare("SELECT MAX(sort_order) FROM translations WHERE lesson_id = ?");
            $maxOrder->execute([$lessonId]);
            $nextOrder = ($maxOrder->fetchColumn() ?? 0) + 1;

            $stmt = $pdo->prepare("INSERT INTO translations (lesson_id, original_text, translated_text, romaji_text, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$lessonId, $originalText, $translatedText, $romajiText ?: null, $nextOrder]);
            $successMessage = 'Translation added!';
            // Refresh translations
            $totalTranslations = countTranslationsForLesson($pdo, $lessonId);
            $pagination = getPaginationInfo($totalTranslations, $currentPage, $perPage);
            $allTranslations = getTranslationsForLesson($pdo, $lessonId);
            $translations = getPaginatedTranslationsForLesson($pdo, $lessonId, $pagination['current_page'], $perPage);
        }
    }
}

// Convert existing translations back to paste format for the textarea
$existingText = '';
foreach ($allTranslations as $t) {
    $existingText .= "Original: " . $t['original_text'] . "\n";
    $existingText .= "(Jp): " . $t['translated_text'] . "\n";
    if (!empty($t['romaji_text'])) {
        $existingText .= "(Romaji): " . $t['romaji_text'] . "\n";
    }
    $existingText .= "\n";
}
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
            <p class="lesson-meta"><?= e($lesson['class_name']) ?> &mdash; <?= e(formatDate($lesson['lesson_date'])) ?></p>
        </header>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
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
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_single">
                    <input type="hidden" name="translation_id" value="<?= $t['id'] ?>">

                    <div class="translation-number">#<?= $startIndex + $index + 1 ?></div>

                    <div class="translation-fields">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Original</label>
                            <input type="text" name="original_text" value="<?= e($t['original_text']) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Translation</label>
                            <input type="text" name="translated_text" value="<?= e($t['translated_text']) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Romaji (optional)</label>
                            <input type="text" name="romaji_text" value="<?= e($t['romaji_text'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="translation-actions">
                        <button type="submit" class="btn btn-success btn-sm">Save Changes</button>
                        <button type="submit" name="action" value="delete_single" class="btn btn-danger btn-sm" onclick="return confirm('Delete this translation?')">Delete</button>
                    </div>
                </form>
                <?php endforeach; ?>

                <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="pagination" aria-label="Translation navigation">
                    <a href="?id=<?= $lessonId ?>&page=<?= $pagination['prev_page'] ?>"
                       class="pagination-btn <?= !$pagination['has_prev'] ? 'disabled' : '' ?>"
                       <?= !$pagination['has_prev'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       aria-label="Previous page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </a>

                    <span class="pagination-info">
                        Page <span class="current"><?= $pagination['current_page'] ?></span> of <?= $pagination['total_pages'] ?>
                        (<?= $totalTranslations ?> total)
                    </span>

                    <a href="?id=<?= $lessonId ?>&page=<?= $pagination['next_page'] ?>"
                       class="pagination-btn <?= !$pagination['has_next'] ? 'disabled' : '' ?>"
                       <?= !$pagination['has_next'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
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
                <?= csrfField() ?>
                <h3>Add New Translation</h3>
                <input type="hidden" name="action" value="add_single">

                <div class="translation-fields">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Original</label>
                        <input type="text" name="original_text" placeholder="Enter original text...">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Translation</label>
                        <input type="text" name="translated_text" placeholder="Enter translation...">
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
                    <?= csrfField() ?>
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
