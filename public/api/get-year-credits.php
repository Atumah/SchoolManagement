<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';

requireAuth();

if (!hasAnyRole(['Teacher', 'Admin', 'Principal', 'Web Designer'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : 1;

if ($year < 1 || $year > 4) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year']);
    exit;
}

try {
    $totalCredits = getTotalCreditsByYear($year);
    echo json_encode([
        'year' => $year,
        'total' => $totalCredits,
        'remaining' => 60 - $totalCredits
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
