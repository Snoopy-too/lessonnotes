<?php
/**
 * Student Portal - Landing Page
 * Select your class
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$classes = getClasses($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Lesson Notes - Eikaiwa Portal</title>
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
            overflow-x: hidden;
        }

        /* Subtle paper texture overlay */
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

        /* Ink wash gradient decoration */
        .ink-wash {
            position: fixed;
            top: -20%;
            right: -10%;
            width: 60vw;
            height: 60vw;
            background: radial-gradient(ellipse at center,
                rgba(61, 90, 128, 0.04) 0%,
                rgba(61, 90, 128, 0.02) 40%,
                transparent 70%);
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 24px;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        header {
            padding: 60px 0 48px;
            text-align: center;
            animation: fadeDown 0.8s ease-out;
        }

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-mark {
            width: 48px;
            height: 48px;
            margin: 0 auto 24px;
            position: relative;
        }

        .logo-mark::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 2px solid var(--ink-black);
            border-radius: 50%;
        }

        .logo-mark::after {
            content: '言';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Noto Serif JP', serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--ink-black);
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            margin-bottom: 8px;
            color: var(--ink-black);
        }

        .subtitle {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 0.8125rem;
            font-weight: 300;
            color: var(--ink-light);
            letter-spacing: 0.1em;
        }

        .class-selection {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding-bottom: 40px;
        }

        .section-label {
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--ink-light);
            margin-bottom: 20px;
            padding-left: 4px;
            animation: fadeIn 0.6s ease-out 0.2s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .class-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .class-btn {
            display: block;
            text-decoration: none;
            background: var(--paper-cream);
            border: 1px solid transparent;
            border-radius: 4px;
            padding: 28px 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            animation: slideUp 0.6s ease-out both;
        }

        .class-btn:nth-child(1) { animation-delay: 0.3s; }
        .class-btn:nth-child(2) { animation-delay: 0.4s; }
        .class-btn:nth-child(3) { animation-delay: 0.5s; }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .class-btn::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--accent-indigo);
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .class-btn:hover, .class-btn:focus {
            background: var(--paper-white);
            border-color: var(--paper-warm);
            box-shadow: 0 8px 32px var(--shadow-soft), 0 2px 8px var(--shadow-medium);
            transform: translateX(4px);
        }

        .class-btn:hover::before, .class-btn:focus::before {
            transform: scaleY(1);
        }

        .class-btn:active {
            transform: translateX(4px) scale(0.99);
        }

        .class-name {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--ink-black);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .class-day {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.875rem;
            font-style: italic;
            color: var(--ink-gray);
            font-weight: 400;
        }

        .class-arrow {
            margin-left: auto;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-light);
            transition: all 0.3s ease;
        }

        .class-btn:hover .class-arrow {
            color: var(--accent-indigo);
            transform: translateX(4px);
        }

        footer {
            padding: 32px 0;
            text-align: center;
            border-top: 1px solid var(--paper-warm);
            animation: fadeIn 0.6s ease-out 0.8s both;
        }

        .footer-link {
            font-size: 0.75rem;
            color: var(--ink-light);
            text-decoration: none;
            letter-spacing: 0.05em;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: var(--accent-vermillion);
        }

        /* Touch feedback for mobile */
        @media (hover: none) {
            .class-btn:active {
                background: var(--paper-warm);
            }
        }

        @media (min-width: 600px) {
            header {
                padding: 80px 0 60px;
            }
            h1 {
                font-size: 2.5rem;
            }
            .class-btn {
                padding: 32px 28px;
            }
        }
    </style>
</head>
<body>
    <div class="ink-wash"></div>

    <div class="container">
        <header>
            <div class="logo-mark"></div>
            <h1>Lesson Notes</h1>
            <p class="subtitle">レッスンノート</p>
        </header>

        <main class="class-selection">
            <p class="section-label">Select Your Class</p>

            <div class="class-list">
                <?php foreach ($classes as $class): ?>
                <a href="class.php?slug=<?= e($class['slug']) ?>" class="class-btn">
                    <div class="class-name">
                        <span><?= e($class['class_name']) ?></span>
                        <span class="class-arrow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </main>

        <footer>
            <a href="admin/" class="footer-link">Teacher Access</a>
        </footer>
    </div>
</body>
</html>
