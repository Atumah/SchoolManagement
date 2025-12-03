<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

function getDBConnection(): PDO {
    return \App\Database\Database::connection();
}

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Only teachers can edit, principal/admin can view
if (!hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
    header('Location: /');
    exit;
}

$pdo = getDBConnection();
$role = $currentUser['role'];
$canEdit = hasRole('Teacher');

// Get teacher's courses
$coursesStmt = $pdo->prepare("SELECT id, name, description, schedule FROM courses WHERE teacher_id = ? ORDER BY name");
$coursesStmt->execute([$currentUser['id']]);
$courses = $coursesStmt->fetchAll();

$selectedCourseId = $_GET['course'] ?? $courses[0]['id'] ?? null;
$selectedMonth = $_GET['month'] ?? date('Y-m'); // YYYY-MM format

// Get students in selected course
$students = [];
$courseInfo = null;
if ($selectedCourseId) {
    $courseStmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $courseStmt->execute([$selectedCourseId, $currentUser['id']]);
    $courseInfo = $courseStmt->fetch();

    if ($courseInfo) {
        $studentsStmt = $pdo->prepare("SELECT u.id, u.name, u.first_name, u.last_name FROM course_students cs JOIN users u ON cs.student_id = u.id WHERE cs.course_id = ? AND u.role = 'Student' AND u.status = 'Active' ORDER BY u.name");
        $studentsStmt->execute([$selectedCourseId]);
        $students = $studentsStmt->fetchAll();
    }
}

// Handle attendance updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit && verifyCSRFToken($_POST['csrf_token'])) {
    $updates = $_POST['attendance'] ?? [];
    foreach ($updates as $studentId => $dates) {
        foreach ($dates as $date => $status) {
            $notes = trim($_POST['notes'][$studentId][$date] ?? '');
            // Validate status enum values strictly
            if ($status !== 'Present' && $status !== 'Absent') {
                // If invalid or empty string, skip saving or set to NULL if permitted
                continue;
            }
            $stmt = $pdo->prepare("INSERT INTO attendance (id, student_id, course_id, teacher_id, date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)");
            $id = 'att_' . bin2hex(random_bytes(12));
            $stmt->execute([$id, $studentId, $selectedCourseId, $currentUser['id'], $date, $status, $notes]);
        }
    }

    setFlashMessage('success', 'Attendance updated');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?course=' . urlencode($selectedCourseId) . '&month=' . urlencode($selectedMonth));
    exit;
}

$csrfToken = generateCSRFToken();

// Generate days for selected month
$monthDays = [];
if ($selectedMonth) {
    $startDate = new DateTime($selectedMonth . '-01');
    $endDate = clone $startDate;
    $endDate->modify('last day of this month');

    $current = clone $startDate;
    while ($current <= $endDate) {
        $monthDays[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <style>
        .attendance-container { min-height: 60vh; }
        .course-header { display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; }
        .course-info { flex: 1; }
        .course-selectors { display: flex; gap: 1rem; flex-wrap: wrap; }
        .month-nav { display: flex; gap: 0.5rem; align-items: center; }
        .attendance-table { overflow-x: auto; background: #1a1a2e; border-radius: 12px; border: 1px solid #444; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 0.75rem; text-align: center; border-bottom: 1px solid #333; }
        th { background: #16213e; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .student-name { text-align: left !important; font-weight: 500; background: #1a1a2e; position: sticky; left: 0; z-index: 5; padding-left: 1rem; }
        .day-header { white-space: nowrap; }
        .status-present { background: #10b981; color: white; border-radius: 4px; padding: 0.25rem 0.5rem; font-size: 0.8rem; font-weight: bold; }
        .status-absent { background: #ef4444; color: white; border-radius: 4px; padding: 0.25rem 0.5rem; font-size: 0.8rem; font-weight: bold; }
        .status-empty { background: #374151; color: #9ca3af; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
        .status-empty:hover { background: #4b5563; border-color: #6b7280; }
        .notes-input { width: 100%; height: 60px; background: #1e293b; border: 1px solid #475569; border-radius: 4px; color: white; padding: 0.5rem; font-size: 0.8rem; resize: vertical; }
        .notes-input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
        .no-data { text-align: center; padding: 3rem; color: #94a3b8; }
        @media (max-width: 768px) { .course-selectors { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<main class="container">
    <?php include __DIR__ . '/../includes/back-button.php'; ?>

    <div class="page-header">
        <h1>Attendance</h1>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="attendance-container">
        <?php if (empty($courses)): ?>
            <div class="no-data">
                <h3>No Courses</h3>
                <p><?= $canEdit ? 'Create courses first in Courses section' : 'No courses available' ?></p>
            </div>
        <?php else: ?>
            <div class="course-header">
                <div class="course-info">
                    <?php if ($courseInfo): ?>
                        <h2><?= htmlspecialchars($courseInfo['name']) ?></h2>
                        <p><?= htmlspecialchars($courseInfo['description'] ?: 'No description') ?> • <?= htmlspecialchars($courseInfo['schedule'] ?: 'Schedule TBD') ?></p>
                    <?php endif; ?>
                </div>

                <div class="course-selectors">
                    <select onchange="changeCourse(this.value)" <?= $canEdit ? '' : 'disabled' ?>>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= htmlspecialchars($course['id']) ?>" <?= $selectedCourseId === $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="month-nav">
                        <button class="btn btn-secondary btn-small" onclick="changeMonth(-1)">‹</button>
                        <span><?= $selectedMonth ? date('M Y', strtotime($selectedMonth . '-01')) : 'Select course' ?></span>
                        <button class="btn btn-secondary btn-small" onclick="changeMonth(1)">›</button>
                    </div>

                    <?php if ($canEdit): ?>
                        <button class="btn btn-primary" onclick="saveAttendance()">💾 Save All</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedCourseId && $courseInfo && !empty($monthDays)): ?>
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="attendance-table">
                        <table>
                            <thead>
                            <tr>
                                <th class="student-name">Student</th>
                                <?php foreach ($monthDays as $day): ?>
                                    <th class="day-header"><?= date('D M j', strtotime($day)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="student-name"><?= htmlspecialchars($student['name']) ?></td>
                                    <?php foreach ($monthDays as $day): ?>
                                        <td>
                                            <?php
                                            // Get existing attendance
                                            $attendanceStmt = $pdo->prepare("SELECT status, notes FROM attendance WHERE student_id = ? AND course_id = ? AND date = ?");
                                            $attendanceStmt->execute([$student['id'], $selectedCourseId, $day]);
                                            $attendance = $attendanceStmt->fetch();
                                            ?>

                                            <div class="attendance-status" data-student="<?= htmlspecialchars($student['id']) ?>" data-date="<?= $day ?>">
                                                <?php if ($attendance): ?>
                                                    <span class="status-<?= strtolower($attendance['status']) ?>"><?= $attendance['status'][0] ?></span>
                                                <?php else: ?>
                                                    <span class="status-empty" onclick="toggleAttendance(this)">&nbsp;</span>
                                                <?php endif; ?>
                                            </div>

                                            <input type="hidden" name="attendance[<?= htmlspecialchars($student['id']) ?>][<?= $day ?>]"
                                                   value="<?= $attendance['status'] ?? '' ?>" id="status_<?= htmlspecialchars($student['id']) ?>_<?= $day ?>">

                                            <textarea name="notes[<?= htmlspecialchars($student['id']) ?>][<?= $day ?>]"
                                                      class="notes-input" placeholder="Notes..."
                                                              <?= $attendance['notes'] ?? '' ?>></textarea>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php elseif ($selectedCourseId): ?>
                <div class="no-data">No students enrolled in this course</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    function changeCourse(courseId) {
        window.location.href = `?course=${encodeURIComponent(courseId)}&month=<?= urlencode($selectedMonth) ?>`;
    }

    function changeMonth(direction) {
        const [year, month] = '<?= $selectedMonth ?>'.split('-');
        const date = new Date(year, month - 1 + direction, 1);
        const newMonth = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        window.location.href = `?course=<?= urlencode($selectedCourseId) ?>&month=${newMonth}`;
    }

    function toggleAttendance(element) {
        const studentId = element.dataset.student;
        const date = element.dataset.date;
        const statusInput = document.getElementById(`status_${studentId}_${date}`);
        const currentStatus = statusInput.value;

        let newStatus = '';
        if (currentStatus === '') {
            newStatus = 'Present';
            element.innerHTML = 'P';
            element.className = 'status-present';
        } else if (currentStatus === 'Present') {
            newStatus = 'Absent';
            element.innerHTML = 'A';
            element.className = 'status-absent';
        } else {
            newStatus = '';
            element.innerHTML = '&nbsp;';
            element.className = 'status-empty';
        }

        statusInput.value = newStatus;
    }

    function saveAttendance() {
        if (confirm('Save all attendance changes?')) {
            document.getElementById('attendanceForm').submit();
        }
    }

    // Auto-save every 30 seconds (optional)
    setInterval(function() {
        const form = document.getElementById('attendanceForm');
        if (form && form.querySelector('input[value="Present"], input[value="Absent"]')) {
            // Could add auto-save here
        }
    }, 30000);
</script>
</body>
</html>
