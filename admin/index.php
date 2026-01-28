<?php
/**
 * Admin Dashboard - Main Hub
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

initSecureSession();
$pdo = getDBConnection();

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
    header('Location: index.php');
    exit;
}

// Handle login
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['action'])) {
    if (adminLogin($_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Invalid password. Please try again.';
    }
}

// Check if logged in
if (!isAdminLoggedIn()) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Teacher Login - Eikaiwa Lesson Portal</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f5f5;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-card {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            h1 { font-size: 1.5rem; margin-bottom: 8px; color: #333; }
            .subtitle { color: #666; margin-bottom: 30px; font-size: 0.9rem; }
            .form-group { margin-bottom: 20px; }
            label { display: block; font-weight: 500; margin-bottom: 8px; color: #444; }
            input[type="password"] {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 1rem;
                transition: border-color 0.2s;
            }
            input[type="password"]:focus { outline: none; border-color: #4a90a4; }
            button {
                width: 100%;
                padding: 14px;
                background: #4a90a4;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }
            button:hover { background: #3d7a8c; }
            .error {
                background: #fee;
                color: #c00;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 0.9rem;
            }
            .back-link {
                display: block;
                text-align: center;
                margin-top: 20px;
                color: #666;
                text-decoration: none;
                font-size: 0.9rem;
            }
            .back-link:hover { color: #4a90a4; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h1>Teacher Dashboard</h1>
            <p class="subtitle">Enter your password to manage lessons</p>
            <?php if ($loginError): ?>
                <div class="error"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit">Login</button>
            </form>
            <a href="../index.php" class="back-link">Back to Student Portal</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCsrfToken()) {
        die('Invalid security token. Please try again.');
    }
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    if ($lessonId > 0) {
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->execute([$lessonId]);
        header('Location: index.php?deleted=1');
        exit;
    }
}

// Handle password change
$passwordError = '';
$passwordSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validateCsrfToken()) {
        die('Invalid security token. Please try again.');
    }
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $result = changeAdminPassword($currentPassword, $newPassword, $confirmPassword);
    if ($result === true) {
        header('Location: index.php?password_changed=1');
        exit;
    } else {
        $passwordError = $result;
    }
}

// Pagination settings
$perPage = 10;

// Get all lessons grouped by class with pagination
$classes = getClasses($pdo);
$lessonsByClass = [];
foreach ($classes as $class) {
    $pageKey = 'page_' . $class['id'];
    $currentPage = max(1, (int)($_GET[$pageKey] ?? 1));
    $totalLessons = countLessonsForClass($pdo, $class['id']);
    $pagination = getPaginationInfo($totalLessons, $currentPage, $perPage);

    $lessonsByClass[$class['id']] = [
        'class' => $class,
        'lessons' => getPaginatedLessonsForClass($pdo, $class['id'], $pagination['current_page'], $perPage),
        'total_lessons' => $totalLessons,
        'pagination' => $pagination
    ];
}

$successMessage = $_GET['saved'] ?? null ? 'Lesson saved successfully!' : null;
$successMessage = $_GET['deleted'] ?? null ? 'Lesson deleted successfully!' : $successMessage;
$successMessage = $_GET['updated'] ?? null ? 'Lesson updated successfully!' : $successMessage;
$successMessage = $_GET['password_changed'] ?? null ? 'Password changed successfully!' : $successMessage;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Eikaiwa Lesson Portal</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
            gap: 16px;
        }
        h1 { font-size: 1.5rem; color: #333; }
        .header-links { display: flex; gap: 16px; align-items: center; }
        .header-links a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .header-links a:hover { color: #4a90a4; }

        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #4a90a4;
            color: white;
        }
        .btn-primary:hover { background: #3d7a8c; }
        .btn svg { width: 18px; height: 18px; }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success svg { width: 20px; height: 20px; flex-shrink: 0; }

        .class-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .class-header {
            background: #fafafa;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .class-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        .lesson-count {
            font-size: 0.85rem;
            color: #888;
        }

        .lesson-list { padding: 0; }
        .lesson-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            gap: 16px;
        }
        .lesson-item:last-child { border-bottom: none; }
        .lesson-item:hover { background: #fafafa; }

        .lesson-info { flex: 1; }
        .lesson-date {
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }
        .lesson-meta {
            font-size: 0.85rem;
            color: #888;
        }

        .lesson-actions {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #555;
        }
        .btn-secondary:hover { background: #e4e4e4; }
        .btn-danger {
            background: #fff;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .btn-danger:hover { background: #ffebee; }
        .btn-view {
            background: #e3f2fd;
            color: #1565c0;
        }
        .btn-view:hover { background: #bbdefb; }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #888;
        }
        .empty-state p { margin-bottom: 16px; }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 20px;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
        }
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
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
            font-size: 0.85rem;
            color: #666;
            padding: 0 12px;
        }
        .pagination-info .current {
            font-weight: 600;
            color: #333;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
        }
        .modal h3 { margin-bottom: 12px; color: #333; }
        .modal p { color: #666; margin-bottom: 20px; font-size: 0.95rem; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .modal .form-group { margin-bottom: 16px; }
        .modal .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #444;
            font-size: 0.9rem;
        }
        .modal .form-group input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .modal .form-group input[type="password"]:focus {
            outline: none;
            border-color: #4a90a4;
        }
        .modal .form-hint {
            display: block;
            font-size: 0.8rem;
            color: #888;
            margin-top: 4px;
        }
        .modal-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Teacher Dashboard</h1>
            <div class="header-links">
                <a href="../index.php">Student Portal</a>
                <a href="#" onclick="openPasswordModal(); return false;">Change Password</a>
                <a href="?logout=1">Logout</a>
            </div>
        </header>

        <?php if ($successMessage): ?>
        <div class="success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <?= e($successMessage) ?>
        </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="new.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New Lesson Entry
            </a>
        </div>

        <?php foreach ($lessonsByClass as $data): ?>
        <div class="class-section">
            <div class="class-header">
                <span class="class-title"><?= e($data['class']['class_name']) ?></span>
                <span class="lesson-count"><?= $data['total_lessons'] ?> lesson<?= $data['total_lessons'] !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($data['lessons'])): ?>
            <div class="empty-state">
                <p>No lessons recorded yet</p>
                <a href="new.php?class=<?= $data['class']['id'] ?>" class="btn btn-secondary btn-sm">Add First Lesson</a>
            </div>
            <?php else: ?>
            <div class="lesson-list">
                <?php foreach ($data['lessons'] as $lesson): ?>
                <div class="lesson-item">
                    <div class="lesson-info">
                        <div class="lesson-date"><?= e(formatDate($lesson['lesson_date'])) ?></div>
                        <div class="lesson-meta"><?= $lesson['translation_count'] ?> translation<?= $lesson['translation_count'] != 1 ? 's' : '' ?></div>
                    </div>
                    <div class="lesson-actions">
                        <a href="../lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-view" target="_blank">View</a>
                        <a href="edit.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $lesson['id'] ?>, '<?= e(formatDate($lesson['lesson_date'])) ?>')">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($data['pagination']['total_pages'] > 1): ?>
            <?php
                // Build URL with all existing pagination params preserved
                $classId = $data['class']['id'];
                $pageKey = 'page_' . $classId;
                $baseParams = $_GET;
            ?>
            <nav class="pagination" aria-label="<?= e($data['class']['class_name']) ?> lesson navigation">
                <?php
                    $prevParams = $baseParams;
                    $prevParams[$pageKey] = $data['pagination']['prev_page'];
                ?>
                <a href="?<?= http_build_query($prevParams) ?>"
                   class="pagination-btn <?= !$data['pagination']['has_prev'] ? 'disabled' : '' ?>"
                   <?= !$data['pagination']['has_prev'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                   aria-label="Previous page">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </a>

                <span class="pagination-info">
                    Page <span class="current"><?= $data['pagination']['current_page'] ?></span> of <?= $data['pagination']['total_pages'] ?>
                </span>

                <?php
                    $nextParams = $baseParams;
                    $nextParams[$pageKey] = $data['pagination']['next_page'];
                ?>
                <a href="?<?= http_build_query($nextParams) ?>"
                   class="pagination-btn <?= !$data['pagination']['has_next'] ? 'disabled' : '' ?>"
                   <?= !$data['pagination']['has_next'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                   aria-label="Next page">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </a>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Delete Lesson?</h3>
            <p>Are you sure you want to delete the lesson from <strong id="deleteDate"></strong>? This will remove all translations and cannot be undone.</p>
            <form method="POST" id="deleteForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="lesson_id" id="deleteLessonId">
                <div class="modal-actions">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="passwordModal"<?= $passwordError ? ' style="display:flex"' : '' ?>>
        <div class="modal" style="max-width: 420px;">
            <h3>Change Password</h3>
            <?php if ($passwordError): ?>
            <div class="modal-error"><?= e($passwordError) ?></div>
            <?php endif; ?>
            <form method="POST" id="passwordForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                    <small class="form-hint">Minimum 8 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(lessonId, date) {
            document.getElementById('deleteLessonId').value = lessonId;
            document.getElementById('deleteDate').textContent = date;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        function openPasswordModal() {
            document.getElementById('passwordModal').style.display = 'flex';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordForm').reset();
        }

        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) closePasswordModal();
        });
    </script>
</body>
</html>
