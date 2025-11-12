<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

// Redirect to login if not authenticated (BEFORE any output)
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Get user statistics
$stats = [];
if ($currentUser['role'] === 'Teacher') {
    $teacherCourses = getCoursesByTeacher($currentUser['id']);
    $stats['courses'] = count($teacherCourses);
    $stats['grades'] = count(getGradesByTeacher($currentUser['id']));
    $stats['progress'] = count(getProgressByTeacher($currentUser['id']));
    $stats['notes'] = count(getNotesByTeacher($currentUser['id']));
} elseif ($currentUser['role'] === 'Admin') {
    $stats['users'] = count(getAllUsers());
    $stats['courses'] = count(getAllCourses());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <main class="container">
        <div class="page-header">
            <h1>Welcome, <?= htmlspecialchars($currentUser['name']) ?></h1>
        </div>
        
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <?php if ($currentUser['role'] === 'Teacher'): ?>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-content">
                        <h3><?= $stats['courses'] ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <h3><?= $stats['grades'] ?></h3>
                        <p>Grades</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-content">
                        <h3><?= $stats['progress'] ?></h3>
                        <p>Progress Entries</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-content">
                        <h3><?= $stats['notes'] ?></h3>
                        <p>Notes</p>
                    </div>
                </div>
            <?php elseif ($currentUser['role'] === 'Admin'): ?>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <h3><?= $stats['users'] ?></h3>
                        <p>Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-content">
                        <h3><?= $stats['courses'] ?></h3>
                        <p>Courses</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <?php if ($currentUser['role'] === 'Teacher'): ?>
                    <a href="/grades/view.php" class="action-btn">
                        <span class="action-icon">📊</span>
                        <span>Manage Grades</span>
                    </a>
                    <a href="/courses/join.php" class="action-btn">
                        <span class="action-icon">➕</span>
                        <span>Join Course</span>
                    </a>
                    <a href="/progress/progress.php" class="action-btn">
                        <span class="action-icon">📈</span>
                        <span>Track Progress</span>
                    </a>
                    <a href="/notes/notes.php" class="action-btn">
                        <span class="action-icon">📝</span>
                        <span>Take Notes</span>
                    </a>
                <?php elseif ($currentUser['role'] === 'Admin'): ?>
                    <a href="/users/users.php" class="action-btn">
                        <span class="action-icon">👥</span>
                        <span>Manage Users</span>
                    </a>
                    <a href="/courses/course.php" class="action-btn">
                        <span class="action-icon">📚</span>
                        <span>Manage Courses</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
