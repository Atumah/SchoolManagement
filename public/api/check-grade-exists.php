<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasAnyRole(['Admin', 'Teacher', 'Principal', 'Web Designer'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$studentId = $_GET['student_id'] ?? null;
$courseId = $_GET['course_id'] ?? null;

if (!$studentId || !$courseId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $exists = gradeExistsForStudentAndCourse($studentId, $courseId);
    echo json_encode([
        'exists' => $exists,
        'message' => $exists ? 'A grade already exists for this student in this course.' : ''
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to check grade: ' . $e->getMessage()]);
}
