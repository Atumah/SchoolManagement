<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireAuth();

$currentUser = getCurrentUser();
$isStudent = $currentUser['role'] === 'Student';
$isTeacherOrAdmin = hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer']);

$flash = getFlashMessage();

// Handle URL parameters
$viewingStudentId = $_GET['student'] ?? null;
$studentSearch = $_GET['search'] ?? '';

// For teachers/admins: Show student list if no student selected
if (!$isStudent && !$viewingStudentId) {
    $allStudents = getAllStudentsForProgress();

    // Filter students by search
    if ($studentSearch) {
        $allStudents = array_filter($allStudents, static function ($student) use ($studentSearch) {
            return stripos($student['name'] ?? '', $studentSearch) !== false ||
                   stripos($student['email'] ?? '', $studentSearch) !== false;
        });
    }
}

// For students or when viewing a specific student
$studentId = $isStudent ? $currentUser['id'] : ($viewingStudentId ?? null);
$studentProgress = $studentId ? getStudentProgressSummary($studentId) : null;
$viewingStudent = $studentId ? getUserById($studentId) : null;

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?= $isStudent ? 'My Progress' : ($viewingStudent ? htmlspecialchars($viewingStudent['name']) . '\'s Progress' : 'Student Progress') ?> - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>

        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($isStudent || $viewingStudent) : ?>
            <!-- Student Progress Bar View -->
            <div class="page-header">
                <h1><?= $isStudent ? 'My Progress' : htmlspecialchars($viewingStudent['name'] . '\'s Progress') ?></h1>
            </div>

            <div class="progress-container">
                <?php for ($year = 1; $year <= 4; $year++) : ?>
                    <?php
                    $yearData = $studentProgress[$year] ?? [
                        'total_credits_enrolled' => 0,
                        'completed_credits' => 0,
                        'courses' => [],
                        'progress_percentage' => 0
                    ];
                    $progressPercent = min(100, ($yearData['completed_credits'] / 60) * 100);
                    ?>
                    <div class="progress-year-bar" onclick="window.location.href='/grades/view.php?student=<?= urlencode($studentId) ?>&year=<?= $year ?>'">
                        <div class="progress-year-header">
                            <div class="year-label">
                                <h2>Year <?= $year ?></h2>
                                <span class="year-stats">
                                    <?= $yearData['completed_credits'] ?> / 60 ECs completed
                                    (<?= number_format($progressPercent, 1) ?>%)
                                </span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill"
                                     data-percent="<?= $progressPercent ?>"
                                     style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

        <?php elseif (!$isStudent && !$viewingStudent) : ?>
            <!-- Teacher/Admin: Student List View with Search -->
            <div class="page-header">
                <h1>Student Progress</h1>
            </div>

            <!-- Search -->
            <div class="filters" style="margin-bottom: var(--spacing-lg);">
                <form method="GET" action="">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text"
                               name="search"
                               value="<?= htmlspecialchars($studentSearch) ?>"
                               placeholder="Search students by name or email..."
                               style="width: 100%; max-width: 500px;">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if ($studentSearch) : ?>
                            <a href="/progress/progress.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="student-list">
                <?php if (empty($allStudents)) : ?>
                    <div class="empty-state">
                        <p><?= $studentSearch ? 'No students found matching your search.' : 'No students found.' ?></p>
                    </div>
                <?php else : ?>
                    <div class="student-grid">
                        <?php foreach ($allStudents as $student) : ?>
                            <div class="student-card" onclick="window.location.href='?student=<?= htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8') ?>'">
                                <div class="student-name"><?= htmlspecialchars($student['name']) ?></div>
                                <div class="student-email"><?= htmlspecialchars($student['email']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar-fill');
            progressBars.forEach((bar, index) => {
                const targetPercent = parseFloat(bar.getAttribute('data-percent')) || 0;
                if (targetPercent === 0) {
                    bar.style.width = '0%';
                    return;
                }

                let startTime = null;
                const duration = 1500; // 1.5 seconds

                const animate = (currentTime) => {
                    if (!startTime) startTime = currentTime;
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);

                    // Easing function (ease-out)
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    const currentPercent = targetPercent * easeOut;

                    bar.style.width = currentPercent + '%';

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        bar.style.width = targetPercent + '%';
                        // Add animation class after fill completes
                        bar.classList.add('animate-complete');
                    }
                };

                // Start animation with a staggered delay for each bar
                setTimeout(() => {
                    requestAnimationFrame(animate);
                }, index * 100 + 200);
            });
        });
    </script>
</body>
</html>
