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
        
        <?php if ($currentUser) : ?>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
            <ul class="navbar-menu" id="navbar-menu">
                <?php
                $isIndexPage = strpos($currentPage, 'index.php') !== false;
                ?>
                <li><a href="/index.php" class="<?= $isIndexPage ? 'active' : '' ?>">Home</a></li>

                <?php if ($currentUser['role'] === 'Teacher') : ?>
                    <!-- Content -->
                    <?php
                    $isContentPage = strpos($currentPage, 'announcements.php') !== false
                        || strpos($currentPage, 'events.php') !== false;
                    $isAnnouncementsPage = strpos($currentPage, 'announcements.php') !== false;
                    $isEventsPage = strpos($currentPage, 'events.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isContentPage ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/announcements/announcements.php"
                                   class="<?= $isAnnouncementsPage ? 'active' : '' ?>">Announcements</a>
                            </li>
                            <li>
                                <a href="/events/events.php"
                                   class="<?= $isEventsPage ? 'active' : '' ?>">Events</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Academic -->
                    <?php
                    $isAcademicPage = strpos($currentPage, 'course.php') !== false
                        || strpos($currentPage, 'attendance.php') !== false
                        || strpos($currentPage, 'view.php') !== false
                        || strpos($currentPage, 'progress.php') !== false;
                    $isCoursePage = strpos($currentPage, 'course.php') !== false;
                    $isAttendancePage = strpos($currentPage, 'attendance.php') !== false;
                    $isGradesPage = strpos($currentPage, 'view.php') !== false;
                    $isProgressPage = strpos($currentPage, 'progress.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isAcademicPage ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/courses/course.php"
                                   class="<?= $isCoursePage ? 'active' : '' ?>">Courses</a>
                            </li>
                            <li>
                                <a href="/attendance/attendance.php"
                                   class="<?= $isAttendancePage ? 'active' : '' ?>">Attendance</a>
                            </li>
                            <li>
                                <a href="/grades/view.php"
                                   class="<?= $isGradesPage ? 'active' : '' ?>">Grades</a>
                            </li>
                            <li>
                                <a href="/progress/progress.php"
                                   class="<?= $isProgressPage ? 'active' : '' ?>">Progress</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Personal -->
                    <?php
                    $isPersonalPage = strpos($currentPage, 'notes.php') !== false
                        || strpos($currentPage, 'appointments.php') !== false
                        || strpos($currentPage, 'overview.php') !== false;
                    $isNotesPage = strpos($currentPage, 'notes.php') !== false;
                    $isAppointmentsPage = strpos($currentPage, 'appointments.php') !== false;
                    $isOverviewPage = strpos($currentPage, 'overview.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isPersonalPage ? 'active' : '' ?>">
                            Personal <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/notes/notes.php"
                                   class="<?= $isNotesPage ? 'active' : '' ?>">Notes</a>
                            </li>
                            <li>
                                <a href="/appointments/appointments.php"
                                   class="<?= $isAppointmentsPage ? 'active' : '' ?>">Appointments</a>
                            </li>
                            <li>
                                <a href="/overview/overview.php"
                                   class="<?= $isOverviewPage ? 'active' : '' ?>">Overview</a>
                            </li>
                        </ul>
                    </li>

                <?php elseif ($currentUser['role'] === 'Admin' || $currentUser['role'] === 'Principal') : ?>
                    <!-- Content -->
                    <?php
                    $isContentPageAdmin = strpos($currentPage, 'announcements.php') !== false
                        || strpos($currentPage, 'events.php') !== false;
                    $isAnnouncementsPageAdmin = strpos($currentPage, 'announcements.php') !== false;
                    $isEventsPageAdmin = strpos($currentPage, 'events.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isContentPageAdmin ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/announcements/announcements.php"
                                   class="<?= $isAnnouncementsPageAdmin ? 'active' : '' ?>">Announcements</a>
                            </li>
                            <li>
                                <a href="/events/events.php"
                                   class="<?= $isEventsPageAdmin ? 'active' : '' ?>">Events</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Management -->
                    <?php $isUsersPage = strpos($currentPage, 'users.php') !== false; ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isUsersPage ? 'active' : '' ?>">
                            Management <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/users/users.php"
                                   class="<?= $isUsersPage ? 'active' : '' ?>">Users</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Academic -->
                    <?php
                    $isAcademicPageAdmin = strpos($currentPage, 'course.php') !== false
                        || strpos($currentPage, 'view.php') !== false
                        || strpos($currentPage, 'progress.php') !== false
                        || strpos($currentPage, 'notes.php') !== false;
                    $isCoursePageAdmin = strpos($currentPage, 'course.php') !== false;
                    $isGradesPageAdmin = strpos($currentPage, 'view.php') !== false;
                    $isProgressPageAdmin = strpos($currentPage, 'progress.php') !== false;
                    $isNotesPageAdmin = strpos($currentPage, 'notes.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isAcademicPageAdmin ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/courses/course.php"
                                   class="<?= $isCoursePageAdmin ? 'active' : '' ?>">Courses</a>
                            </li>
                            <li>
                                <a href="/grades/view.php"
                                   class="<?= $isGradesPageAdmin ? 'active' : '' ?>">Grades</a>
                            </li>
                            <li>
                                <a href="/progress/progress.php"
                                   class="<?= $isProgressPageAdmin ? 'active' : '' ?>">Progress</a>
                            </li>
                            <li>
                                <a href="/notes/notes.php"
                                   class="<?= $isNotesPageAdmin ? 'active' : '' ?>">Notes</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Personal -->
                    <?php $isAppointmentsPageAdmin = strpos($currentPage, 'appointments.php') !== false; ?>
                    <li>
                        <a href="/appointments/appointments.php"
                           class="<?= $isAppointmentsPageAdmin ? 'active' : '' ?>">Appointments</a>
                    </li>

                <?php elseif ($currentUser['role'] === 'Web Designer') : ?>
                    <!-- Content -->
                    <?php
                    $isContentPageDesigner = strpos($currentPage, 'announcements.php') !== false
                        || strpos($currentPage, 'events.php') !== false;
                    $isAnnouncementsPageDesigner = strpos($currentPage, 'announcements.php') !== false;
                    $isEventsPageDesigner = strpos($currentPage, 'events.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isContentPageDesigner ? 'active' : '' ?>">
                            Content <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/announcements/announcements.php"
                                   class="<?= $isAnnouncementsPageDesigner ? 'active' : '' ?>">Announcements</a>
                            </li>
                            <li>
                                <a href="/events/events.php"
                                   class="<?= $isEventsPageDesigner ? 'active' : '' ?>">Events</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Management -->
                    <?php $isUsersPageDesigner = strpos($currentPage, 'users.php') !== false; ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isUsersPageDesigner ? 'active' : '' ?>">
                            Management <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/users/users.php"
                                   class="<?= $isUsersPageDesigner ? 'active' : '' ?>">Users</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Academic -->
                    <?php
                    $isAcademicPageDesigner = strpos($currentPage, 'course.php') !== false
                        || strpos($currentPage, 'view.php') !== false
                        || strpos($currentPage, 'progress.php') !== false
                        || strpos($currentPage, 'notes.php') !== false;
                    $isCoursePageDesigner = strpos($currentPage, 'course.php') !== false;
                    $isGradesPageDesigner = strpos($currentPage, 'view.php') !== false;
                    $isProgressPageDesigner = strpos($currentPage, 'progress.php') !== false;
                    $isNotesPageDesigner = strpos($currentPage, 'notes.php') !== false;
                    ?>
                    <li class="navbar-dropdown">
                        <a href="#" class="dropdown-toggle <?= $isAcademicPageDesigner ? 'active' : '' ?>">
                            Academic <span class="dropdown-arrow">▼</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="/courses/course.php"
                                   class="<?= $isCoursePageDesigner ? 'active' : '' ?>">Courses</a>
                            </li>
                            <li>
                                <a href="/grades/view.php"
                                   class="<?= $isGradesPageDesigner ? 'active' : '' ?>">Grades</a>
                            </li>
                            <li>
                                <a href="/progress/progress.php"
                                   class="<?= $isProgressPageDesigner ? 'active' : '' ?>">Progress</a>
                            </li>
                            <li>
                                <a href="/notes/notes.php"
                                   class="<?= $isNotesPageDesigner ? 'active' : '' ?>">Notes</a>
                            </li>
                        </ul>
                    </li>

                    <!-- Personal -->
                    <?php $isAppointmentsPageDesigner = strpos($currentPage, 'appointments.php') !== false; ?>
                    <li>
                        <a href="/appointments/appointments.php"
                           class="<?= $isAppointmentsPageDesigner ? 'active' : '' ?>">Appointments</a>
                    </li>
                <?php endif; ?>

                <?php $isSettingsPage = strpos($currentPage, 'settings.php') !== false; ?>
                <li class="navbar-settings-dropdown">
                    <a href="/settings/settings.php"
                       class="settings-icon <?= $isSettingsPage ? 'active' : '' ?>"
                       title="Settings"
                       onclick="event.preventDefault(); toggleSettingsDropdown();">
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
