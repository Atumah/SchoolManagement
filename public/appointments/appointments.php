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
        header('Location: /appointments/appointments.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add appointment logic here
            setFlashMessage('success', 'Appointment added successfully');
            header('Location: /appointments/appointments.php');
            exit;
        } elseif ($_POST['action'] === 'edit') {
            // Edit appointment logic here
            setFlashMessage('success', 'Appointment updated successfully');
            header('Location: /appointments/appointments.php');
            exit;
        } elseif ($_POST['action'] === 'delete') {
            // Delete appointment logic here
            setFlashMessage('success', 'Appointment deleted successfully');
            header('Location: /appointments/appointments.php');
            exit;
        } elseif ($_POST['action'] === 'accept') {
            // Accept appointment logic here
            setFlashMessage('success', 'Appointment accepted');
            header('Location: /appointments/appointments.php');
            exit;
        } elseif ($_POST['action'] === 'decline') {
            // Decline appointment logic here
            setFlashMessage('success', 'Appointment declined');
            header('Location: /appointments/appointments.php');
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
    <title>Appointments - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Appointments</h1>
        </div>
        
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <p>Appointments page - Coming soon</p>
        </div>
    </main>
</body>
</html>

