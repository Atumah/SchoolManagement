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
        header('Location: /notes/notes.php');
        exit;
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $teacherId = $currentUser['id'];
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $studentId = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
            $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
            $tags = $_POST['tags'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');

            if (empty($title) || empty($content)) {
                setFlashMessage('error', 'Title and content are required');
            } else {
                addNote([
                    'title' => $title,
                    'content' => $content,
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'tags' => $tags,
                    'date' => $date,
                    'teacher_id' => $teacherId
                ]);
                setFlashMessage('success', 'Note added successfully');
                header('Location: /notes/notes.php');
                exit;
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $studentId = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
            $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
            $tags = $_POST['tags'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');

            if (empty($title) || empty($content)) {
                setFlashMessage('error', 'Title and content are required');
            } else {
                updateNote($id, [
                    'title' => $title,
                    'content' => $content,
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'tags' => $tags,
                    'date' => $date
                ]);
                setFlashMessage('success', 'Note updated successfully');
                header('Location: /notes/notes.php');
                exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            deleteNote($id);
            setFlashMessage('success', 'Note deleted successfully');
            header('Location: /notes/notes.php');
            exit;
        }
    }
}

// Get notes
if ($currentUser['role'] === 'Teacher') {
    $notes = getNotesByTeacher($currentUser['id']);
} else {
    $notes = getAllNotes();
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
$filterTag = $_GET['filter_tag'] ?? '';
$search = $_GET['search'] ?? '';

if ($filterStudent) {
    $notes = array_filter($notes, fn($n) => $n['student_id'] == $filterStudent);
}
if ($filterCourse) {
    $notes = array_filter($notes, fn($n) => $n['course_id'] == $filterCourse);
}
if ($filterTag) {
    $notes = array_filter($notes, function ($n) use ($filterTag) {
        return stripos($n['tags'], $filterTag) !== false;
    });
}
if ($search) {
    $notes = array_filter($notes, function ($n) use ($search) {
        return stripos($n['title'], $search) !== false ||
               stripos($n['content'], $search) !== false ||
               stripos($n['tags'], $search) !== false;
    });
}

$editingId = $_GET['edit'] ?? null;
$editingNote = $editingId ? getNoteById((int)$editingId) : null;
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - School Management</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Notes</h1>
            <button class="form-toggle-btn" onclick="openAddNoteModal()">+ Add Note</button>
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
                        <option value="unlinked" <?= $filterStudent === 'unlinked' ? 'selected' : '' ?>>Unlinked Notes</option>
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
                        <option value="unlinked" <?= $filterCourse === 'unlinked' ? 'selected' : '' ?>>Unlinked Notes</option>
                        <?php foreach ($teacherCourses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= $filterCourse == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_tag">Filter by Tag</label>
                    <input type="text" id="filter_tag" name="filter_tag" value="<?= htmlspecialchars($filterTag) ?>" placeholder="Enter tag">
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search in title, content, or tags">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="/notes/notes.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        
        <!-- Notes List -->
        <?php if (empty($notes)) : ?>
            <div class="empty-state">
                <p>No notes found.</p>
            </div>
        <?php else : ?>
            <div style="display: grid; gap: 1.5rem;">
                <?php foreach ($notes as $note) : ?>
                    <?php
                    $student = $note['student_id'] ? getUserById($note['student_id']) : null;
                    $course = $note['course_id'] ? getCourseById($note['course_id']) : null;
                    $tags = array_filter(array_map('trim', explode(',', $note['tags'])));
                    ?>
                    <div class="note-card">
                        <div class="note-card-header">
                            <h2 class="note-card-title"><?= htmlspecialchars($note['title']) ?></h2>
                            <div class="actions">
                                <button type="button" class="btn btn-secondary btn-small edit-note-btn" data-note='<?= htmlspecialchars(json_encode($note), ENT_QUOTES, 'UTF-8') ?>'>Edit</button>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this note?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $note['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="note-card-content">
                            <?= nl2br(htmlspecialchars(substr($note['content'], 0, 200))) ?><?= strlen($note['content']) > 200 ? '...' : '' ?>
                        </div>
                        <div class="note-card-meta">
                            <?php if ($student) : ?>
                                <span><strong>Student:</strong> <?= htmlspecialchars($student['name']) ?></span>
                            <?php endif; ?>
                            <?php if ($course) : ?>
                                <span><strong>Course:</strong> <?= htmlspecialchars($course['name']) ?></span>
                            <?php endif; ?>
                            <span><strong>Date:</strong> <?= htmlspecialchars($note['date']) ?></span>
                            <?php if (!empty($tags)) : ?>
                                <div class="note-card-tags">
                                    <?php foreach ($tags as $tag) : ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Add Note Modal -->
    <div class="modal-overlay" id="addNoteModal" onclick="closeAddNoteModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Add Note</h2>
                <button type="button" class="modal-close-btn" onclick="closeAddNoteModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="addNoteForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="add_title">Title</label>
                        <input type="text" id="add_title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="add_content">Content</label>
                        <textarea id="add_content" name="content" required style="min-height: 150px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="add_student_id">Student (Optional)</label>
                        <select id="add_student_id" name="student_id">
                            <option value="">None</option>
                            <?php foreach ($allStudents as $student) : ?>
                                <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_course_id">Course (Optional)</label>
                        <select id="add_course_id" name="course_id">
                            <option value="">None</option>
                            <?php foreach ($teacherCourses as $course) : ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_tags">Tags (comma-separated)</label>
                        <input type="text" id="add_tags" name="tags" placeholder="e.g., meeting, important, parent">
                    </div>
                    <div class="form-group">
                        <label for="add_date">Date</label>
                        <input type="date" id="add_date" name="date" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddNoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Note Modal -->
    <div class="modal-overlay" id="editNoteModal" onclick="closeEditNoteModalOnOverlay(event)">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h2>Edit Note</h2>
                <button type="button" class="modal-close-btn" onclick="closeEditNoteModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="" id="editNoteForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_note_id">
                    <div class="form-group">
                        <label for="edit_title">Title</label>
                        <input type="text" id="edit_title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_content">Content</label>
                        <textarea id="edit_content" name="content" required style="min-height: 150px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_student_id">Student (Optional)</label>
                        <select id="edit_student_id" name="student_id">
                            <option value="">None</option>
                            <?php foreach ($allStudents as $student) : ?>
                                <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_course_id">Course (Optional)</label>
                        <select id="edit_course_id" name="course_id">
                            <option value="">None</option>
                            <?php foreach ($teacherCourses as $course) : ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_tags">Tags (comma-separated)</label>
                        <input type="text" id="edit_tags" name="tags" placeholder="e.g., meeting, important, parent">
                    </div>
                    <div class="form-group">
                        <label for="edit_date">Date</label>
                        <input type="date" id="edit_date" name="date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditNoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Note</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Add Note Modal Functions
    function openAddNoteModal() {
        const modal = document.getElementById('addNoteModal');
        if (!modal) return;
        
        // Reset form fields
        document.getElementById('add_title').value = '';
        document.getElementById('add_content').value = '';
        document.getElementById('add_student_id').value = '';
        document.getElementById('add_course_id').value = '';
        document.getElementById('add_tags').value = '';
        document.getElementById('add_date').value = '<?= date('Y-m-d') ?>';
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeAddNoteModal() {
        const modal = document.getElementById('addNoteModal');
        if (!modal) return;
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function closeAddNoteModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeAddNoteModal();
        }
    }
    
    // Edit Note Modal Functions
    function openEditNoteModal(note) {
        const modal = document.getElementById('editNoteModal');
        if (!modal) return;
        
        // Populate form fields
        document.getElementById('edit_note_id').value = note.id;
        document.getElementById('edit_title').value = note.title || '';
        document.getElementById('edit_content').value = note.content || '';
        document.getElementById('edit_student_id').value = note.student_id || '';
        document.getElementById('edit_course_id').value = note.course_id || '';
        document.getElementById('edit_tags').value = note.tags || '';
        document.getElementById('edit_date').value = note.date || '<?= date('Y-m-d') ?>';
        
        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeEditNoteModal() {
        const modal = document.getElementById('editNoteModal');
        if (!modal) return;
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function closeEditNoteModalOnOverlay(event) {
        if (event.target === event.currentTarget) {
            closeEditNoteModal();
        }
    }
    
    // Attach event listeners to all edit buttons
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-note-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const noteData = this.getAttribute('data-note');
                if (noteData) {
                    try {
                        const note = JSON.parse(noteData);
                        openEditNoteModal(note);
                    } catch (e) {
                        console.error('Error parsing note data:', e);
                    }
                }
            });
        });
    });
    
    // Close any modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddNoteModal();
            closeEditNoteModal();
        }
    });
    
    <?php if ($editingNote) : ?>
    // Auto-open edit modal if editing from URL
    document.addEventListener('DOMContentLoaded', function() {
        const noteData = <?= json_encode($editingNote, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        openEditNoteModal(noteData);
    });
    <?php endif; ?>
    </script>
</body>
</html>

