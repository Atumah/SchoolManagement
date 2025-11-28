<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireAuth();

$currentUser = getCurrentUser();
$isStudent = $currentUser['role'] === 'Student';
$isTeacherOrAdmin = hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer']);

$flash = getFlashMessage();

// Handle CRUD operations (only for teachers/admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTeacherOrAdmin) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /grades/view.php' . (isset($_GET['student']) ? '?student=' . urlencode($_GET['student']) : '') . (isset($_GET['year']) ? '&year=' . (int)$_GET['year'] : ''));
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $originalGrade = (float) ($_POST['original_grade'] ?? 0);

            if ($originalGrade < 1.0 || $originalGrade > 10.0) {
                setFlashMessage('error', 'Original grade must be between 1.0 and 10.0');
                header('Location: /grades/view.php' . (isset($_GET['student']) ? '?student=' . urlencode($_GET['student']) : '') . (isset($_GET['year']) ? '&year=' . (int)$_GET['year'] : ''));
                exit;
            }

            $gradeData = [
                'student_id' => $_POST['student_id'],
                'course_id' => $_POST['course_id'],
                'teacher_id' => $currentUser['id'],
                'original_grade' => $originalGrade,
                'date' => date('Y-m-d'), // Always use current date
                'notes' => $_POST['notes'] ?? ''
            ];

            try {
                if ($_POST['action'] === 'add') {
                    addGrade($gradeData);
                    setFlashMessage('success', 'Grade added successfully');
                } else {
                    $gradeId = $_POST['id'] ?? null;
                    if (!$gradeId) {
                        setFlashMessage('error', 'Grade ID is required for editing');
                        header('Location: /grades/view.php' . (isset($_GET['student']) ? '?student=' . urlencode($_GET['student']) : '') . (isset($_GET['year']) ? '&year=' . (int)$_GET['year'] : ''));
                        exit;
                    }
                    $result = updateGrade($gradeId, $gradeData);
                    if ($result) {
                        setFlashMessage('success', 'Grade updated successfully');
                    } else {
                        setFlashMessage('error', 'Failed to update grade. Please try again.');
                    }
                }
            } catch (Exception $e) {
                setFlashMessage('error', $e->getMessage());
            }

            $redirectUrl = '/grades/view.php';
            if (isset($_POST['student_id'])) {
                $redirectUrl .= '?student=' . urlencode($_POST['student_id']);
            }
            if (isset($_GET['year'])) {
                $redirectUrl .= '&year=' . (int)$_GET['year'];
            }
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            deleteGrade($id);
            setFlashMessage('success', 'Grade deleted successfully');
            header('Location: /grades/view.php' . (isset($_GET['student']) ? '?student=' . urlencode($_GET['student']) : '') . (isset($_GET['year']) ? '&year=' . (int)$_GET['year'] : ''));
            exit;
        }
    }
}

// Get student and year from URL
$viewingStudentId = $_GET['student'] ?? null;
$year = isset($_GET['year']) ? (int) $_GET['year'] : null;

// Determine which student we're viewing
$studentId = null;
if ($isStudent) {
    $studentId = $currentUser['id'];
} elseif ($viewingStudentId) {
    $studentId = $viewingStudentId;
}

// Get student info
$viewingStudent = $studentId ? getUserById($studentId) : null;

// Get courses and grades for the selected year (or all years if no year specified)
$courses = [];
$grades = [];
$coursesById = [];

if ($studentId) {
    if ($year) {
        // Get courses for specific year
        $courses = getStudentCoursesByYear($studentId, $year);
        $grades = getGradesByStudentAndYear($studentId, $year);
    } else {
        // Get all courses for student
        $courses = getCoursesByStudent($studentId);
        $grades = getGradesByStudent($studentId);
    }

    // Index courses by id for quick lookup
    foreach ($courses as $course) {
        $coursesById[$course['id']] = $course;
    }

    // Sort grades by date (most recent first)
    usort($grades, static function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?= $year ? "Year $year Grades" : 'Grades' ?> - <?= $viewingStudent ? htmlspecialchars($viewingStudent['name']) : 'School Management' ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>

        <div class="page-header">
            <h1>
                <?php if ($year) : ?>
                    Year <?= $year ?> Grades
                <?php else : ?>
                    Grades
                <?php endif; ?>
                <?php if ($viewingStudent && !$isStudent) : ?>
                    - <?= htmlspecialchars($viewingStudent['name']) ?>
                <?php endif; ?>
            </h1>
            <?php if ($isTeacherOrAdmin && $studentId) : ?>
                <button class="form-toggle-btn" onclick="openAddGradeModal()">+ Add Grade</button>
            <?php endif; ?>
        </div>

        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Grades Table -->
        <div class="table-container">
            <?php if (empty($courses) && empty($grades)) : ?>
                <div class="empty-state">
                    <p><?= $year ? "No courses enrolled for Year $year." : 'No courses found.' ?></p>
                </div>
            <?php else : ?>
                <table class="grades-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable('course_name')">
                                <span class="sortable-header">
                                    Course Name
                                    <span class="sort-icon" id="sort-icon-course_name"></span>
                                </span>
                            </th>
                            <th onclick="sortTable('teacher')">
                                <span class="sortable-header">
                                    Teacher
                                    <span class="sort-icon" id="sort-icon-teacher"></span>
                                </span>
                            </th>
                            <th onclick="sortTable('ec')">
                                <span class="sortable-header">
                                    EC
                                    <span class="sort-icon" id="sort-icon-ec"></span>
                                </span>
                            </th>
                            <th onclick="sortTable('date')">
                                <span class="sortable-header">
                                    Date Published
                                    <span class="sort-icon" id="sort-icon-date"></span>
                                </span>
                            </th>
                            <th onclick="sortTable('original_grade')">
                                <span class="sortable-header">
                                    Original Grade
                                    <span class="sort-icon" id="sort-icon-original_grade"></span>
                                </span>
                            </th>
                            <th onclick="sortTable('final_grade')">
                                <span class="sortable-header">
                                    Final Grade
                                    <span class="sort-icon" id="sort-icon-final_grade"></span>
                                </span>
                            </th>
                            <?php if ($isTeacherOrAdmin) : ?>
                            <th style="width: 50px;"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grades)) : ?>
                            <?php foreach ($courses as $index => $course) : ?>
                                <?php
                                $teacher = getUserById($course['teacher_id']);
                                ?>
                                <tr data-original-index="<?= $index ?>"
                                    data-course-name="<?= htmlspecialchars(strtolower($course['name']), ENT_QUOTES, 'UTF-8') ?>"
                                    data-teacher-name="<?= htmlspecialchars(strtolower($teacher['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-ec="<?= $course['credits'] ?? 5 ?>"
                                    data-date="0"
                                    data-original-grade="-1"
                                    data-final-grade="-1">
                                    <td><strong><?= htmlspecialchars($course['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($teacher['name'] ?? 'Unknown') ?></td>
                                    <td><?= $course['credits'] ?? 5 ?> ECs</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td><span style="color: var(--text-muted); font-style: italic;">No grade yet</span></td>
                                    <?php if ($isTeacherOrAdmin) : ?>
                                    <td>
                                        <div class="actions-dropdown">
                                            <button type="button" class="actions-menu-btn" onclick="toggleActionsMenu(this, event)">⋮</button>
                                            <div class="actions-menu">
                                                <button type="button" onclick="openAddGradeModalForCourse('<?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8') ?>'); toggleActionsMenu(event.target.closest('.actions-dropdown').querySelector('.actions-menu-btn'));">Add Grade</button>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php foreach ($grades as $index => $grade) : ?>
                                <?php
                                $course = $coursesById[$grade['course_id']] ?? null;
                                if (!$course) {
                                    continue;
                                } // Skip if course not found

                                $hasGrade = true;
                                $isPassing = isPassingGrade((int) $grade['final_grade']);
                                $rowClass = $isPassing ? 'grade-row-passed' : 'grade-row-failed';

                                $teacher = getUserById($course['teacher_id']);
                                ?>
                                <tr class="<?= $rowClass ?>"
                                    data-original-index="<?= $index ?>"
                                    data-course-name="<?= htmlspecialchars(strtolower($course['name']), ENT_QUOTES, 'UTF-8') ?>"
                                    data-teacher-name="<?= htmlspecialchars(strtolower($teacher['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-ec="<?= $course['credits'] ?? 5 ?>"
                                    data-date="<?= strtotime($grade['updated_at'] ?? $grade['date']) ?>"
                                    data-original-grade="<?= (float)$grade['original_grade'] ?>"
                                    data-final-grade="<?= (int)$grade['final_grade'] ?>">
                                    <td><strong><?= htmlspecialchars($course['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($teacher['name'] ?? 'Unknown') ?></td>
                                    <td><?= $course['credits'] ?? 5 ?> ECs</td>
                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($grade['updated_at'] ?? $grade['date']))) ?></td>
                                    <td><?= number_format((float) $grade['original_grade'], 1) ?></td>
                                    <td><strong><?= $grade['final_grade'] ?></strong></td>
                                    <?php if ($isTeacherOrAdmin) : ?>
                                    <td>
                                        <div class="actions-dropdown">
                                            <button type="button" class="actions-menu-btn" onclick="toggleActionsMenu(this, event)">⋮</button>
                                            <div class="actions-menu">
                                                <button type="button" class="edit-grade-btn-view"
                                                        data-grade='<?= htmlspecialchars(json_encode($grade, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                                        data-course='<?= htmlspecialchars(json_encode($course, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $grade['id'] ?>">
                                                    <button type="submit" class="delete-action">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php
                            // Show courses without grades
                            $coursesWithGrades = array_column($grades, 'course_id');
foreach ($courses as $index => $course) {
    if (!in_array($course['id'], $coursesWithGrades, true)) {
        $teacher = getUserById($course['teacher_id']);
        ?>
                                    <tr data-original-index="<?= count($grades) + $index ?>"
                                        data-course-name="<?= htmlspecialchars(strtolower($course['name']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-teacher-name="<?= htmlspecialchars(strtolower($teacher['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-ec="<?= $course['credits'] ?? 5 ?>"
                                        data-date="0"
                                        data-original-grade="-1"
                                        data-final-grade="-1">
                                        <td><strong><?= htmlspecialchars($course['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($teacher['name'] ?? 'Unknown') ?></td>
                                        <td><?= $course['credits'] ?? 5 ?> ECs</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td><span style="color: var(--text-muted); font-style: italic;">No grade yet</span></td>
                                        <?php if ($isTeacherOrAdmin) : ?>
                                        <td>
                                            <div class="actions-dropdown">
                                                <button type="button" class="actions-menu-btn" onclick="toggleActionsMenu(this, event)">⋮</button>
                                                <div class="actions-menu">
                                                    <button type="button" onclick="openAddGradeModalForCourse('<?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8') ?>'); toggleActionsMenu(event.target.closest('.actions-dropdown').querySelector('.actions-menu-btn'));">Add Grade</button>
                                                </div>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php
    }
}
?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add/Edit Grade Modal (for teachers/admins) -->
    <?php if ($isTeacherOrAdmin && $studentId) : ?>
        <div id="gradeModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeGradeModal()">&times;</span>
                <h2 id="gradeModalTitle">Add Grade</h2>
                <form id="gradeForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" id="gradeAction" value="add">
                    <input type="hidden" name="id" id="gradeId" value="">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                    <?php if ($year) : ?>
                        <input type="hidden" name="year" value="<?= $year ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="gradeCourseSelect">Course</label>
                        <select id="gradeCourseSelect" required></select>
                        <input type="hidden" id="gradeCourseIdHidden" name="course_id" value="">
                    </div>

                    <div class="form-group">
                        <label for="gradeOriginal">Grade (1.0 - 10.0)</label>
                        <input type="number" id="gradeOriginal" name="original_grade" step="0.1" min="1.0" max="10.0" required>
                        <small style="color: var(--text-muted);">Final grade will be automatically rounded (e.g., 7.5 → 8)</small>
                    </div>

                    <div class="form-group">
                        <label for="gradeNotes">Notes (Optional)</label>
                        <textarea id="gradeNotes" name="notes" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Grade</button>
                    <button type="button" class="btn btn-secondary" onclick="closeGradeModal()">Cancel</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        <?php if ($isTeacherOrAdmin && $studentId) : ?>
        const allCourses = <?= json_encode($courses) ?>;

        function openAddGradeModal() {
            const modal = document.getElementById('gradeModal');
            const courseSelect = document.getElementById('gradeCourseSelect');
            const courseIdHidden = document.getElementById('gradeCourseIdHidden');

            // Populate course dropdown with all courses for that year/student
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            allCourses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name + ' (' + (course.credits || 5) + ' ECs)';
                courseSelect.appendChild(option);
            });

            // Reset form
            document.getElementById('gradeModalTitle').textContent = 'Add Grade';
            document.getElementById('gradeAction').value = 'add';
            document.getElementById('gradeId').value = '';
            courseSelect.value = '';
            courseSelect.disabled = false;
            courseIdHidden.value = '';
            document.getElementById('gradeOriginal').value = '';
            document.getElementById('gradeNotes').value = '';

            // Sync hidden field when course changes
            courseSelect.onchange = function() {
                courseIdHidden.value = this.value;
            };

            modal.style.display = 'block';
        }

        function openAddGradeModalForCourse(courseId, courseName) {
            openAddGradeModal();
            // Set the selected course
            const courseSelect = document.getElementById('gradeCourseSelect');
            const courseIdHidden = document.getElementById('gradeCourseIdHidden');
            courseSelect.value = courseId;
            courseIdHidden.value = courseId;
        }

        function openEditGradeModal(grade, course) {
            const modal = document.getElementById('gradeModal');
            const courseSelect = document.getElementById('gradeCourseSelect');

            // Populate course dropdown
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            allCourses.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = c.name + ' (' + (c.credits || 5) + ' ECs)';
                option.selected = c.id === course.id;
                courseSelect.appendChild(option);
            });

            // Populate form
            document.getElementById('gradeModalTitle').textContent = 'Edit Grade';
            document.getElementById('gradeAction').value = 'edit';
            document.getElementById('gradeId').value = grade.id;
            courseSelect.value = grade.course_id;
            courseSelect.disabled = true; // Can't change course when editing
            // Set hidden field since disabled select won't submit
            document.getElementById('gradeCourseIdHidden').value = grade.course_id;
            document.getElementById('gradeOriginal').value = grade.original_grade;
            document.getElementById('gradeNotes').value = grade.notes || '';

            modal.style.display = 'block';
        }

        // Reset course select enabled state when closing modal
        function closeGradeModal() {
            const courseSelect = document.getElementById('gradeCourseSelect');
            if (courseSelect) {
                courseSelect.disabled = false;
            }
            document.getElementById('gradeModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('gradeModal');
            if (event.target === modal) {
                closeGradeModal();
            }
        }

        // Table sorting functionality
        let currentSort = { column: null, order: 'natural' }; // 'asc', 'desc', 'natural'

        function sortTable(column) {
            const tbody = document.querySelector('.grades-table tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr'));

            // Cycle through sort orders: natural -> asc -> desc -> natural
            if (currentSort.column === column) {
                if (currentSort.order === 'natural') {
                    currentSort.order = 'asc';
                } else if (currentSort.order === 'asc') {
                    currentSort.order = 'desc';
                } else {
                    currentSort.order = 'natural';
                }
            } else {
                currentSort.column = column;
                currentSort.order = 'asc';
            }

            // Update sort icons
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.className = 'sort-icon';
            });

            const sortIcon = document.getElementById('sort-icon-' + column);
            if (sortIcon) {
                if (currentSort.order === 'asc') {
                    sortIcon.className = 'sort-icon asc';
                } else if (currentSort.order === 'desc') {
                    sortIcon.className = 'sort-icon desc';
                } else {
                    sortIcon.className = 'sort-icon';
                }
            }

            if (currentSort.order === 'natural') {
                // Restore original order
                rows.sort((a, b) => {
                    const aIndex = parseInt(a.dataset.originalIndex || '0');
                    const bIndex = parseInt(b.dataset.originalIndex || '0');
                    return aIndex - bIndex;
                });
                rows.forEach((row, index) => {
                    row.dataset.originalIndex = index;
                    tbody.appendChild(row);
                });
            } else {
                // Sort rows
                rows.sort((a, b) => {
                    let aVal, bVal;

                    if (column === 'course_name') {
                        aVal = a.dataset.courseName || '';
                        bVal = b.dataset.courseName || '';
                        return currentSort.order === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                    } else if (column === 'teacher') {
                        aVal = a.dataset.teacherName || '';
                        bVal = b.dataset.teacherName || '';
                        return currentSort.order === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                    } else if (column === 'ec') {
                        aVal = parseFloat(a.dataset.ec || 0);
                        bVal = parseFloat(b.dataset.ec || 0);
                        return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
                    } else if (column === 'date') {
                        aVal = parseInt(a.dataset.date || 0);
                        bVal = parseInt(b.dataset.date || 0);
                        return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
                    } else if (column === 'original_grade') {
                        aVal = parseFloat(a.dataset.originalGrade || -1);
                        bVal = parseFloat(b.dataset.originalGrade || -1);
                        // Filter out -1 (no grade) values - they go last
                        if (aVal === -1 && bVal === -1) return 0;
                        if (aVal === -1) return 1;
                        if (bVal === -1) return -1;
                        return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
                    } else if (column === 'final_grade') {
                        aVal = parseFloat(a.dataset.finalGrade || -1);
                        bVal = parseFloat(b.dataset.finalGrade || -1);
                        // Filter out -1 (no grade) values - they go last
                        if (aVal === -1 && bVal === -1) return 0;
                        if (aVal === -1) return 1;
                        if (bVal === -1) return -1;
                        return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
                    }
                    return 0;
                });

                rows.forEach((row) => {
                    tbody.appendChild(row);
                });
            }
        }

        // Actions menu toggle
        function toggleActionsMenu(button, event) {
            if (event) {
                event.stopPropagation();
            }

            const dropdown = button.closest('.actions-dropdown');
            const menu = dropdown.querySelector('.actions-menu');
            const isOpen = menu.classList.contains('show');

            // Close all other menus
            document.querySelectorAll('.actions-menu').forEach(m => {
                m.classList.remove('show', 'show-above', 'show-below');
            });

            if (!isOpen) {
                // Get menu dimensions (force it to be visible temporarily to measure)
                menu.style.visibility = 'hidden';
                menu.style.display = 'block';
                const menuHeight = menu.offsetHeight;
                menu.style.display = '';
                menu.style.visibility = '';

                // Get dropdown position
                const rect = dropdown.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;
                const menuHeightWithMargin = menuHeight + 10; // Add some margin

                // Check if we're in the last row (row is near bottom of viewport)
                const tableBody = dropdown.closest('tbody');
                const tableRect = tableBody ? tableBody.getBoundingClientRect() : null;
                const isLastRow = tableRect ? (rect.bottom > tableRect.bottom - 50) : false;

                // Position menu above if:
                // 1. Not enough space below (with margin)
                // 2. OR we're in the last row
                // AND there's more space above than below
                if ((spaceBelow < menuHeightWithMargin || isLastRow) && spaceAbove > menuHeightWithMargin) {
                    menu.classList.add('show', 'show-above');
                } else {
                    menu.classList.add('show', 'show-below');
                }
            }
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.actions-dropdown')) {
                document.querySelectorAll('.actions-menu').forEach(menu => {
                    menu.classList.remove('show', 'show-above', 'show-below');
                });
            }
        });

        // Attach event listeners for edit grade buttons
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const editGradeButtons = document.querySelectorAll('.edit-grade-btn-view');
                if (editGradeButtons && editGradeButtons.length > 0) {
                    editGradeButtons.forEach(button => {
                        if (!button) return;
                        button.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const gradeData = this.getAttribute('data-grade');
                            const courseData = this.getAttribute('data-course');
                            if (gradeData && courseData) {
                                try {
                                    const grade = JSON.parse(gradeData);
                                    const course = JSON.parse(courseData);
                                    openEditGradeModal(grade, course);
                                    // Close the actions menu
                                    const menuBtn = this.closest('.actions-dropdown')?.querySelector('.actions-menu-btn');
                                    if (menuBtn) {
                                        toggleActionsMenu(menuBtn, e);
                                    }
                                } catch (err) {
                                    console.error('Error parsing grade/course data:', err);
                                    alert('Error loading grade data. Please refresh the page.');
                                }
                            }
                        });
                    });
                }
            } catch (e) {
                console.error('Error attaching grade button listeners:', e);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
