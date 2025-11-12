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
        header('Location: /grades/view.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $teacherId = $currentUser['id'];
            $studentId = (int)$_POST['student_id'];
            $courseId = (int)$_POST['course_id'];
            $grade = (float)$_POST['grade'];
            $date = $_POST['date'];
            $notes = $_POST['notes'] ?? '';
            
            // Validation
            if ($grade < 0 || $grade > 100) {
                setFlashMessage('error', 'Grade must be between 0 and 100');
            } else {
                addGrade([
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'grade' => $grade,
                    'date' => $date,
                    'notes' => $notes,
                    'teacher_id' => $teacherId
                ]);
                setFlashMessage('success', 'Grade added successfully');
                header('Location: /grades/view.php');
                exit;
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $studentId = (int)$_POST['student_id'];
            $courseId = (int)$_POST['course_id'];
            $grade = (float)$_POST['grade'];
            $date = $_POST['date'];
            $notes = $_POST['notes'] ?? '';
            
            if ($grade < 0 || $grade > 100) {
                setFlashMessage('error', 'Grade must be between 0 and 100');
            } else {
                updateGrade($id, [
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'grade' => $grade,
                    'date' => $date,
                    'notes' => $notes
                ]);
                setFlashMessage('success', 'Grade updated successfully');
                header('Location: /grades/view.php');
                exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            deleteGrade($id);
            setFlashMessage('success', 'Grade deleted successfully');
            header('Location: /grades/view.php');
            exit;
        }
    }
}

// Get grades
if ($currentUser['role'] === 'Teacher') {
    $grades = getGradesByTeacher($currentUser['id']);
} else {
    $grades = getAllGrades();
}

// Get teacher's courses for dropdowns
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
$search = $_GET['search'] ?? '';

if ($filterStudent) {
    $grades = array_filter($grades, fn($g) => $g['student_id'] == $filterStudent);
}
if ($filterCourse) {
    $grades = array_filter($grades, fn($g) => $g['course_id'] == $filterCourse);
}
if ($search) {
    $grades = array_filter($grades, function($g) use ($search) {
        $student = getUserById($g['student_id']);
        $course = getCourseById($g['course_id']);
        return stripos($student['name'] ?? '', $search) !== false ||
               stripos($course['name'] ?? '', $search) !== false;
    });
}

$editingId = $_GET['edit'] ?? null;
$editingGrade = $editingId ? getGradeById((int)$editingId) : null;
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Grades</h1>
            <button class="form-toggle-btn" onclick="showAddForm()">+ Add Grade</button>
        </div>
        
        <?php if ($flash): ?>
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
                        <?php foreach ($allStudents as $student): ?>
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
                        <?php foreach ($teacherCourses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $filterCourse == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student or course">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="/grades/view.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        <!-- Add/Edit Form -->
        <div class="form-container" id="grade-form" style="display: none;">
            <h2><?= $editingGrade ? 'Edit Grade' : 'Add Grade' ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="<?= $editingGrade ? 'edit' : 'add' ?>">
                <?php if ($editingGrade): ?>
                    <input type="hidden" name="id" value="<?= $editingGrade['id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($allStudents as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $editingGrade && $editingGrade['student_id'] == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($teacherCourses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $editingGrade && $editingGrade['course_id'] == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="grade">Grade (0-100)</label>
                    <input type="number" id="grade" name="grade" min="0" max="100" step="0.1" required value="<?= $editingGrade ? $editingGrade['grade'] : '' ?>">
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" required value="<?= $editingGrade ? $editingGrade['date'] : date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"><?= $editingGrade ? htmlspecialchars($editingGrade['notes']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?= $editingGrade ? 'Update' : 'Add' ?> Grade</button>
                <button type="button" class="btn btn-secondary" onclick="hideAddForm()">Cancel</button>
            </form>
        </div>
        
        <!-- Grades Table -->
        <div class="table-container">
            <?php if (empty($grades)): ?>
                <div class="empty-state">
                    <p>No grades found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Grade</th>
                            <th>Date</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <?php
                            $student = getUserById($grade['student_id']);
                            $course = getCourseById($grade['course_id']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($student['name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($course['name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars((string)$grade['grade']) ?></td>
                                <td><?= htmlspecialchars($grade['date']) ?></td>
                                <td><?= htmlspecialchars(substr($grade['notes'], 0, 50)) ?><?= strlen($grade['notes']) > 50 ? '...' : '' ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?= $grade['id'] ?>" class="btn btn-secondary btn-small">Edit</a>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $grade['id'] ?>">
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
        document.getElementById('grade-form').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function hideAddForm() {
        document.getElementById('grade-form').style.display = 'none';
        window.location.href = '/grades/view.php';
    }
    
    <?php if ($editingGrade): ?>
    showAddForm();
    <?php endif; ?>
    </script>
</body>
</html>

