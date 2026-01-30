<?php
/**
 * Admin - New Lesson Entry
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
$classes = getClasses($pdo);

$errorMessage = '';
$preselectedClass = (int)($_GET['class'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        die('Invalid security token. Please try again.');
    }
    $classId = (int)($_POST['class_id'] ?? 0);
    $lessonDate = $_POST['lesson_date'] ?? '';
    $lessonText = $_POST['lesson_text'] ?? '';

    if ($classId <= 0) {
        $errorMessage = 'Please select a class.';
    } elseif (empty($lessonDate)) {
        $errorMessage = 'Please select a date.';
    } elseif (empty(trim($lessonText))) {
        $errorMessage = 'Please enter lesson notes.';
    } else {
        try {
            $parser = new LessonParser();
            $entries = $parser->parse($lessonText);

            if (empty($entries)) {
                $errorMessage = 'Could not parse any translations from the text. Please check the format.';
            } else {
                $lessonId = getOrCreateLesson($pdo, $classId, $lessonDate);
                deleteTranslationsForLesson($pdo, $lessonId);
                $parser->saveEntries($pdo, $lessonId, $entries);

                header('Location: index.php?saved=1');
                exit;
            }
        } catch (Exception $e) {
            $errorMessage = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Lesson - Teacher Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
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
        h1 { font-size: 1.5rem; color: #333; }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #444;
        }
        select, input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }
        select:focus, input[type="date"]:focus, textarea:focus {
            outline: none;
            border-color: #4a90a4;
        }
        textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Consolas', 'Monaco', monospace;
            min-height: 300px;
            resize: vertical;
            line-height: 1.6;
        }
        .help-text {
            font-size: 0.85rem;
            color: #888;
            margin-top: 8px;
        }
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #4a90a4; color: white; }
        .btn-primary:hover { background: #3d7a8c; }
        .btn-secondary { background: #f0f0f0; color: #555; }
        .btn-secondary:hover { background: #e4e4e4; }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .format-guide {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .format-guide h3 {
            font-size: 1rem;
            margin-bottom: 12px;
            color: #555;
        }
        .format-guide pre {
            background: #fff;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            overflow-x: auto;
            border: 1px solid #eee;
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
            <h1>New Lesson Entry</h1>
        </header>

        <div class="card">
            <?php if ($errorMessage): ?>
                <div class="error"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_id">Class</label>
                        <select id="class_id" name="class_id" required>
                            <option value="">Select a class...</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= ($preselectedClass == $class['id'] || (isset($_POST['class_id']) && $_POST['class_id'] == $class['id'])) ? 'selected' : '' ?>>
                                    <?= e($class['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="lesson_date">Date</label>
                        <input type="date" id="lesson_date" name="lesson_date"
                               value="<?= e($_POST['lesson_date'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="lesson_text">Lesson Notes</label>
                    <textarea id="lesson_text" name="lesson_text" placeholder="Paste your lesson notes here..."><?= e($_POST['lesson_text'] ?? '') ?></textarea>
                    <p class="help-text">Paste the translations from your lesson. Each entry should start with "Original:" and include translations.</p>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Save Lesson</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <div class="format-guide">
                <h3>Accepted Format Examples</h3>
                <pre>Original: How are you today?
(Jp): 今日の調子はどうですか？
(Romaji): Kyou no choushi wa dou desu ka?

Original: Thank you very much
(Jp): どうもありがとうございます
(Romaji): Doumo arigatou gozaimasu

-------

Original: 食べる
(English): to eat

Original: beautiful
(Jp): 美しい</pre>
            </div>
        </div>
    </div>
    <script>
        // Default to user's local date if not already set (e.g. from POST return)
        window.addEventListener('load', () => {
            const dateInput = document.getElementById('lesson_date');
            if (!dateInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                dateInput.value = `${year}-${month}-${day}`;
            }
        });
    </script>
</body>
</html>
