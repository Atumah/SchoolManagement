<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();

// Get course ID from query parameter
$courseId = $_GET['course_id'] ?? null;

if (!$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'Course ID is required']);
    exit;
}

try {
    // Get the course
    $course = getCourseById($courseId);

    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
        exit;
    }

    // Check permissions
    if (!hasRole('Admin') && $course['teacher_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to view this course']);
        exit;
    }

    // Get all students
    $allStudents = array_filter(getAllUsers(), static fn ($u) => $u['role'] === 'Student');

    // Get enrolled student IDs
    $enrolledStudentIds = $course['students'] ?? [];

    // Filter out enrolled students
    $availableStudents = array_filter($allStudents, static fn ($s) => !in_array($s['id'], $enrolledStudentIds, true));

    // Re-index array for proper JSON encoding
    $availableStudents = array_values($availableStudents);

    echo json_encode(['students' => $availableStudents]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch available students: ' . $e->getMessage()]);
}
