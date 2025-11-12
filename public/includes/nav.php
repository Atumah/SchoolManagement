<?php

declare(strict_types=1);

if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/auth.php';
}

$currentUser = getCurrentUser();
$currentPage = $_SERVER['PHP_SELF'] ?? '';
?>
<nav class="navbar">
    <div class="navbar-container">
        <a href="/index.php" class="navbar-brand">
            <img src="/assets/logo-placeholder.svg" alt="Morning Star" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
            <span style="display: none;">⭐</span>
            <span>Morning Star</span>
        </a>
        
        <?php if ($currentUser): ?>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
            <ul class="navbar-menu" id="navbar-menu">
                <li><a href="/index.php" class="<?= strpos($currentPage, 'index.php') !== false ? 'active' : '' ?>">Home</a></li>
                
                <?php if ($currentUser['role'] === 'Teacher'): ?>
                    <!-- Content -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'announcements.php') !== false || strpos($currentPage, 'events.php') !== false) ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/announcements/announcements.php" class="<?= strpos($currentPage, 'announcements.php') !== false ? 'active' : '' ?>">Announcements</a></li>
                            <li><a href="/events/events.php" class="<?= strpos($currentPage, 'events.php') !== false ? 'active' : '' ?>">Events</a></li>
                        </ul>
                    </li>
                    
                    <!-- Academic -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'course.php') !== false || strpos($currentPage, 'attendance.php') !== false || strpos($currentPage, 'view.php') !== false || strpos($currentPage, 'progress.php') !== false) ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/courses/course.php" class="<?= strpos($currentPage, 'course.php') !== false ? 'active' : '' ?>">Courses</a></li>
                            <li><a href="/attendance/attendance.php" class="<?= strpos($currentPage, 'attendance.php') !== false ? 'active' : '' ?>">Attendance</a></li>
                            <li><a href="/grades/view.php" class="<?= strpos($currentPage, 'view.php') !== false ? 'active' : '' ?>">Grades</a></li>
                            <li><a href="/progress/progress.php" class="<?= strpos($currentPage, 'progress.php') !== false ? 'active' : '' ?>">Progress</a></li>
                        </ul>
                    </li>
                    
                    <!-- Personal -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'notes.php') !== false || strpos($currentPage, 'appointments.php') !== false || strpos($currentPage, 'overview.php') !== false) ? 'active' : '' ?>">
                            Personal <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/notes/notes.php" class="<?= strpos($currentPage, 'notes.php') !== false ? 'active' : '' ?>">Notes</a></li>
                            <li><a href="/appointments/appointments.php" class="<?= strpos($currentPage, 'appointments.php') !== false ? 'active' : '' ?>">Appointments</a></li>
                            <li><a href="/overview/overview.php" class="<?= strpos($currentPage, 'overview.php') !== false ? 'active' : '' ?>">Overview</a></li>
                        </ul>
                    </li>
                    
                <?php elseif ($currentUser['role'] === 'Admin' || $currentUser['role'] === 'Principal'): ?>
                    <!-- Content -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'announcements.php') !== false || strpos($currentPage, 'events.php') !== false) ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/announcements/announcements.php" class="<?= strpos($currentPage, 'announcements.php') !== false ? 'active' : '' ?>">Announcements</a></li>
                            <li><a href="/events/events.php" class="<?= strpos($currentPage, 'events.php') !== false ? 'active' : '' ?>">Events</a></li>
                        </ul>
                    </li>
                    
                    <!-- Management -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= strpos($currentPage, 'users.php') !== false ? 'active' : '' ?>">
                            Management <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/users/users.php" class="<?= strpos($currentPage, 'users.php') !== false ? 'active' : '' ?>">Users</a></li>
                        </ul>
                    </li>
                    
                    <!-- Academic -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'course.php') !== false || strpos($currentPage, 'view.php') !== false || strpos($currentPage, 'progress.php') !== false || strpos($currentPage, 'notes.php') !== false) ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                    <li><a href="/courses/course.php" class="<?= strpos($currentPage, 'course.php') !== false ? 'active' : '' ?>">Courses</a></li>
                    <li><a href="/grades/view.php" class="<?= strpos($currentPage, 'view.php') !== false ? 'active' : '' ?>">Grades</a></li>
                    <li><a href="/progress/progress.php" class="<?= strpos($currentPage, 'progress.php') !== false ? 'active' : '' ?>">Progress</a></li>
                    <li><a href="/notes/notes.php" class="<?= strpos($currentPage, 'notes.php') !== false ? 'active' : '' ?>">Notes</a></li>
                        </ul>
                    </li>
                    
                    <!-- Personal -->
                    <li><a href="/appointments/appointments.php" class="<?= strpos($currentPage, 'appointments.php') !== false ? 'active' : '' ?>">Appointments</a></li>
                    
                <?php elseif ($currentUser['role'] === 'Web Designer'): ?>
                    <!-- Content -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'announcements.php') !== false || strpos($currentPage, 'events.php') !== false) ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/announcements/announcements.php" class="<?= strpos($currentPage, 'announcements.php') !== false ? 'active' : '' ?>">Announcements</a></li>
                            <li><a href="/events/events.php" class="<?= strpos($currentPage, 'events.php') !== false ? 'active' : '' ?>">Events</a></li>
                        </ul>
                    </li>
                    
                    <!-- Management -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= strpos($currentPage, 'users.php') !== false ? 'active' : '' ?>">
                            Management <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/users/users.php" class="<?= strpos($currentPage, 'users.php') !== false ? 'active' : '' ?>">Users</a></li>
                        </ul>
                    </li>
                    
                    <!-- Academic -->
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= (strpos($currentPage, 'course.php') !== false || strpos($currentPage, 'view.php') !== false || strpos($currentPage, 'progress.php') !== false || strpos($currentPage, 'notes.php') !== false) ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/courses/course.php" class="<?= strpos($currentPage, 'course.php') !== false ? 'active' : '' ?>">Courses</a></li>
                            <li><a href="/grades/view.php" class="<?= strpos($currentPage, 'view.php') !== false ? 'active' : '' ?>">Grades</a></li>
                            <li><a href="/progress/progress.php" class="<?= strpos($currentPage, 'progress.php') !== false ? 'active' : '' ?>">Progress</a></li>
                            <li><a href="/notes/notes.php" class="<?= strpos($currentPage, 'notes.php') !== false ? 'active' : '' ?>">Notes</a></li>
                        </ul>
                    </li>
                    
                    <!-- Personal -->
                    <li><a href="/appointments/appointments.php" class="<?= strpos($currentPage, 'appointments.php') !== false ? 'active' : '' ?>">Appointments</a></li>
                <?php endif; ?>
                
                <li class="navbar-settings-dropdown">
                    <a href="/settings/settings.php" class="settings-icon <?= strpos($currentPage, 'settings.php') !== false ? 'active' : '' ?>" title="Settings" onclick="event.preventDefault(); toggleSettingsDropdown();">
                        ⚙️
                    </a>
                    <div class="settings-dropdown-menu" id="settings-dropdown">
                        <a href="/settings/settings.php">Settings</a>
                        <a href="/logout.php" class="logout-link">Logout</a>
                    </div>
                </li>
            </ul>
        <?php endif; ?>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('navbar-menu');
    menu.classList.toggle('active');
}

// Settings dropdown toggle
function toggleSettingsDropdown() {
    const dropdownMenu = document.getElementById('settings-dropdown');
    if (dropdownMenu) {
        dropdownMenu.classList.toggle('show');
    }
}

// Navbar dropdown toggle
document.addEventListener('DOMContentLoaded', function() {
    // Handle navbar dropdowns
    document.querySelectorAll('.navbar-dropdown .dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.closest('.navbar-dropdown');
            const isActive = dropdown.classList.contains('active');
            
            // Close all other dropdowns
            document.querySelectorAll('.navbar-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('active', !isActive);
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.navbar-dropdown')) {
            document.querySelectorAll('.navbar-dropdown').forEach(d => {
                d.classList.remove('active');
            });
        }
        
        // Close settings dropdown when clicking outside
        const settingsDropdown = document.querySelector('.navbar-settings-dropdown');
        if (settingsDropdown && !settingsDropdown.contains(e.target)) {
            const dropdownMenu = document.getElementById('settings-dropdown');
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
        }
    });
});
</script>
