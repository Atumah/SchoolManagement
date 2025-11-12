<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireRole('Student');

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Handle join/leave course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /courses/join.php');
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'join') {
            // Ensure only students can join courses
            if ($currentUser['role'] !== 'Student') {
                setFlashMessage('error', 'Only students can join courses.');
                header('Location: /courses/join.php');
                exit;
            }

            $courseId = (int)$_POST['course_id'];
            if (joinCourse($courseId, $currentUser['id'])) {
                setFlashMessage('success', 'Successfully joined course');
            } else {
                setFlashMessage('error', 'Failed to join course. Course may be full or you are already enrolled.');
            }
            header('Location: /courses/join.php');
            exit;
        } elseif ($_POST['action'] === 'leave') {
            // Ensure only students can leave courses
            if ($currentUser['role'] !== 'Student') {
                setFlashMessage('error', 'Only students can leave courses.');
                header('Location: /courses/join.php');
                exit;
            }

            $courseId = (int)$_POST['course_id'];
            if (leaveCourse($courseId, $currentUser['id'])) {
                setFlashMessage('success', 'Successfully left course');
            } else {
                setFlashMessage('error', 'Failed to leave course');
            }
            header('Location: /courses/join.php');
            exit;
        }
    }
}

// Get all courses
$allCourses = getAllCourses();
$userCourses = getCoursesByStudent($currentUser['id']);
$userCourseIds = array_column($userCourses, 'id');

// Filtering
$csrfToken = generateCSRFToken();
$search = $_GET['search'] ?? '';
if ($search) {
    $allCourses = array_filter($allCourses, function ($course) use ($search) {
        return stripos($course['name'], $search) !== false ||
               stripos($course['description'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Course - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Join Course</h1>
        </div>
        
        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <!-- Search -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="search">Search Courses</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by course name or description">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
                <a href="/courses/join.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        <!-- Courses List -->
        <?php if (empty($allCourses)) : ?>
            <div class="empty-state">
                <p>No courses available.</p>
            </div>
        <?php else : ?>
            <div style="display: grid; gap: 1.5rem;">
                <?php foreach ($allCourses as $course) : ?>
                    <?php
                    $teacher = getUserById($course['teacher_id']);
                    $enrolledCount = count($course['students']);
                    $isEnrolled = in_array($course['id'], $userCourseIds, true);
                    $isFull = $enrolledCount >= $course['max_students'];
                    ?>
                    <div class="course-card">
                        <div class="course-card-header">
                            <h2><?= htmlspecialchars($course['name']) ?></h2>
                            <div>
                                <?php if ($isEnrolled) : ?>
                                    <span class="badge badge-success">Already Joined</span>
                                <?php elseif ($isFull) : ?>
                                    <span class="badge badge-danger">Full</span>
                                <?php else : ?>
                                    <span class="badge badge-info">Available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="course-card-content">
                            <p><strong>Description:</strong> <?= htmlspecialchars($course['description']) ?></p>
                            <p><strong>Teacher:</strong> <?= htmlspecialchars($teacher['name'] ?? 'Unknown') ?></p>
                            <p><strong>Schedule:</strong> <?= htmlspecialchars($course['schedule']) ?></p>
                            <p><strong>Enrollment:</strong> <?= $enrolledCount ?> / <?= $course['max_students'] ?> students</p>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <?php if ($isEnrolled) : ?>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to leave this course?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="leave">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Leave Course</button>
                                </form>
                            <?php elseif ($isFull) : ?>
                                <button class="btn btn-secondary" disabled>Course Full</button>
                            <?php else : ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="join">
                                    <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                    <button type="submit" class="btn btn-primary">Join Course</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

