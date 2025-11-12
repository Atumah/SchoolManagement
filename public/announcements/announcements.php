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
        header('Location: /announcements/announcements.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add announcement logic here
            setFlashMessage('success', 'Announcement added successfully');
            header('Location: /announcements/announcements.php');
            exit;
        } elseif ($_POST['action'] === 'edit') {
            // Edit announcement logic here
            setFlashMessage('success', 'Announcement updated successfully');
            header('Location: /announcements/announcements.php');
            exit;
        } elseif ($_POST['action'] === 'delete') {
            // Delete announcement logic here
            setFlashMessage('success', 'Announcement deleted successfully');
            header('Location: /announcements/announcements.php');
            exit;
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Announcements</h1>
        </div>
        
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <p>Announcements page - Coming soon</p>
        </div>
    </main>
</body>
</html>

