<?php
/**
 * Student Portal - Class Archive Page
 * View all lesson dates for a class
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

$slug = $_GET['slug'] ?? '';
$class = getClassBySlug($pdo, $slug);

if (!$class) {
    header('Location: index.php');
    exit;
}

// Pagination settings
$perPage = 8;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalLessons = countLessonsForClass($pdo, $class['id']);
$pagination = getPaginationInfo($totalLessons, $currentPage, $perPage);
$currentPage = $pagination['current_page']; // Normalized
$lessons = getPaginatedLessonsForClass($pdo, $class['id'], $currentPage, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($class['class_name']) ?> - Lesson Notes</title>
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

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 24px;
            min-height: 100vh;
            min-height: 100dvh;
        }

        header {
            padding: 24px 0;
            position: sticky;
            top: 0;
            background: var(--paper-white);
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
            margin-bottom: 24px;
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

        .class-header {
            padding-bottom: 20px;
            border-bottom: 1px solid var(--paper-warm);
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.75rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            color: var(--ink-black);
            margin-bottom: 4px;
        }

        .lesson-count {
            font-size: 0.8125rem;
            color: var(--ink-light);
            font-weight: 300;
        }

        main {
            padding: 32px 0 40px;
        }

        .section-label {
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--ink-light);
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out 0.2s both;
        }

        .date-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .date-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            background: var(--paper-cream);
            border: 1px solid transparent;
            border-radius: 4px;
            padding: 20px 24px;
            position: relative;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            animation: slideUp 0.5s ease-out both;
        }

        .date-btn:nth-child(1) { animation-delay: 0.1s; }
        .date-btn:nth-child(2) { animation-delay: 0.15s; }
        .date-btn:nth-child(3) { animation-delay: 0.2s; }
        .date-btn:nth-child(4) { animation-delay: 0.25s; }
        .date-btn:nth-child(5) { animation-delay: 0.3s; }
        .date-btn:nth-child(6) { animation-delay: 0.35s; }
        .date-btn:nth-child(7) { animation-delay: 0.4s; }
        .date-btn:nth-child(8) { animation-delay: 0.45s; }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .date-btn::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--accent-vermillion);
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .date-btn:hover, .date-btn:focus {
            background: var(--paper-white);
            border-color: var(--paper-warm);
            box-shadow: 0 4px 20px var(--shadow-soft), 0 2px 6px var(--shadow-medium);
            transform: translateX(4px);
        }

        .date-btn:hover::before, .date-btn:focus::before {
            transform: scaleY(1);
        }

        .date-info {
            flex: 1;
        }

        .date-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ink-black);
            letter-spacing: 0.01em;
            margin-bottom: 2px;
        }

        .entry-count {
            font-size: 0.75rem;
            color: var(--ink-light);
            font-weight: 300;
        }

        .date-arrow {
            width: 20px;
            height: 20px;
            color: var(--ink-light);
            transition: all 0.3s ease;
        }

        .date-btn:hover .date-arrow {
            color: var(--accent-vermillion);
            transform: translateX(4px);
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

        @media (hover: none) {
            .date-btn:active {
                background: var(--paper-warm);
            }
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 32px;
            padding: 20px 0;
            animation: fadeIn 0.6s ease-out 0.4s both;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--paper-cream);
            border: 1px solid transparent;
            color: var(--ink-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .pagination-btn:hover:not(.disabled),
        .pagination-btn:focus:not(.disabled) {
            background: var(--paper-white);
            border-color: var(--paper-warm);
            box-shadow: 0 2px 12px var(--shadow-soft);
            color: var(--accent-vermillion);
        }

        .pagination-btn:active:not(.disabled) {
            transform: scale(0.95);
        }

        .pagination-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-btn svg {
            width: 20px;
            height: 20px;
        }

        .pagination-info {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.9375rem;
            color: var(--ink-light);
            min-width: 80px;
            text-align: center;
        }

        .pagination-info .current {
            font-weight: 600;
            color: var(--ink-black);
        }

        @media (min-width: 600px) {
            header {
                padding: 32px 0;
            }
            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="index.php" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                All Classes
            </a>

            <div class="class-header">
                <h1><?= e($class['class_name']) ?></h1>
                <p class="lesson-count"><?= $totalLessons ?> lesson<?= $totalLessons !== 1 ? 's' : '' ?> recorded</p>
            </div>
        </header>

        <main>
            <?php if (empty($lessons)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 2v6h6"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <line x1="10" y1="9" x2="8" y2="9"/>
                    </svg>
                    <p class="empty-text">No lessons recorded yet</p>
                </div>
            <?php else: ?>
                <p class="section-label">Lesson Archive</p>

                <div class="date-list">
                    <?php foreach ($lessons as $lesson): ?>
                    <a href="lesson.php?id=<?= $lesson['id'] ?>" class="date-btn">
                        <div class="date-info">
                            <div class="date-text"><?= e(formatDate($lesson['lesson_date'])) ?></div>
                            <div class="entry-count"><?= $lesson['translation_count'] ?> translation<?= $lesson['translation_count'] !== 1 ? 's' : '' ?></div>
                        </div>
                        <svg class="date-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="pagination" aria-label="Lesson navigation">
                    <a href="?slug=<?= e($slug) ?>&page=<?= $pagination['prev_page'] ?>"
                       class="pagination-btn <?= !$pagination['has_prev'] ? 'disabled' : '' ?>"
                       <?= !$pagination['has_prev'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                       aria-label="Previous page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </a>

                    <span class="pagination-info">
                        <span class="current"><?= $pagination['current_page'] ?></span> / <?= $pagination['total_pages'] ?>
                    </span>

                    <a href="?slug=<?= e($slug) ?>&page=<?= $pagination['next_page'] ?>"
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
        </main>
    </div>
</body>
</html>
