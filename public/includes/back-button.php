<?php

declare(strict_types=1);

/**
 * Back Button Component
 * 
 * Shows a back button only on pages that are NOT directly accessible from navigation
 * (i.e., pages opened as a result of actions like edit forms, detail views, etc.)
 */

// Pages accessible from navigation bar (no back button needed)
$navBarPages = [
    '/index.php',
    '/login.php',
    '/logout.php',
    '/courses/course.php',
    '/courses/join.php',
    '/grades/view.php',
    '/progress/progress.php',
    '/notes/notes.php',
    '/users/users.php',
    '/settings/settings.php',
    '/errors/404.php',
    '/errors/403.php',
    '/errors/500.php'
];

$currentPage = $_SERVER['PHP_SELF'] ?? '';
$isNavBarPage = in_array($currentPage, $navBarPages, true);

// Check if page was opened with an action parameter (like ?edit=1, ?id=5, etc.)
// This indicates the page was opened from another page, not directly from nav
$hasEditParam = isset($_GET['edit']) && !empty($_GET['edit']);
$hasIdParam = isset($_GET['id']) && !empty($_GET['id']);
$hasActionParam = isset($_GET['action']) && !empty($_GET['action']);

// Show back button ONLY if:
// 1. Not a navigation bar page (like login-2fa.php)
// 2. OR is a nav bar page but opened with action parameters (like ?edit=1)
$showBackButton = !$isNavBarPage || ($isNavBarPage && ($hasEditParam || $hasIdParam || $hasActionParam));

if ($showBackButton):
    // Determine fallback URL based on context
    $fallbackUrl = '/index.php';
    
    // For 2FA login, go back to regular login
    if (strpos($currentPage, 'login-2fa.php') !== false) {
        $fallbackUrl = '/login.php';
    }
    // For nav bar pages opened with action parameters (like ?edit=1), go back to base page
    elseif ($isNavBarPage && ($hasEditParam || $hasIdParam || $hasActionParam)) {
        // Remove query parameters to go back to the base page
        $fallbackUrl = $currentPage;
    }
    // For other non-nav pages, try to determine parent page from current page path
    else {
        if (strpos($currentPage, 'courses') !== false) {
            $fallbackUrl = '/courses/course.php';
        } elseif (strpos($currentPage, 'grades') !== false) {
            $fallbackUrl = '/grades/view.php';
        } elseif (strpos($currentPage, 'progress') !== false) {
            $fallbackUrl = '/progress/progress.php';
        } elseif (strpos($currentPage, 'notes') !== false) {
            $fallbackUrl = '/notes/notes.php';
        } elseif (strpos($currentPage, 'users') !== false) {
            $fallbackUrl = '/users/users.php';
        } elseif (strpos($currentPage, 'settings') !== false) {
            $fallbackUrl = '/settings/settings.php';
        }
    }
?>
    <div class="back-button-container">
        <button type="button" class="back-button" onclick="goBack(); return false;" title="Go back">
            <span class="back-button-icon">←</span>
            <span class="back-button-text">Back</span>
        </button>
    </div>
    <script>
    // PHP-driven back button with fallback
    function goBack() {
        // Try browser history first (most reliable)
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // Fallback: redirect to the determined fallback URL
            window.location.href = '<?= htmlspecialchars($fallbackUrl) ?>';
        }
    }
    </script>
<?php endif; ?>

<style>
.back-button-container {
    margin-bottom: var(--spacing-md);
    padding: 0;
    position: relative;
    z-index: 100;
}

/* Special positioning for auth pages */
.auth-container ~ .back-button-container,
body > .back-button-container {
    position: absolute;
    top: var(--spacing-md);
    left: var(--spacing-md);
    z-index: 1000;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-normal);
    backdrop-filter: blur(10px);
}

.back-button:hover {
    background: rgba(30, 41, 59, 0.95);
    border-color: var(--primary-light);
    color: var(--text-primary);
    transform: translateX(-4px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.back-button:active {
    transform: translateX(-2px);
}

.back-button-icon {
    font-size: 1.2rem;
    line-height: 1;
    transition: transform var(--transition-fast);
}

.back-button:hover .back-button-icon {
    transform: translateX(-2px);
}

.back-button-text {
    line-height: 1;
}

@media (max-width: 768px) {
    .back-button {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
    
    .back-button-text {
        display: none; /* Hide text on mobile, show only icon */
    }
    
    .back-button-icon {
        font-size: 1.4rem;
    }
}
</style>

