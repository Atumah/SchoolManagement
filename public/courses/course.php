<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Support\Cuid;
use App\Database\Database;

requireAuth();

$currentUser = getCurrentUser();
// Students should use join.php instead
if ($currentUser['role'] === 'Student') {
    header('Location: /courses/join.php');
    exit;
}

$flash = getFlashMessage();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /courses/course.php');
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            if (hasAnyRole(['Admin', 'Teacher'])) {
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $teacherId = $_POST['teacher_id'];
                $schedule = $_POST['schedule'] ?? '';
                $maxStudents = (int)($_POST['max_students'] ?? 30);
                $year = (int)($_POST['year'] ?? 1);
                $credits = (int)($_POST['credits'] ?? 5);

                if (empty($name) || empty($teacherId)) {
                    setFlashMessage('error', 'Course name and teacher are required');
                } else {
                    try {
                        addCourse([
                            'name' => $name,
                            'description' => $description,
                            'teacher_id' => $teacherId,
                            'schedule' => $schedule,
                            'max_students' => $maxStudents,
                            'year' => $year,
                            'credits' => $credits
                        ]);
                        setFlashMessage('success', 'Course added successfully');
                        header('Location: /courses/course.php');
                        exit;
                    } catch (RuntimeException $e) {
                        setFlashMessage('error', $e->getMessage());
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $course = getCourseById($id);
            if ($course && (hasRole('Admin') || $course['teacher_id'] === $currentUser['id'])) {
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $teacherId = $_POST['teacher_id'];
                $schedule = $_POST['schedule'] ?? '';
                $maxStudents = (int)$_POST['max_students'];
                $year = (int)($_POST['year'] ?? 1);
                $credits = (int)($_POST['credits'] ?? 5);

                if ($maxStudents < count($course['students'])) {
                    setFlashMessage('error', 'Cannot reduce max students below current enrollment');
                } else {
                    try {
                        updateCourse($id, [
                            'name' => $name,
                            'description' => $description,
                            'teacher_id' => $teacherId,
                            'schedule' => $schedule,
                            'max_students' => $maxStudents,
                            'year' => $year,
                            'credits' => $credits
                        ]);
                        setFlashMessage('success', 'Course updated successfully');
                        header('Location: /courses/course.php');
                        exit;
                    } catch (RuntimeException $e) {
                        setFlashMessage('error', $e->getMessage());
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            $course = getCourseById($id);
            if ($course && (hasRole('Admin') || $course['teacher_id'] === $currentUser['id'])) {
                deleteCourse($id);
                setFlashMessage('success', 'Course deleted successfully');
                header('Location: /courses/course.php');
                exit;
            }
        } elseif ($_POST['action'] === 'add_student') {
            $courseId = $_POST['course_id'];
            $studentId = $_POST['student_id'];
            $course = getCourseById($courseId);

            if ($course && (hasRole('Admin') || $course['teacher_id'] === $currentUser['id'])) {
                if (joinCourse($courseId, $studentId)) {
                    setFlashMessage('success', 'Student added to course successfully');
                } else {
                    setFlashMessage('error', 'Failed to add student. Student may already be enrolled or course is full.');
                }
                header('Location: /courses/course.php?view=' . $courseId);
                exit;
            }
        } elseif ($_POST['action'] === 'remove_student') {
            $courseId = $_POST['course_id'];
            $studentId = $_POST['student_id'];
            $course = getCourseById($courseId);

            if ($course && (hasRole('Admin') || $course['teacher_id'] === $currentUser['id'])) {
                if (leaveCourse($courseId, $studentId)) {
                    setFlashMessage('success', 'Student removed from course successfully');
                } else {
                    setFlashMessage('error', 'Failed to remove student.');
                }
                header('Location: /courses/course.php?view=' . $courseId);
                exit;
            }
        } elseif ($_POST['action'] === 'add_grade' || $_POST['action'] === 'edit_grade') {
            // Handle grade add/edit from course grades tab
            if (hasAnyRole(['Admin', 'Teacher'])) {
                $courseId = $_POST['course_id'] ?? null;
                $studentId = $_POST['student_id'] ?? null;
                $originalGrade = isset($_POST['original_grade']) ? (float)$_POST['original_grade'] : null;

                if (!$courseId || !$studentId) {
                    setFlashMessage('error', 'Course ID and Student ID are required');
                    header('Location: /courses/course.php?view=' . ($courseId ?? ''));
                    exit;
                }

                $course = getCourseById($courseId);
                if (!$course || (!hasRole('Admin') && $course['teacher_id'] !== $currentUser['id'])) {
                    setFlashMessage('error', 'You do not have permission to grade this course');
                    header('Location: /courses/course.php?view=' . $courseId);
                    exit;
                }

                if ($originalGrade === null || $originalGrade < 1.0 || $originalGrade > 10.0) {
                    setFlashMessage('error', 'Original grade must be between 1.0 and 10.0');
                    header('Location: /courses/course.php?view=' . $courseId . '&tab=grades');
                    exit;
                }

                try {
                    $gradeData = [
                        'student_id' => $studentId,
                        'course_id' => $courseId,
                        'teacher_id' => $currentUser['id'],
                        'original_grade' => $originalGrade,
                        'notes' => $_POST['notes'] ?? ''
                    ];

                    if ($_POST['action'] === 'add_grade') {
                        // Check if grade record exists (should exist with NULL values)
                        $allGrades = getGradesByCourse($courseId);
                        $existingGrade = null;
                        foreach ($allGrades as $g) {
                            if ($g['student_id'] === $studentId) {
                                $existingGrade = $g;
                                break;
                            }
                        }
                        if ($existingGrade) {
                            // Update existing NULL grade record
                            updateGrade($existingGrade['id'], $gradeData);
                            setFlashMessage('success', 'Grade added successfully');
                        } else {
                            // Create new grade record
                            addGrade($gradeData);
                            setFlashMessage('success', 'Grade added successfully');
                        }
                    } else {
                        // Edit existing grade
                        $gradeId = $_POST['grade_id'] ?? null;
                        if (!$gradeId) {
                            setFlashMessage('error', 'Grade ID is required for editing');
                            header('Location: /courses/course.php?view=' . $courseId . '&tab=grades');
                            exit;
                        }
                        updateGrade($gradeId, $gradeData);
                        setFlashMessage('success', 'Grade updated successfully');
                    }
                } catch (Exception $e) {
                    setFlashMessage('error', $e->getMessage());
                }

                header('Location: /courses/course.php?view=' . $courseId . '&tab=grades');
                exit;
            }
        } elseif ($_POST['action'] === 'delete_grade') {
            // Handle grade deletion from course grades tab
            if (hasAnyRole(['Admin', 'Teacher'])) {
                $gradeId = $_POST['grade_id'] ?? null;
                $courseId = $_POST['course_id'] ?? null;

                if (!$gradeId || !$courseId) {
                    setFlashMessage('error', 'Grade ID and Course ID are required');
                    header('Location: /courses/course.php?view=' . ($courseId ?? ''));
                    exit;
                }

                $grade = getGradeById($gradeId);
                $course = getCourseById($courseId);

                if ($grade && $course && (hasRole('Admin') || $course['teacher_id'] === $currentUser['id'])) {
                    // Set grade to NULL instead of deleting (keep record)
                    try {
                        $stmt = Database::connection()->prepare('
                            UPDATE grades
                            SET original_grade = NULL, final_grade = NULL, date = NULL
                            WHERE id = ?
                        ');
                        $stmt->execute([$gradeId]);
                        setFlashMessage('success', 'Grade deleted successfully');
                    } catch (Exception $e) {
                        setFlashMessage('error', 'Failed to delete grade');
                    }
                }

                header('Location: /courses/course.php?view=' . $courseId . '&tab=grades');
                exit;
            }
        }
    }
}

// Get all courses
$courses = getAllCourses();
$teachers = array_filter(getAllUsers(), static fn ($u) => $u['role'] === 'Teacher');
$students = array_values(array_filter(getAllUsers(), static fn ($u) => $u['role'] === 'Student')); // Re-index for proper array handling

// Get active tab from URL (default to 'overview')
$activeTab = $_GET['tab'] ?? 'overview';

// Filtering
$filterTeacher = $_GET['filter_teacher'] ?? '';
$search = $_GET['search'] ?? '';

if ($filterTeacher) {
    $courses = array_filter($courses, static fn ($c) => $c['teacher_id'] === $filterTeacher);
}
if ($search) {
    $courses = array_filter($courses, static fn ($c) => stripos($c['name'], $search) !== false);
}

$editingId = $_GET['edit'] ?? null;
$viewingId = $_GET['view'] ?? null;
$editingCourse = $editingId ? getCourseById($editingId) : null;
$viewingCourse = $viewingId ? getCourseById($viewingId) : null;

// Get grades for this course if viewing course detail
$courseGrades = [];
$gradesByStudentId = [];
if ($viewingCourse) {
    $courseGrades = getGradesByCourse($viewingCourse['id']);
    // Create map of student_id => grade for quick lookup
    // Note: Grade records are created automatically when students join courses via joinCourse()
    // We don't create them here - only display existing grades
    foreach ($courseGrades as $grade) {
        $gradesByStudentId[$grade['student_id']] = $grade;
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>Courses - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <script>
    // Define switchTab function immediately in head to ensure it's available for onclick handlers
    function switchTab(tabName) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.location.href = url.toString();
    }
    // Also assign to window for explicit global access
    window.switchTab = switchTab;
    </script>
    <style>
        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .actions .btn {
            height: 32px;
            padding: 0.5rem 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .course-row {
            cursor: pointer;
            transition: background-color var(--transition-normal);
        }

        .course-row:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .course-row .actions {
            pointer-events: auto;
        }

        .course-row .actions a,
        .course-row .actions form,
        .course-row .actions button {
            pointer-events: auto;
        }

        .course-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .course-info {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(37, 47, 69, 0.9));
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border-color);
        }

        .course-info h2 {
            margin-top: 0;
            color: var(--text-primary);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .info-value {
            color: var(--text-primary);
        }

        .students-section {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(37, 47, 69, 0.9));
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border-color);
        }

        .students-section h3 {
            margin-top: 0;
            color: var(--text-primary);
        }

        .student-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: rgba(59, 130, 246, 0.1);
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
        }

        .add-student-form {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .course-detail-container {
                grid-template-columns: 1fr;
            }
        }

        /* Tabs Styles */
        .course-tabs-container {
            margin-top: var(--spacing-lg);
        }

        .tabs-nav {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: var(--spacing-lg);
        }

        .tab-button {
            padding: var(--spacing-sm) var(--spacing-md);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all var(--transition-normal);
            margin-bottom: -2px;
        }

        .tab-button:hover {
            color: var(--primary-lighter);
            border-bottom-color: var(--border-color);
        }

        .tab-button.active {
            color: var(--primary-lighter);
            border-bottom-color: var(--primary-lighter);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Courses</h1>
            <?php if (hasAnyRole(['Admin', 'Teacher']) && !$viewingCourse) : ?>
                <button type="button" class="form-toggle-btn" onclick="openAddCourseModal()">+ Add Course</button>
            <?php endif; ?>
        </div>

        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($viewingCourse) : ?>
            <!-- Course Detail View with Tabs -->
            <div class="course-tabs-container">
                <!-- Tab Navigation -->
                <div class="tabs-nav">
                    <button class="tab-button <?= $activeTab === 'overview' ? 'active' : '' ?>" onclick="switchTab('overview')">Overview</button>
                    <button class="tab-button <?= $activeTab === 'grades' ? 'active' : '' ?>" onclick="switchTab('grades')">Grades</button>
                </div>

                <!-- Overview Tab Content -->
                <div id="tab-overview" class="tab-content <?= $activeTab === 'overview' ? 'active' : '' ?>">
                    <div class="course-detail-container">
                <div class="course-info">
                    <h2><?= htmlspecialchars($viewingCourse['name']) ?></h2>
                    <div class="info-row">
                        <span class="info-label">Description:</span>
                        <span class="info-value"><?= htmlspecialchars($viewingCourse['description'] ?: 'No description') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Teacher:</span>
                        <span class="info-value"><?= htmlspecialchars(getUserById($viewingCourse['teacher_id'])['name'] ?? 'Unknown') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Schedule:</span>
                        <span class="info-value"><?= htmlspecialchars($viewingCourse['schedule'] ?: 'Not scheduled') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Enrollment:</span>
                        <span class="info-value"><?= count($viewingCourse['students']) ?> / <?= $viewingCourse['max_students'] ?></span>
                    </div>
                    <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] === $currentUser['id']) : ?>
                        <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-secondary edit-course-btn"
                                    style="height: 38px; display: inline-flex; align-items: center;"
                                    data-course='<?= htmlspecialchars(json_encode($viewingCourse), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course? This will remove all enrollments.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $viewingCourse['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="height: 38px; display: inline-flex; align-items: center;">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="students-section">
                    <h3>Enrolled Students</h3>
                    <?php if (empty($viewingCourse['students'])) : ?>
                        <p style="color: var(--text-muted);">No students enrolled yet.</p>
                    <?php else : ?>
                        <ul class="student-list">
                            <?php foreach ($viewingCourse['students'] as $studentId) : ?>
                                <?php $student = getUserById($studentId); ?>
                                <?php if ($student) : ?>
                                    <li class="student-item">
                                        <span><?= htmlspecialchars($student['name']) ?></span>
                                        <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] === $currentUser['id']) : ?>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Remove this student from the course?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="remove_student">
                                                <input type="hidden" name="course_id" value="<?= $viewingCourse['id'] ?>">
                                                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] === $currentUser['id']) : ?>
                        <?php
                        $enrolledStudentIds = $viewingCourse['students'] ?? [];
                        $availableStudents = array_filter($students, static fn ($s) => !in_array($s['id'], $enrolledStudentIds, true));
                        // Re-index array to ensure proper JSON encoding
                        $availableStudents = array_values($availableStudents);
                        ?>
                        <?php if (count($viewingCourse['students']) < $viewingCourse['max_students']) : ?>
                            <div class="add-student-form">
                                <button type="button" class="btn btn-primary add-student-btn"
                                        data-course-id="<?= htmlspecialchars($viewingCourse['id'], ENT_QUOTES, 'UTF-8') ?>">+ Add Student</button>
                            </div>
                        <?php elseif (count($viewingCourse['students']) >= $viewingCourse['max_students']) : ?>
                            <p style="color: var(--text-muted); margin-top: 1rem;">Course is full (<?= $viewingCourse['max_students'] ?> students).</p>
                        <?php elseif (empty($availableStudents)) : ?>
                            <p style="color: var(--text-muted); margin-top: 1rem;">All students are already enrolled.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                    </div>
                </div>

                <!-- Grades Tab Content -->
                <div id="tab-grades" class="tab-content <?= $activeTab === 'grades' ? 'active' : '' ?>">
                    <?php
                    // Include grades tab - we'll create this next
                    $courseId = $viewingCourse['id'];
$isTeacherOrAdmin = hasAnyRole(['Admin', 'Teacher']);
include __DIR__ . '/course-grades-tab.php';
?>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <a href="/courses/course.php" class="btn btn-secondary">Back to Courses List</a>
            </div>
        <?php else : ?>
            <!-- Courses List View -->
            <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="filter_teacher">Filter by Teacher</label>
                    <select id="filter_teacher" name="filter_teacher">
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $teacher) : ?>
                            <option value="<?= $teacher['id'] ?>" <?= $filterTeacher === $teacher['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($teacher['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by course name">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="/courses/course.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>


        <!-- Courses Table -->
        <div class="table-container">
            <?php if (empty($courses)) : ?>
                <div class="empty-state">
                    <p>No courses found.</p>
                </div>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Teacher</th>
                            <th>Students Enrolled</th>
                            <th>Schedule</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course) : ?>
                            <?php
        $teacher = getUserById($course['teacher_id']);
                            $enrolledCount = count($course['students']);
                            ?>
                            <tr class="course-row" onclick="window.location.href='?view=<?= $course['id'] ?>'" style="cursor: pointer;">
                                <td>
                                    <strong><?= htmlspecialchars($course['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($teacher['name'] ?? 'Unknown') ?></td>
                                <td><?= $enrolledCount ?> / <?= $course['max_students'] ?></td>
                                <td><?= htmlspecialchars($course['schedule']) ?></td>
                                <td>
                                    <div class="actions" onclick="event.stopPropagation();">
                                        <?php if (hasRole('Admin') || $course['teacher_id'] === $currentUser['id']) : ?>
                                            <button type="button" class="btn btn-secondary btn-small edit-course-btn"
                                                    data-course='<?= htmlspecialchars(json_encode($course), ENT_QUOTES, 'UTF-8') ?>'
                                                    onclick="event.stopPropagation();">Edit</button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course? This will remove all enrollments.');" onclick="event.stopPropagation();">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $course['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-small" onclick="event.stopPropagation();">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Edit Course Modal -->
    <?php if (hasAnyRole(['Admin', 'Teacher'])) : ?>
    <div class="modal-overlay" id="editCourseModal" onclick="closeEditModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Edit Course</h2>
                <button type="button" class="modal-close-btn" onclick="closeEditModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="editCourseForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_course_id">
                    <div class="form-group">
                        <label for="edit_name">Course Name</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_teacher_id">Teacher</label>
                        <select id="edit_teacher_id" name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher) : ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_schedule">Schedule</label>
                        <input type="text" id="edit_schedule" name="schedule" placeholder="e.g., Mon/Wed 10:00-11:00">
                    </div>
                    <div class="form-group">
                        <label for="edit_max_students">Max Students</label>
                        <input type="number" id="edit_max_students" name="max_students" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_year">Year</label>
                        <select id="edit_year" name="year" required onchange="updateYearInfo(this, 'edit')">
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_credits">EC Credits</label>
                        <input type="number" id="edit_credits" name="credits" min="1" max="60" required oninput="validateECs(this, 'edit')">
                        <small id="edit_ec_info" class="ec-info"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal-overlay" id="addCourseModal" onclick="closeAddCourseModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Add Course</h2>
                <button type="button" class="modal-close-btn" onclick="closeAddCourseModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="addCourseForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="add_name">Course Name</label>
                        <input type="text" id="add_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_description">Description</label>
                        <textarea id="add_description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="add_teacher_id">Teacher</label>
                        <select id="add_teacher_id" name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher) : ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_schedule">Schedule</label>
                        <input type="text" id="add_schedule" name="schedule" placeholder="e.g., Mon/Wed 10:00-11:00">
                    </div>
                    <div class="form-group">
                        <label for="add_max_students">Max Students</label>
                        <input type="number" id="add_max_students" name="max_students" min="1" required value="30">
                    </div>
                    <div class="form-group">
                        <label for="add_year">Year</label>
                        <select id="add_year" name="year" required onchange="updateYearInfo(this, 'add')">
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_credits">EC Credits</label>
                        <input type="number" id="add_credits" name="credits" min="1" max="60" required value="5" oninput="validateECs(this, 'add')">
                        <small id="add_ec_info" class="ec-info"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddCourseModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="add_course_submit">Add Course</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="addStudentModal" onclick="closeAddStudentModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Add Student to Course</h2>
                <button type="button" class="modal-close-btn" onclick="closeAddStudentModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="addStudentForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="add_student">
                    <input type="hidden" name="course_id" id="add_student_course_id">
                    <div class="form-group">
                        <label for="add_student_id">Select Student</label>
                        <select id="add_student_id" name="student_id" required>
                            <option value="">Select Student</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Helper function for safe element retrieval
    function safeGetElement(id, errorMessage = null) {
        const el = document.getElementById(id);
        if (!el) {
            const msg = errorMessage || `Element not found: ${id}`;
            console.error(msg);
            if (errorMessage) {
                alert(errorMessage);
            }
        }
        return el;
    }

    // Define all global functions FIRST before any other code
    // Tab switching function - ensure it's in global scope
    // Define it both as window property and as a global function for compatibility
    function switchTab(tabName) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.location.href = url.toString();
    }
    // Also assign to window for explicit global access
    window.switchTab = switchTab;

    // Ensure functions are in global scope
    window.openAddStudentModal = async function(courseId, availableStudents = null) {
        const modal = document.getElementById('addStudentModal');
        if (!modal) {
            console.error('Add student modal not found');
            alert('Error: Modal not found. Please refresh the page.');
            return;
        }

        // Set course ID
        const courseIdInput = document.getElementById('add_student_course_id');
        if (courseIdInput) {
            courseIdInput.value = courseId;
        } else {
            console.error('Course ID input not found');
            alert('Error: Form element not found. Please refresh the page.');
            return;
        }

        // Populate student dropdown
        const studentSelect = document.getElementById('add_student_id');
        if (!studentSelect) {
            console.error('Student select element not found');
            alert('Error: Form element not found. Please refresh the page.');
            return;
        }

        // Show loading state
        studentSelect.innerHTML = '<option value="">Loading students...</option>';
        studentSelect.disabled = true;

        // Fetch available students via AJAX if not provided
        if (!availableStudents && courseId) {
            try {
                const response = await fetch(`/api/get-available-students.php?course_id=${encodeURIComponent(courseId)}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error);
                }
                availableStudents = data.students || [];
            } catch (e) {
                console.error('Error fetching available students:', e);
                studentSelect.innerHTML = '<option value="">Error loading students</option>';
                studentSelect.disabled = false;
                alert('Error loading available students. Please refresh the page and try again.');
                return;
            }
        }

        // Populate dropdown
        studentSelect.innerHTML = '<option value="">Select Student</option>';
        studentSelect.disabled = false;

        if (availableStudents && Array.isArray(availableStudents) && availableStudents.length > 0) {
            availableStudents.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = student.name || (student.first_name + ' ' + student.last_name) || 'Unknown';
                studentSelect.appendChild(option);
            });
        } else {
            studentSelect.innerHTML = '<option value="">No available students</option>';
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    function closeAddStudentModal() {
        const modal = document.getElementById('addStudentModal');
        if (!modal) return;

        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeAddStudentModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeAddStudentModal();
        }
    }

    // Edit Course Modal Functions
    function openEditModal(course) {
        const modal = safeGetElement('editCourseModal', 'Error: Edit modal not found. Please refresh the page.');
        if (!modal) return;

        // Populate form fields with null checks
        const courseIdEl = safeGetElement('edit_course_id');
        const nameEl = safeGetElement('edit_name');
        const descEl = safeGetElement('edit_description');
        const teacherEl = safeGetElement('edit_teacher_id');
        const scheduleEl = safeGetElement('edit_schedule');
        const maxStudentsEl = safeGetElement('edit_max_students');
        const yearEl = safeGetElement('edit_year');
        const creditsEl = safeGetElement('edit_credits');

        if (courseIdEl) courseIdEl.value = course.id || '';
        if (nameEl) nameEl.value = course.name || '';
        if (descEl) descEl.value = course.description || '';
        if (teacherEl) teacherEl.value = course.teacher_id || '';
        if (scheduleEl) scheduleEl.value = course.schedule || '';
        if (maxStudentsEl) maxStudentsEl.value = course.max_students || '30';
        if (yearEl) yearEl.value = course.year || '1';
        if (creditsEl) creditsEl.value = course.credits || '5';

        // Update year info
        if (yearEl) {
            updateYearInfo(yearEl, 'edit');
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        const modal = document.getElementById('editCourseModal');
        if (!modal) return;

        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeEditModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeEditModal();
        }
    }

    // Add Course Modal Functions
    function openAddCourseModal() {
        const modal = safeGetElement('addCourseModal', 'Error: Add course modal not found. Please refresh the page.');
        if (!modal) return;

        // Reset form fields with null checks
        const nameEl = safeGetElement('add_name');
        const descEl = safeGetElement('add_description');
        const teacherEl = safeGetElement('add_teacher_id');
        const scheduleEl = safeGetElement('add_schedule');
        const maxStudentsEl = safeGetElement('add_max_students');
        const yearEl = safeGetElement('add_year');
        const creditsEl = safeGetElement('add_credits');
        const ecInfoEl = safeGetElement('add_ec_info');

        if (nameEl) nameEl.value = '';
        if (descEl) descEl.value = '';
        if (teacherEl) teacherEl.value = '';
        if (scheduleEl) scheduleEl.value = '';
        if (maxStudentsEl) maxStudentsEl.value = '30';
        if (yearEl) yearEl.value = '1';
        if (creditsEl) creditsEl.value = '5';
        if (ecInfoEl) ecInfoEl.textContent = '';

        // Update EC info for year 1
        if (yearEl) {
            updateYearInfo(yearEl, 'add');
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeAddCourseModal() {
        const modal = document.getElementById('addCourseModal');
        if (!modal) return;

        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function closeAddCourseModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeAddCourseModal();
        }
    }


    // Attach event listeners to all edit buttons
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const editButtons = document.querySelectorAll('.edit-course-btn');
            if (editButtons && editButtons.length > 0) {
                editButtons.forEach(button => {
                    if (!button) return;
                    button.addEventListener('click', function() {
                        const courseData = this.getAttribute('data-course');
                        if (courseData) {
                            try {
                                const course = JSON.parse(courseData);
                                openEditModal(course);
                            } catch (e) {
                                console.error('Error parsing course data:', e);
                                alert('Error loading course data. Please refresh the page and try again.');
                            }
                        } else {
                            console.error('Course data attribute is missing');
                        }
                    });
                });
            }
        } catch (e) {
            console.error('Error attaching edit button listeners:', e);
        }

        // Attach event listeners to add student buttons
        const addStudentButtons = document.querySelectorAll('.add-student-btn');
        if (addStudentButtons && addStudentButtons.length > 0) {
            addStudentButtons.forEach(button => {
                if (!button) return;
                button.addEventListener('click', function() {
                    const courseId = this.getAttribute('data-course-id');
                    if (courseId) {
                        // Fetch students via AJAX (no need for data attribute)
                        window.openAddStudentModal(courseId);
                    } else {
                        console.error('Missing course ID');
                        alert('Error: Missing course ID. Please refresh the page and try again.');
                    }
                });
            });
        } else {
            console.warn('No add student buttons found on page');
        }
    });

    // Close any modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            closeAddCourseModal();
            closeAddStudentModal();
        }
    });

    // EC Validation Functions
    async function updateYearInfo(select, prefix) {
        if (!select) {
            console.error('updateYearInfo: select element is null');
            return;
        }
        const year = parseInt(select.value);
        if (isNaN(year) || year < 1 || year > 4) {
            console.error('updateYearInfo: invalid year', year);
            return;
        }
        const creditsInput = safeGetElement(prefix + '_credits');
        const infoElement = safeGetElement(prefix + '_ec_info');
        const submitButton = prefix === 'add' ? safeGetElement('add_course_submit') : document.querySelector('#editCourseForm button[type="submit"]');

        if (!creditsInput || !infoElement) {
            console.error('updateYearInfo: required elements not found');
            return;
        }

        try {
            const response = await fetch(`/api/get-year-credits.php?year=${year}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }
            const totalCredits = data.total || 0;
            const remaining = 60 - totalCredits;

            if (prefix === 'edit') {
                // For edit, need to subtract current course's credits
                const currentCredits = parseInt(creditsInput.value) || 0;
                // We'll calculate this in validateECs
            }

            infoElement.textContent = `Total ECs in Year ${year}: ${totalCredits}/60. Remaining: ${remaining} ECs`;

            if (remaining <= 0) {
                infoElement.style.color = 'var(--accent-danger)';
                if (submitButton) submitButton.disabled = true;
            } else {
                infoElement.style.color = 'var(--text-secondary)';
                if (submitButton) submitButton.disabled = false;
            }

            // Validate current input
            validateECs(creditsInput, prefix);
        } catch (error) {
            console.error('Error fetching year credits:', error);
            if (infoElement) {
                infoElement.textContent = 'Error loading year information. Please try again.';
                infoElement.style.color = 'var(--accent-danger)';
            }
        }
    }

    async function validateECs(input, prefix) {
        if (!input) {
            console.error('validateECs: input element is null');
            return;
        }
        const yearEl = safeGetElement(prefix + '_year');
        if (!yearEl) {
            console.error('validateECs: year element not found');
            return;
        }
        const year = parseInt(yearEl.value);
        if (isNaN(year) || year < 1 || year > 4) {
            console.error('validateECs: invalid year', year);
            return;
        }
        const credits = parseInt(input.value) || 0;
        const infoElement = safeGetElement(prefix + '_ec_info');
        const submitButton = prefix === 'add' ? safeGetElement('add_course_submit') : document.querySelector('#editCourseForm button[type="submit"]');

        if (!infoElement) {
            console.error('validateECs: info element not found');
            return;
        }

        try {
            const response = await fetch(`/api/get-year-credits.php?year=${year}`);
            const data = await response.json();
            let totalCredits = data.total || 0;

            // For edit, subtract current course's credits if editing
            if (prefix === 'edit') {
                const editCourseIdEl = safeGetElement('edit_course_id');
                if (editCourseIdEl) {
                    const editCourseId = editCourseIdEl.value;
                    if (editCourseId) {
                        // Get current course credits from the course data
                        const editBtn = document.querySelector(`[data-course*="${editCourseId}"]`);
                        if (editBtn) {
                            try {
                                const courseDataAttr = editBtn.getAttribute('data-course');
                                if (courseDataAttr) {
                                    const courseData = JSON.parse(courseDataAttr);
                                    const currentCredits = parseInt(courseData.credits) || 0;
                                    totalCredits -= currentCredits;
                                }
                            } catch (e) {
                                console.error('Error parsing course data:', e);
                                // Continue with calculation even if parsing fails
                            }
                        }
                    }
                }
            }

            const newTotal = totalCredits + credits;
            const remaining = 60 - totalCredits;

            if (newTotal > 60) {
                infoElement.textContent = `Cannot assign ${credits} ECs. Only ${remaining} ECs remaining for Year ${year}.`;
                infoElement.style.color = 'var(--accent-danger)';
                if (submitButton) submitButton.disabled = true;
            } else {
                infoElement.textContent = `Total ECs in Year ${year}: ${totalCredits + credits}/60. Remaining: ${60 - newTotal} ECs`;
                infoElement.style.color = 'var(--text-secondary)';
                if (submitButton) submitButton.disabled = false;
            }
        } catch (error) {
            console.error('Error validating ECs:', error);
        }
    }
    </script>
</body>
</html>

