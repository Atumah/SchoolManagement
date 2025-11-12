<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireAuth();

$currentUser = getCurrentUser();
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
                $teacherId = (int)$_POST['teacher_id'];
                $schedule = $_POST['schedule'] ?? '';
                $maxStudents = (int)($_POST['max_students'] ?? 30);

                if (empty($name) || empty($teacherId)) {
                    setFlashMessage('error', 'Course name and teacher are required');
                } else {
                    addCourse([
                        'name' => $name,
                        'description' => $description,
                        'teacher_id' => $teacherId,
                        'schedule' => $schedule,
                        'max_students' => $maxStudents
                    ]);
                    setFlashMessage('success', 'Course added successfully');
                    header('Location: /courses/course.php');
                    exit;
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $course = getCourseById($id);
            if ($course && (hasRole('Admin') || $course['teacher_id'] == $currentUser['id'])) {
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $teacherId = (int)$_POST['teacher_id'];
                $schedule = $_POST['schedule'] ?? '';
                $maxStudents = (int)$_POST['max_students'];

                if ($maxStudents < count($course['students'])) {
                    setFlashMessage('error', 'Cannot reduce max students below current enrollment');
                } else {
                    updateCourse($id, [
                        'name' => $name,
                        'description' => $description,
                        'teacher_id' => $teacherId,
                        'schedule' => $schedule,
                        'max_students' => $maxStudents
                    ]);
                    setFlashMessage('success', 'Course updated successfully');
                    header('Location: /courses/course.php');
                    exit;
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $course = getCourseById($id);
            if ($course && (hasRole('Admin') || $course['teacher_id'] == $currentUser['id'])) {
                deleteCourse($id);
                setFlashMessage('success', 'Course deleted successfully');
                header('Location: /courses/course.php');
                exit;
            }
        } elseif ($_POST['action'] === 'add_student') {
            $courseId = (int)$_POST['course_id'];
            $studentId = (int)$_POST['student_id'];
            $course = getCourseById($courseId);

            if ($course && (hasRole('Admin') || $course['teacher_id'] == $currentUser['id'])) {
                if (joinCourse($courseId, $studentId)) {
                    setFlashMessage('success', 'Student added to course successfully');
                } else {
                    setFlashMessage('error', 'Failed to add student. Student may already be enrolled or course is full.');
                }
                header('Location: /courses/course.php?view=' . $courseId);
                exit;
            }
        } elseif ($_POST['action'] === 'remove_student') {
            $courseId = (int)$_POST['course_id'];
            $studentId = (int)$_POST['student_id'];
            $course = getCourseById($courseId);

            if ($course && (hasRole('Admin') || $course['teacher_id'] == $currentUser['id'])) {
                if (leaveCourse($courseId, $studentId)) {
                    setFlashMessage('success', 'Student removed from course successfully');
                } else {
                    setFlashMessage('error', 'Failed to remove student.');
                }
                header('Location: /courses/course.php?view=' . $courseId);
                exit;
            }
        }
    }
}

// Get all courses
$courses = getAllCourses();
$teachers = array_filter(getAllUsers(), fn($u) => $u['role'] === 'Teacher');
$students = array_filter(getAllUsers(), fn($u) => $u['role'] === 'Student');

// Filtering
$filterTeacher = $_GET['filter_teacher'] ?? '';
$search = $_GET['search'] ?? '';

if ($filterTeacher) {
    $courses = array_filter($courses, fn($c) => $c['teacher_id'] == $filterTeacher);
}
if ($search) {
    $courses = array_filter($courses, fn($c) => stripos($c['name'], $search) !== false);
}

$editingId = $_GET['edit'] ?? null;
$viewingId = $_GET['view'] ?? null;
$editingCourse = $editingId ? getCourseById((int)$editingId) : null;
$viewingCourse = $viewingId ? getCourseById((int)$viewingId) : null;
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
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
            <!-- Course Detail View -->
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
                    <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] == $currentUser['id']) : ?>
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
                                        <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] == $currentUser['id']) : ?>
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
                    
                    <?php if (hasRole('Admin') || $viewingCourse['teacher_id'] == $currentUser['id']) : ?>
                        <?php
                        $enrolledStudentIds = $viewingCourse['students'];
                        $availableStudents = array_filter($students, fn($s) => !in_array($s['id'], $enrolledStudentIds));
                        ?>
                        <?php if (count($viewingCourse['students']) < $viewingCourse['max_students'] && !empty($availableStudents)) : ?>
                            <div class="add-student-form">
                                <button type="button" class="btn btn-primary" onclick="openAddStudentModal(<?= $viewingCourse['id'] ?>, <?= htmlspecialchars(json_encode($availableStudents), ENT_QUOTES, 'UTF-8') ?>)">+ Add Student</button>
                            </div>
                        <?php elseif (count($viewingCourse['students']) >= $viewingCourse['max_students']) : ?>
                            <p style="color: var(--text-muted); margin-top: 1rem;">Course is full (<?= $viewingCourse['max_students'] ?> students).</p>
                        <?php elseif (empty($availableStudents)) : ?>
                            <p style="color: var(--text-muted); margin-top: 1rem;">All students are already enrolled.</p>
                        <?php endif; ?>
                    <?php endif; ?>
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
                            <option value="<?= $teacher['id'] ?>" <?= $filterTeacher == $teacher['id'] ? 'selected' : '' ?>>
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
                                        <?php if (hasRole('Admin') || $course['teacher_id'] == $currentUser['id']) : ?>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddCourseModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
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
    // Edit Course Modal Functions
    function openEditModal(course) {
        const modal = document.getElementById('editCourseModal');
        if (!modal) return;
        
        // Populate form fields
        document.getElementById('edit_course_id').value = course.id;
        document.getElementById('edit_name').value = course.name || '';
        document.getElementById('edit_description').value = course.description || '';
        document.getElementById('edit_teacher_id').value = course.teacher_id || '';
        document.getElementById('edit_schedule').value = course.schedule || '';
        document.getElementById('edit_max_students').value = course.max_students || '30';
        
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
        const modal = document.getElementById('addCourseModal');
        if (!modal) return;
        
        // Reset form fields
        document.getElementById('add_name').value = '';
        document.getElementById('add_description').value = '';
        document.getElementById('add_teacher_id').value = '';
        document.getElementById('add_schedule').value = '';
        document.getElementById('add_max_students').value = '30';
        
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
    
    // Add Student Modal Functions
    function openAddStudentModal(courseId, availableStudents) {
        const modal = document.getElementById('addStudentModal');
        if (!modal) return;
        
        // Set course ID
        document.getElementById('add_student_course_id').value = courseId;
        
        // Populate student dropdown
        const studentSelect = document.getElementById('add_student_id');
        studentSelect.innerHTML = '<option value="">Select Student</option>';
        
        if (availableStudents && Array.isArray(availableStudents)) {
            availableStudents.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = student.name;
                studentSelect.appendChild(option);
            });
        }
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
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
    
    // Attach event listeners to all edit buttons
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-course-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const courseData = this.getAttribute('data-course');
                if (courseData) {
                    try {
                        const course = JSON.parse(courseData);
                        openEditModal(course);
                    } catch (e) {
                        console.error('Error parsing course data:', e);
                    }
                }
            });
        });
    });
    
    // Close any modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            closeAddCourseModal();
            closeAddStudentModal();
        }
    });
    </script>
</body>
</html>

