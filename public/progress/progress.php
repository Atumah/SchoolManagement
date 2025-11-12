<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireAnyRole(['Teacher', 'Admin']);

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /progress/progress.php');
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $teacherId = $currentUser['id'];
            $studentId = (int)$_POST['student_id'];
            $courseId = (int)$_POST['course_id'];
            $notes = $_POST['notes'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');
            $status = $_POST['status'] ?? 'Stable';

            if (empty($notes)) {
                setFlashMessage('error', 'Progress notes are required');
            } else {
                addProgress([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'notes' => $notes,
                    'date' => $date,
                    'status' => $status,
                    'teacher_id' => $teacherId
                ]);
                setFlashMessage('success', 'Progress entry added successfully');
                header('Location: /progress/progress.php');
                exit;
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $studentId = (int)$_POST['student_id'];
            $courseId = (int)$_POST['course_id'];
            $notes = $_POST['notes'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');
            $status = $_POST['status'] ?? 'Stable';

            if (empty($notes)) {
                setFlashMessage('error', 'Progress notes are required');
            } else {
                updateProgress($id, [
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'notes' => $notes,
                    'date' => $date,
                    'status' => $status
                ]);
                setFlashMessage('success', 'Progress entry updated successfully');
                header('Location: /progress/progress.php');
                exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            deleteProgress($id);
            setFlashMessage('success', 'Progress entry deleted successfully');
            header('Location: /progress/progress.php');
            exit;
        }
    }
}

// Get progress entries
if ($currentUser['role'] === 'Teacher') {
    $progressEntries = getProgressByTeacher($currentUser['id']);
} else {
    $progressEntries = getAllProgress();
}

// Get teacher's courses and students
$teacherCourses = getCoursesByTeacher($currentUser['id']);
$allStudents = [];
foreach ($teacherCourses as $course) {
    foreach ($course['students'] as $studentId) {
        $student = getUserById($studentId);
        if ($student && !in_array($student, $allStudents, true)) {
            $allStudents[] = $student;
        }
    }
}

// Filtering
$filterStudent = $_GET['filter_student'] ?? '';
$filterCourse = $_GET['filter_course'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$search = $_GET['search'] ?? '';

if ($filterStudent) {
    $progressEntries = array_filter($progressEntries, fn($p) => $p['student_id'] == $filterStudent);
}
if ($filterCourse) {
    $progressEntries = array_filter($progressEntries, fn($p) => $p['course_id'] == $filterCourse);
}
if ($filterStatus) {
    $progressEntries = array_filter($progressEntries, fn($p) => $p['status'] === $filterStatus);
}
if ($search) {
    $progressEntries = array_filter($progressEntries, function ($p) use ($search) {
        return stripos($p['notes'], $search) !== false;
    });
}

$editingId = $_GET['edit'] ?? null;
$editingProgress = $editingId ? getProgressById((int)$editingId) : null;
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Student Progress</h1>
            <button class="form-toggle-btn" onclick="showAddForm()">+ Add Progress</button>
        </div>
        
        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="filter_student">Filter by Student</label>
                    <select id="filter_student" name="filter_student">
                        <option value="">All Students</option>
                        <?php foreach ($allStudents as $student) : ?>
                            <option value="<?= $student['id'] ?>" <?= $filterStudent == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_course">Filter by Course</label>
                    <select id="filter_course" name="filter_course">
                        <option value="">All Courses</option>
                        <?php foreach ($teacherCourses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= $filterCourse == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_status">Filter by Status</label>
                    <select id="filter_status" name="filter_status">
                        <option value="">All Statuses</option>
                        <option value="Improving" <?= $filterStatus === 'Improving' ? 'selected' : '' ?>>Improving</option>
                        <option value="Stable" <?= $filterStatus === 'Stable' ? 'selected' : '' ?>>Stable</option>
                        <option value="Needs Attention" <?= $filterStatus === 'Needs Attention' ? 'selected' : '' ?>>Needs Attention</option>
                        <option value="Excellent" <?= $filterStatus === 'Excellent' ? 'selected' : '' ?>>Excellent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search in notes">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="/progress/progress.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="form-container" id="progress-form" style="display: none;">
            <h2><?= $editingProgress ? 'Edit Progress Entry' : 'Add Progress Entry' ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $editingProgress ? 'edit' : 'add' ?>">
                <?php if ($editingProgress) : ?>
                    <input type="hidden" name="id" value="<?= $editingProgress['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($allStudents as $student) : ?>
                            <option value="<?= $student['id'] ?>" <?= $editingProgress && $editingProgress['student_id'] == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($teacherCourses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= $editingProgress && $editingProgress['course_id'] == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Progress Notes</label>
                    <textarea id="notes" name="notes" required><?= $editingProgress ? htmlspecialchars($editingProgress['notes']) : '' ?></textarea>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" required value="<?= $editingProgress ? $editingProgress['date'] : date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="Stable" <?= $editingProgress && $editingProgress['status'] === 'Stable' ? 'selected' : '' ?>>Stable</option>
                        <option value="Improving" <?= $editingProgress && $editingProgress['status'] === 'Improving' ? 'selected' : '' ?>>Improving</option>
                        <option value="Needs Attention" <?= $editingProgress && $editingProgress['status'] === 'Needs Attention' ? 'selected' : '' ?>>Needs Attention</option>
                        <option value="Excellent" <?= $editingProgress && $editingProgress['status'] === 'Excellent' ? 'selected' : '' ?>>Excellent</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><?= $editingProgress ? 'Update' : 'Add' ?> Progress</button>
                <button type="button" class="btn btn-secondary" onclick="hideAddForm()">Cancel</button>
            </form>
        </div>
        
        <!-- Progress Table -->
        <div class="table-container">
            <?php if (empty($progressEntries)) : ?>
                <div class="empty-state">
                    <p>No progress entries found.</p>
                </div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Notes Preview</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($progressEntries as $progress) : ?>
                            <?php
                            $student = getUserById($progress['student_id']);
                            $course = getCourseById($progress['course_id']);
                            $notesPreview = substr($progress['notes'], 0, 50);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($student['name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($course['name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($notesPreview) ?><?= strlen($progress['notes']) > 50 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($progress['date']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $progress['status'] === 'Excellent' ? 'success' : ($progress['status'] === 'Needs Attention' ? 'danger' : 'info') ?>">
                                        <?= htmlspecialchars($progress['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?= $progress['id'] ?>" class="btn btn-secondary btn-small">Edit</a>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this progress entry?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $progress['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
    function showAddForm() {
        document.getElementById('progress-form').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function hideAddForm() {
        document.getElementById('progress-form').style.display = 'none';
        window.location.href = '/progress/progress.php';
    }
    
    <?php if ($editingProgress) : ?>
    showAddForm();
    <?php endif; ?>
    </script>
</body>
</html>

