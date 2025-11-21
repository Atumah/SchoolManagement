<?php
// Course Grades Tab - Shows all enrolled students with their grades for this course
// Variables expected: $courseId, $isTeacherOrAdmin, $viewingCourse, $gradesByStudentId, $csrfToken

// Get all enrolled students for this course
$enrolledStudents = [];
if ($viewingCourse && !empty($viewingCourse['students'])) {
    foreach ($viewingCourse['students'] as $studentId) {
        $student = getUserById($studentId);
        if ($student) {
            $enrolledStudents[] = $student;
            // Note: Grade records are created automatically when students join courses via joinCourse()
            // We don't create them here - only display existing grades
        }
    }
}
?>

<div class="table-container">
    <?php if (empty($enrolledStudents)) : ?>
        <div class="empty-state">
            <p>No students enrolled in this course yet.</p>
        </div>
    <?php else : ?>
        <table class="grades-table">
            <thead>
                <tr>
                    <th onclick="sortTable('student_name')">
                        <span class="sortable-header">
                            Student Name
                            <span class="sort-icon" id="sort-icon-student_name"></span>
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
                <?php foreach ($enrolledStudents as $index => $student) : ?>
                    <?php
                    $grade = $gradesByStudentId[$student['id']] ?? null;
                    $hasGrade = $grade && $grade['original_grade'] !== null;
                    $originalGrade = $hasGrade ? (float)$grade['original_grade'] : null;
                    $finalGrade = $hasGrade ? (int)$grade['final_grade'] : null;
                    $datePublished = $hasGrade && $grade['date'] ? date('Y-m-d', strtotime($grade['updated_at'] ?? $grade['date'])) : null;

                    // Determine row class for pass/fail
                    $rowClass = '';
                    if ($hasGrade) {
                        $rowClass = isPassingGrade($finalGrade) ? 'grade-row-passed' : 'grade-row-failed';
                    }

                    // Data attributes for sorting
                    $dataDate = $datePublished ? strtotime($datePublished) : 0;
                    $dataOriginalGrade = $originalGrade !== null ? $originalGrade : -1;
                    $dataFinalGrade = $finalGrade !== null ? $finalGrade : -1;
                    ?>
                    <tr class="<?= $rowClass ?>"
                        data-original-index="<?= $index ?>"
                        data-student-name="<?= htmlspecialchars(strtolower($student['name']), ENT_QUOTES, 'UTF-8') ?>"
                        data-ec="<?= $viewingCourse['credits'] ?? 5 ?>"
                        data-date="<?= $dataDate ?>"
                        data-original-grade="<?= $dataOriginalGrade ?>"
                        data-final-grade="<?= $dataFinalGrade ?>">
                        <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                        <td><?= $viewingCourse['credits'] ?? 5 ?> ECs</td>
                        <td><?= $datePublished ?? '-' ?></td>
                        <td><?= $originalGrade !== null ? number_format($originalGrade, 1) : '-' ?></td>
                        <td>
                            <?php if ($finalGrade !== null) : ?>
                                <strong><?= $finalGrade ?></strong>
                            <?php else : ?>
                                <span style="color: var(--text-muted); font-style: italic;">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isTeacherOrAdmin) : ?>
                        <td>
                            <div class="actions-dropdown">
                                <button type="button" class="actions-menu-btn" onclick="toggleActionsMenu(this, event)">⋮</button>
                                <div class="actions-menu">
                                    <?php if ($hasGrade && $grade) : ?>
                                        <button type="button" class="edit-grade-btn"
                                                data-grade='<?= htmlspecialchars(json_encode($grade, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                                data-student='<?= htmlspecialchars(json_encode($student, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_grade">
                                            <input type="hidden" name="grade_id" value="<?= $grade['id'] ?>">
                                            <input type="hidden" name="course_id" value="<?= $viewingCourse['id'] ?>">
                                            <button type="submit" class="delete-action">Delete</button>
                                        </form>
                                    <?php else : ?>
                                        <button type="button" class="add-grade-btn"
                                                data-student-id="<?= htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-student-name="<?= htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8') ?>">Add Grade</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Add/Edit Grade Modal (for teachers/admins) -->
<?php if ($isTeacherOrAdmin) : ?>
    <div id="gradeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="closeGradeModal()">&times;</span>
            <h2 id="gradeModalTitle">Add Grade</h2>
            <form id="gradeForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" id="gradeAction" value="add_grade">
                <input type="hidden" name="grade_id" id="gradeId" value="">
                <input type="hidden" name="student_id" id="gradeStudentId" value="">
                <input type="hidden" name="course_id" value="<?= htmlspecialchars($viewingCourse['id']) ?>">

                <div class="form-group">
                    <label for="gradeStudentSelect">Student</label>
                    <select id="gradeStudentSelect" required disabled></select>
                    <input type="hidden" id="gradeStudentIdHidden" name="student_id" value="">
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
<?php if ($isTeacherOrAdmin) : ?>
const enrolledStudents = <?= json_encode($enrolledStudents) ?>;
const courseId = '<?= htmlspecialchars($viewingCourse['id'], ENT_QUOTES, 'UTF-8') ?>';

function openAddGradeModalForStudent(studentId, studentName) {
    openAddGradeModal();
    const studentSelect = document.getElementById('gradeStudentSelect');
    const studentIdHidden = document.getElementById('gradeStudentIdHidden');
    const gradeStudentId = document.getElementById('gradeStudentId');

    // Populate student dropdown
    studentSelect.innerHTML = '<option value="">Select Student</option>';
    enrolledStudents.forEach(student => {
        const option = document.createElement('option');
        option.value = student.id;
        option.textContent = student.name;
        option.selected = student.id === studentId;
        studentSelect.appendChild(option);
    });

    studentSelect.value = studentId;
    studentSelect.disabled = true;
    studentIdHidden.value = studentId;
    gradeStudentId.value = studentId;
}

function openAddGradeModal() {
    const modal = document.getElementById('gradeModal');
    const studentSelect = document.getElementById('gradeStudentSelect');
    const studentIdHidden = document.getElementById('gradeStudentIdHidden');
    const gradeStudentId = document.getElementById('gradeStudentId');

    // Populate student dropdown
    studentSelect.innerHTML = '<option value="">Select Student</option>';
    enrolledStudents.forEach(student => {
        const option = document.createElement('option');
        option.value = student.id;
        option.textContent = student.name;
        studentSelect.appendChild(option);
    });

    // Reset form
    document.getElementById('gradeModalTitle').textContent = 'Add Grade';
    document.getElementById('gradeAction').value = 'add_grade';
    document.getElementById('gradeId').value = '';
    studentSelect.value = '';
    studentSelect.disabled = false;
    studentIdHidden.value = '';
    gradeStudentId.value = '';
    document.getElementById('gradeOriginal').value = '';
    document.getElementById('gradeNotes').value = '';

    // Sync hidden field when student changes
    studentSelect.onchange = function() {
        studentIdHidden.value = this.value;
        gradeStudentId.value = this.value;
    };

    modal.style.display = 'block';
}

function openEditGradeModal(grade, student) {
    const modal = document.getElementById('gradeModal');
    const studentSelect = document.getElementById('gradeStudentSelect');
    const studentIdHidden = document.getElementById('gradeStudentIdHidden');
    const gradeStudentId = document.getElementById('gradeStudentId');

    // Populate student dropdown
    studentSelect.innerHTML = '<option value="">Select Student</option>';
    enrolledStudents.forEach(s => {
        const option = document.createElement('option');
        option.value = s.id;
        option.textContent = s.name;
        option.selected = s.id === student.id;
        studentSelect.appendChild(option);
    });

    // Populate form
    document.getElementById('gradeModalTitle').textContent = 'Edit Grade';
    document.getElementById('gradeAction').value = 'edit_grade';
    document.getElementById('gradeId').value = grade.id;
    studentSelect.value = student.id;
    studentSelect.disabled = true;
    studentIdHidden.value = student.id;
    gradeStudentId.value = student.id;
    document.getElementById('gradeOriginal').value = grade.original_grade ?? '';
    document.getElementById('gradeNotes').value = grade.notes || '';

    modal.style.display = 'block';
}

function closeGradeModal() {
    const studentSelect = document.getElementById('gradeStudentSelect');
    if (studentSelect) {
        studentSelect.disabled = false;
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
let currentSort = { column: null, order: 'natural' };

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

            if (column === 'student_name') {
                aVal = a.dataset.studentName || '';
                bVal = b.dataset.studentName || '';
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
                if (aVal === -1 && bVal === -1) return 0;
                if (aVal === -1) return 1;
                if (bVal === -1) return -1;
                return currentSort.order === 'asc' ? aVal - bVal : bVal - aVal;
            } else if (column === 'final_grade') {
                aVal = parseFloat(a.dataset.finalGrade || -1);
                bVal = parseFloat(b.dataset.finalGrade || -1);
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
        // Get menu dimensions
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';
        const menuHeight = menu.offsetHeight;
        menu.style.display = '';
        menu.style.visibility = '';

        // Get dropdown position
        const rect = dropdown.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        const menuHeightWithMargin = menuHeight + 10;

        // Check if we're in the last row
        const tableBody = dropdown.closest('tbody');
        const tableRect = tableBody ? tableBody.getBoundingClientRect() : null;
        const isLastRow = tableRect ? (rect.bottom > tableRect.bottom - 50) : false;

        // Position menu above if needed
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

// Attach event listeners for edit and add grade buttons
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Edit grade buttons
        const editGradeButtons = document.querySelectorAll('.edit-grade-btn');
        if (editGradeButtons && editGradeButtons.length > 0) {
            editGradeButtons.forEach(button => {
                if (!button) return;
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const gradeData = this.getAttribute('data-grade');
                    const studentData = this.getAttribute('data-student');
                    if (gradeData && studentData) {
                        try {
                            const grade = JSON.parse(gradeData);
                            const student = JSON.parse(studentData);
                            openEditGradeModal(grade, student);
                            // Close the actions menu
                            const menuBtn = this.closest('.actions-dropdown')?.querySelector('.actions-menu-btn');
                            if (menuBtn) {
                                toggleActionsMenu(menuBtn, e);
                            }
                        } catch (err) {
                            console.error('Error parsing grade/student data:', err);
                            alert('Error loading grade data. Please refresh the page.');
                        }
                    }
                });
            });
        }

        // Add grade buttons
        const addGradeButtons = document.querySelectorAll('.add-grade-btn');
        if (addGradeButtons && addGradeButtons.length > 0) {
            addGradeButtons.forEach(button => {
                if (!button) return;
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const studentId = this.getAttribute('data-student-id');
                    const studentName = this.getAttribute('data-student-name');
                    if (studentId && studentName) {
                        openAddGradeModalForStudent(studentId, studentName);
                        // Close the actions menu
                        const menuBtn = this.closest('.actions-dropdown')?.querySelector('.actions-menu-btn');
                        if (menuBtn) {
                            toggleActionsMenu(menuBtn, e);
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

