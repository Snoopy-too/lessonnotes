<?php
/**
 * Student Portal - Lesson View Page
 * View translations for a specific lesson date
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

$lessonId = (int)($_GET['id'] ?? 0);
$lesson = getLessonById($pdo, $lessonId);

if (!$lesson) {
    header('Location: index.php');
    exit;
}

// Get all translations for this lesson (no pagination)
$translations = getTranslationsForLesson($pdo, $lessonId);
$totalTranslations = count($translations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e(formatDate($lesson['lesson_date'])) ?> - <?= e($lesson['class_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink-black: #1a1a1a;
            --ink-gray: #4a4a4a;
            --ink-light: #8a8a8a;
            --paper-white: #faf9f7;
            --paper-cream: #f5f3ef;
            --paper-warm: #ebe8e2;
            --accent-vermillion: #c73e3a;
            --accent-indigo: #3d5a80;
            --shadow-soft: rgba(26, 26, 26, 0.06);
            --shadow-medium: rgba(26, 26, 26, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--paper-white);
            color: var(--ink-black);
            min-height: 100vh;
            min-height: 100dvh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%' height='100%' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
            z-index: 1000;
        }

        /* Decorative vertical line inspired by Japanese scrolls */
        .scroll-line {
            position: fixed;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 1px;
            background: linear-gradient(
                to bottom,
                transparent 0%,
                var(--paper-warm) 10%,
                var(--paper-warm) 90%,
                transparent 100%
            );
            pointer-events: none;
            z-index: -1;
        }

        @media (max-width: 600px) {
            .scroll-line {
                display: none;
            }
        }

        .container {
            max-width: 540px;
            margin: 0 auto;
            padding: 0 24px;
            min-height: 100vh;
            min-height: 100dvh;
        }

        header {
            padding: 24px 0 20px;
            position: sticky;
            top: 0;
            background: linear-gradient(to bottom, var(--paper-white) 80%, transparent);
            z-index: 100;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--ink-light);
            font-size: 0.8125rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: color 0.2s ease;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: var(--accent-indigo);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .back-link:hover svg {
            transform: translateX(-3px);
        }

        .lesson-header {
            padding-bottom: 16px;
        }

        .class-name {
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--accent-indigo);
            margin-bottom: 6px;
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.625rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            color: var(--ink-black);
            margin-bottom: 4px;
        }

        .translation-count {
            font-size: 0.8125rem;
            color: var(--ink-light);
            font-weight: 300;
        }

        main {
            padding: 20px 0 60px;
        }

        .translation-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .translation-card {
            background: var(--paper-cream);
            border-radius: 6px;
            padding: 24px;
            position: relative;
            animation: cardSlide 0.6s ease-out both;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .translation-card:nth-child(1) { animation-delay: 0.1s; }
        .translation-card:nth-child(2) { animation-delay: 0.15s; }
        .translation-card:nth-child(3) { animation-delay: 0.2s; }
        .translation-card:nth-child(4) { animation-delay: 0.25s; }
        .translation-card:nth-child(5) { animation-delay: 0.3s; }
        .translation-card:nth-child(6) { animation-delay: 0.35s; }
        .translation-card:nth-child(7) { animation-delay: 0.4s; }
        .translation-card:nth-child(8) { animation-delay: 0.45s; }
        .translation-card:nth-child(9) { animation-delay: 0.5s; }
        .translation-card:nth-child(10) { animation-delay: 0.55s; }

        @keyframes cardSlide {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .translation-card:hover {
            border-left-color: var(--accent-vermillion);
            box-shadow: 0 4px 16px var(--shadow-soft);
        }

        .card-number {
            position: absolute;
            top: 12px;
            right: 16px;
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.875rem;
            font-style: italic;
            color: var(--ink-light);
            opacity: 0.6;
        }

        .original-section {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--paper-warm);
        }

        .field-label {
            font-size: 0.625rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--ink-light);
            margin-bottom: 6px;
        }

        .original-text {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 1.125rem;
            font-weight: 400;
            color: var(--ink-black);
            line-height: 1.6;
            word-break: break-word;
        }

        .translation-section {
            margin-bottom: 12px;
        }

        .translation-section:last-child {
            margin-bottom: 0;
        }

        .translated-text {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 1.0625rem;
            font-weight: 400;
            color: var(--ink-gray);
            line-height: 1.7;
            word-break: break-word;
        }

        .romaji-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var(--paper-warm);
        }

        .romaji-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            font-style: italic;
            color: var(--ink-light);
            line-height: 1.6;
            letter-spacing: 0.01em;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeIn 0.6s ease-out 0.3s both;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            color: var(--paper-warm);
        }

        .empty-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.125rem;
            font-style: italic;
            color: var(--ink-light);
        }

        /* Progress indicator */
        .progress-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--paper-warm);
            z-index: 200;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent-indigo);
            width: 0%;
            transition: width 0.1s ease-out;
        }

        @media (min-width: 600px) {
            header {
                padding: 32px 0 24px;
            }
            h1 {
                font-size: 1.875rem;
            }
            .translation-card {
                padding: 28px 32px;
            }
            .original-text {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="scroll-line"></div>

    <div class="container">
        <header>
            <a href="class.php?slug=<?= e($lesson['class_slug']) ?>" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Archive
            </a>

            <div class="lesson-header">
                <p class="class-name"><?= e($lesson['class_name']) ?></p>
                <h1><?= e(formatDate($lesson['lesson_date'])) ?></h1>
                <p class="translation-count"><?= $totalTranslations ?> translation<?= $totalTranslations !== 1 ? 's' : '' ?></p>
            </div>
        </header>

        <main>
            <?php if (empty($translations)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <p class="empty-text">No translations found</p>
                </div>
            <?php else: ?>
                <div class="translation-list">
                    <?php foreach ($translations as $index => $translation): ?>
                    <article class="translation-card">
                        <span class="card-number"><?= $index + 1 ?></span>

                        <div class="original-section">
                            <p class="field-label">Original</p>
                            <p class="original-text"><?= e($translation['original_text']) ?></p>
                        </div>

                        <div class="translation-section">
                            <p class="field-label">Translation</p>
                            <p class="translated-text"><?= e($translation['translated_text']) ?></p>
                        </div>

                        <?php if (!empty($translation['romaji_text'])): ?>
                        <div class="romaji-section">
                            <p class="field-label">Romaji</p>
                            <p class="romaji-text"><?= e($translation['romaji_text']) ?></p>
                        </div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
    </div>

    <script>
        // Scroll progress indicator
        const progressFill = document.getElementById('progressFill');

        function updateProgress() {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            progressFill.style.width = progress + '%';
        }

        window.addEventListener('scroll', updateProgress, { passive: true });
        updateProgress();
    </script>
</body>
</html>
