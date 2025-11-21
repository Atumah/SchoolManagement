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

            $courseId = $_POST['course_id'];
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

            $courseId = $_POST['course_id'];
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

// Get all courses for autocomplete
$allCourses = getAllCourses();
$userCourses = getCoursesByStudent($currentUser['id']);
$userCourseIds = array_column($userCourses, 'id');

// Handle course join by ID
$joinById = $_GET['join_id'] ?? '';
$selectedCourse = null;
if ($joinById) {
    // Try to find course by ID
    foreach ($allCourses as $course) {
        if ($course['id'] === $joinById) {
            $selectedCourse = $course;
            break;
        }
    }
}

// Filtering by name search
$csrfToken = generateCSRFToken();
$search = $_GET['search'] ?? '';
$filteredCourses = $allCourses;
if ($search && !$selectedCourse) {
    $filteredCourses = array_filter($allCourses, static function ($course) use ($search) {
        return stripos($course['name'], $search) !== false ||
               stripos($course['description'], $search) !== false;
    });
} elseif ($selectedCourse) {
    $filteredCourses = [$selectedCourse];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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

        <!-- Dual Search Interface -->
        <div class="filters">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <!-- Name Search with Autocomplete -->
                <div class="form-group">
                    <label for="search-name">Search by Course Name</label>
                    <div style="position: relative;">
                        <input type="text" id="search-name" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Type course name..." autocomplete="off"
                               oninput="filterCourseSuggestions(this.value)">
                        <div id="course-suggestions" class="autocomplete-dropdown" style="display: none;"></div>
                    </div>
                </div>

                <!-- ID Search -->
                <div class="form-group">
                    <label for="search-id">Join by Course ID</label>
                    <form method="GET" action="" style="display: flex; gap: 0.5rem;">
                        <input type="text" id="search-id" name="join_id"
                               value="<?= htmlspecialchars($joinById) ?>"
                               placeholder="Enter course ID"
                               pattern="[0-9]+"
                               style="flex: 1;">
                        <button type="submit" class="btn btn-primary">Join</button>
                    </form>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="search" id="search-hidden" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-secondary">Search</button>
                </form>
                <a href="/courses/join.php" class="btn btn-secondary">Clear</a>
            </div>
        </div>

        <!-- Courses List -->
        <?php if (empty($filteredCourses)) : ?>
            <div class="empty-state">
                <p>No courses found. <?= $joinById ? 'Course ID not found.' : 'Try a different search.' ?></p>
            </div>
        <?php else : ?>
            <div style="display: grid; gap: 1.5rem;">
                <?php foreach ($filteredCourses as $course) : ?>
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

    <style>
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-secondary, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: var(--radius-sm, 0.375rem);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 0.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .autocomplete-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color, #334155);
            transition: background-color 0.2s;
        }
        .autocomplete-item:hover {
            background-color: var(--bg-hover, rgba(59, 130, 246, 0.1));
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item strong {
            color: var(--text-primary, #f1f5f9);
        }
        .autocomplete-item small {
            color: var(--text-secondary, #94a3b8);
            display: block;
            margin-top: 0.25rem;
        }
        .autocomplete-item.highlighted {
            background-color: var(--bg-hover, rgba(59, 130, 246, 0.2));
        }
    </style>

    <script>
        // Course data for autocomplete
        const allCourses = <?= json_encode(array_map(static function ($course) {
            return [
                'id' => $course['id'],
                'name' => $course['name'],
                'description' => $course['description'] ?? '',
                'teacher' => getUserById($course['teacher_id'])['name'] ?? 'Unknown'
            ];
        }, $allCourses)) ?>;

        function filterCourseSuggestions(query) {
            const suggestionsDiv = document.getElementById('course-suggestions');
            const searchInput = document.getElementById('search-name');
            const searchHidden = document.getElementById('search-hidden');

            if (!query || query.length < 1) {
                suggestionsDiv.style.display = 'none';
                searchHidden.value = '';
                return;
            }

            const queryLower = query.toLowerCase();
            const matches = allCourses.filter(course =>
                course.name.toLowerCase().includes(queryLower) ||
                course.description.toLowerCase().includes(queryLower)
            ).slice(0, 10); // Limit to 10 suggestions

            if (matches.length === 0) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            suggestionsDiv.innerHTML = matches.map(course => `
                <div class="autocomplete-item" onclick="selectCourse('${escapeHtml(course.name)}', '${escapeHtml(course.id)}')">
                    <strong>${escapeHtml(course.name)}</strong>
                    <small>${escapeHtml(course.description || 'No description')} - Teacher: ${escapeHtml(course.teacher)}</small>
                </div>
            `).join('');
            suggestionsDiv.style.display = 'block';
        }

        function selectCourse(name, id) {
            const searchInput = document.getElementById('search-name');
            const searchHidden = document.getElementById('search-hidden');
            const suggestionsDiv = document.getElementById('course-suggestions');

            searchInput.value = name;
            searchHidden.value = name;
            suggestionsDiv.style.display = 'none';

            // Store the selected course ID for potential direct join
            searchInput.dataset.selectedCourseId = id;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            const suggestionsDiv = document.getElementById('course-suggestions');
            const searchInput = document.getElementById('search-name');
            if (!suggestionsDiv.contains(e.target) && e.target !== searchInput) {
                suggestionsDiv.style.display = 'none';
            }
        });

        // Handle keyboard navigation
        document.getElementById('search-name').addEventListener('keydown', function(e) {
            const suggestionsDiv = document.getElementById('course-suggestions');
            const items = suggestionsDiv.querySelectorAll('.autocomplete-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const current = suggestionsDiv.querySelector('.autocomplete-item.highlighted');
                if (current) {
                    current.classList.remove('highlighted');
                    const next = current.nextElementSibling;
                    if (next) {
                        next.classList.add('highlighted');
                        next.scrollIntoView({ block: 'nearest' });
                    }
                } else if (items.length > 0) {
                    items[0].classList.add('highlighted');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const current = suggestionsDiv.querySelector('.autocomplete-item.highlighted');
                if (current) {
                    current.classList.remove('highlighted');
                    const prev = current.previousElementSibling;
                    if (prev) {
                        prev.classList.add('highlighted');
                        prev.scrollIntoView({ block: 'nearest' });
                    }
                }
            } else if (e.key === 'Enter') {
                const highlighted = suggestionsDiv.querySelector('.autocomplete-item.highlighted');
                if (highlighted) {
                    e.preventDefault();
                    highlighted.click();
                }
            } else if (e.key === 'Escape') {
                suggestionsDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>

