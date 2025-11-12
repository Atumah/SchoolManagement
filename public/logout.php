<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Logout and destroy session (no flash messages)
logout();

// Redirect to login page
header('Location: /login.php');
exit;
