# School Management System - "The Morningstar"

A professional PHP 8.2 web application for managing a school's daily operations, including user management, courses, grades, attendance, announcements, events, and more. Built with Docker, MariaDB, and modern PHP practices.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Routing System](#routing-system)
- [Database Setup](#database-setup)
- [Architecture](#architecture)
- [Development Guide](#development-guide)
- [What's Implemented](#whats-implemented)
- [Environment Variables](#environment-variables)
- [Quality Checks](#quality-checks)
- [Troubleshooting](#troubleshooting)

## 🎯 Overview

This is a complete school management system designed for "The Morningstar" school. It provides functionality for:

- **User Management**: Admin, Teachers, Students, Principal, Web Designer roles
- **Course Management**: Create courses, enroll students, manage schedules
- **Grades & Progress**: Track student grades and progress notes
- **Attendance**: Record and manage student attendance
- **Announcements & Events**: Publish announcements and manage school events
- **Appointments**: Schedule and manage appointments between users
- **Two-Factor Authentication**: Secure login with TOTP-based 2FA
- **Notes**: Teachers can take notes about students and courses

## ✨ Features

- ✅ **User Authentication**: Secure login with password hashing (bcrypt)
- ✅ **Two-Factor Authentication**: TOTP-based 2FA with replay protection
- ✅ **Role-Based Access Control**: Different permissions for Admin, Teacher, Student, etc.
- ✅ **Database Integration**: Full CRUD operations with prepared statements
- ✅ **Session Management**: Secure session handling with CSRF protection
- ✅ **File Uploads**: Profile picture uploads
- ✅ **Responsive Design**: Modern, mobile-friendly UI

## 📁 Project Structure

```
SchoolManagement/
├── public/                    # Web-accessible files (Document Root)
│   ├── index.php             # Dashboard/Homepage
│   ├── login.php             # Login page
│   ├── login-2fa.php         # 2FA verification page
│   ├── logout.php            # Logout handler
│   │
│   ├── users/                # User management pages
│   │   └── users.php         # User CRUD (Admin only)
│   │
│   ├── courses/              # Course management
│   │   ├── course.php        # Course CRUD (Admin/Teacher)
│   │   └── join.php          # Student course enrollment
│   │
│   ├── grades/               # Grade management
│   │   └── view.php          # Grade CRUD (Teacher/Admin)
│   │
│   ├── attendance/           # Attendance tracking
│   │   └── attendance.php    # Attendance CRUD (Placeholder)
│   │
│   ├── progress/             # Progress tracking
│   │   └── progress.php      # Progress notes CRUD (Teacher)
│   │
│   ├── notes/                # General notes
│   │   └── notes.php         # Notes CRUD (Teacher/Admin)
│   │
│   ├── announcements/        # School announcements
│   │   └── announcements.php # Announcements CRUD (Placeholder)
│   │
│   ├── events/               # School events
│   │   └── events.php        # Events CRUD (Placeholder)
│   │
│   ├── appointments/         # Appointments/meetings
│   │   └── appointments.php # Appointments CRUD (Placeholder)
│   │
│   ├── overview/             # Overview dashboard
│   │   └── overview.php      # Overview page (Placeholder)
│   │
│   ├── settings/             # User settings
│   │   └── settings.php      # Profile & 2FA settings
│   │
│   ├── includes/            # Shared PHP includes
│   │   ├── auth.php         # Authentication functions
│   │   ├── data.php         # Database functions (CRUD)
│   │   ├── nav.php          # Navigation component
│   │   ├── totp.php         # 2FA/TOTP functions
│   │   └── router.php       # Router class (not currently used)
│   │
│   ├── assets/              # Static assets
│   │   ├── styles.css       # Main stylesheet
│   │   ├── nav.css          # Navigation styles
│   │   ├── components.css   # Component styles
│   │   └── auth.css         # Auth page styles
│   │
│   ├── uploads/            # User uploads
│   │   └── profiles/       # Profile pictures
│   │
│   └── errors/             # Error pages
│       ├── 403.php         # Forbidden
│       ├── 404.php         # Not Found
│       └── 500.php         # Server Error
│
├── src/                    # Application source code (PSR-4)
│   ├── Config/             # Configuration classes
│   ├── Database/           # Database classes
│   ├── Repository/         # Data repositories
│   └── Support/            # Support utilities
│
├── config/                 # Configuration files
│   └── bootstrap.php       # Application bootstrap
│
├── docker/                 # Docker configuration
│   ├── php/               # PHP container config
│   │   ├── Dockerfile     # PHP image definition
│   │   └── php.ini        # PHP configuration
│   └── phpmyadmin/        # phpMyAdmin config
│
├── scripts/                # Utility scripts
│   ├── up-open.sh         # Start script
│   ├── create_admin.php   # Create admin user
│   └── migrate_passwords.php # Password migration
│
├── docs/                   # Documentation
│   ├── DATABASE_MIGRATION.md
│   └── requirements/      # Project requirements docs
│
├── database_schema.sql     # Complete database schema
├── docker-compose.yml      # Docker Compose configuration
├── composer.json          # PHP dependencies
└── .env.example           # Environment template
```

## 🚀 Getting Started

### Prerequisites

- **Docker Desktop** (or Docker Engine + Docker Compose v2)
- **Git** (for cloning the repository)
- **Composer** (optional, for local development)

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd SchoolManagement
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Set up environment variables**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and adjust database credentials if needed.

4. **Start the Docker stack**
   ```bash
   ./scripts/up-open.sh
   ```
   Or manually:
   ```bash
   docker compose up -d --build
   ```

5. **Initialize the database**
   ```bash
   # Run the database schema
   docker-compose exec db mariadb -u app -psecret app < database_schema.sql
   ```

6. **Create an admin user** (optional)
   ```bash
   docker-compose exec app php scripts/create_admin.php
   ```

7. **Access the application**
   - **Web Application**: http://localhost:49200
   - **phpMyAdmin**: http://localhost:49201
   - **Database**: localhost:49202

## 🛣️ Routing System

This application uses **file-based routing** (not the Router class). Here's how it works:

### How Routing Works

1. **Direct File Access**: PHP files in `public/` are directly accessible via URL
   - `public/index.php` → `http://localhost:49200/index.php`
   - `public/users/users.php` → `http://localhost:49200/users/users.php`

2. **Apache Configuration**: The Apache server serves files from `public/` directory
   - Document root: `/var/www/html/public` (inside container)
   - URL structure mirrors file structure

3. **URL Patterns**:
   ```
   /index.php              → Dashboard
   /login.php              → Login page
   /users/users.php        → User management
   /courses/course.php     → Course management
   /grades/view.php       → Grades
   /settings/settings.php  → Settings
   ```

### Adding a New Page

1. **Create a PHP file** in the appropriate directory:
   ```php
   <?php
   declare(strict_types=1);
   
   require_once __DIR__ . '/../includes/auth.php';
   require_once __DIR__ . '/../includes/data.php';
   
   requireAuth(); // Require login
   // or requireRole('Admin'); // Require specific role
   
   $currentUser = getCurrentUser();
   ?>
   <!DOCTYPE html>
   <html>
   <!-- Your HTML here -->
   ```

2. **Access it via URL**: `http://localhost:49200/your-folder/your-file.php`

3. **Add navigation link** in `public/includes/nav.php` if needed

### Authentication & Authorization

- **`requireAuth()`**: Requires user to be logged in
- **`requireRole('Admin')`**: Requires specific role
- **`requireAnyRole(['Admin', 'Teacher'])`**: Requires one of the roles
- **`hasRole('Admin')`**: Check if user has role (returns boolean)

## 🗄️ Database Setup

### Schema

The complete database schema is in `database_schema.sql`. Main tables:

- **users**: User accounts with roles and 2FA settings
- **courses**: Course information
- **course_students**: Student enrollment (many-to-many)
- **grades**: Student grades
- **attendance**: Daily attendance records
- **progress**: Student progress notes
- **notes**: General notes
- **announcements**: School announcements
- **events**: School events calendar
- **appointments**: User appointments/meetings

### Database Functions

All database operations are in `public/includes/data.php`:

- **Users**: `getAllUsers()`, `getUserById()`, `addUser()`, `updateUser()`, `deleteUser()`
- **Courses**: `getAllCourses()`, `getCourseById()`, `addCourse()`, `updateCourse()`, `deleteCourse()`
- **Grades**: `getAllGrades()`, `addGrade()`, `updateGrade()`, `deleteGrade()`
- **Progress**: `getAllProgress()`, `addProgress()`, `updateProgress()`, `deleteProgress()`
- **Notes**: `getAllNotes()`, `addNote()`, `updateNote()`, `deleteNote()`
- **And more...**

### Running Migrations

To add new database fields or tables:

1. Update `database_schema.sql`
2. Run the SQL manually:
   ```bash
   docker-compose exec db mariadb -u app -psecret app < database_schema.sql
   ```
   Or use phpMyAdmin at http://localhost:49201

## 🏗️ Architecture

### Technology Stack

- **Backend**: PHP 8.2 with PDO for database access
- **Database**: MariaDB 11.4
- **Web Server**: Apache 2.4
- **Containerization**: Docker & Docker Compose
- **Session Management**: PHP native sessions
- **Password Hashing**: bcrypt via `password_hash()`
- **2FA**: Custom TOTP implementation

### Code Organization

1. **Public Files** (`public/`): All web-accessible PHP files
2. **Includes** (`public/includes/`): Shared PHP functions and components
3. **Source Code** (`src/`): PSR-4 autoloaded classes
4. **Configuration** (`config/`): Bootstrap and configuration files
5. **Database**: Functions in `public/includes/data.php` (not using ORM)

### Security Features

- ✅ **CSRF Protection**: All forms use CSRF tokens
- ✅ **SQL Injection Prevention**: Prepared statements everywhere
- ✅ **XSS Protection**: `htmlspecialchars()` for all output
- ✅ **Password Security**: bcrypt hashing
- ✅ **2FA**: TOTP with replay protection
- ✅ **Session Security**: HttpOnly cookies, secure sessions
- ✅ **Input Validation**: Server-side validation on all inputs

## 💻 Development Guide

### Where to Put Your Files

- **New Pages**: Add to `public/` directory (or subdirectories)
- **Shared Functions**: Add to `public/includes/`
- **Classes**: Add to `src/` following PSR-4 namespace structure
- **Assets**: CSS/JS/images go in `public/assets/`
- **Uploads**: User uploads go in `public/uploads/`

### Code Style

- **PHP Version**: 8.2+ with strict types (`declare(strict_types=1)`)
- **Coding Standard**: PSR-12
- **Type Hints**: Use type hints for all function parameters and returns
- **Error Handling**: Use try-catch blocks, log errors with `error_log()`

### Running Quality Checks

```bash
# Run all checks
composer check

# Fix coding style automatically
composer lint-fix

# Run individual checks
composer lint      # PHP CodeSniffer
composer analyse   # PHPStan static analysis
```

### Hot Reload

The application supports hot reload:
- PHP files: Changes are picked up immediately (just refresh browser)
- CSS/JS: Changes are picked up immediately
- No need to restart containers for code changes

### Database Access

**Via phpMyAdmin**:
- URL: http://localhost:49201
- Username: `app` (from `.env`)
- Password: `secret` (from `.env`)

**Via Command Line**:
```bash
docker-compose exec db mariadb -u app -psecret app
```

**Via PHP**:
```php
use App\Database\Database;
$db = Database::connection();
```

## ✅ What's Implemented

### Fully Implemented Pages

1. **Dashboard** (`index.php`)
   - Role-based statistics
   - Quick action links

2. **User Management** (`users/users.php`)
   - ✅ Create, Read, Update, Delete users
   - ✅ Role assignment
   - ✅ Status management

3. **Course Management** (`courses/course.php`)
   - ✅ Create, Read, Update, Delete courses
   - ✅ Add/remove students from courses
   - ✅ Teacher assignment

4. **Course Enrollment** (`courses/join.php`)
   - ✅ Students can join/leave courses
   - ✅ Course availability checking

5. **Grades** (`grades/view.php`)
   - ✅ Create, Read, Update, Delete grades
   - ✅ Filter by student/course
   - ✅ Search functionality

6. **Progress Tracking** (`progress/progress.php`)
   - ✅ Create, Read, Update, Delete progress entries
   - ✅ Status tracking (Stable, Improving, Needs Attention, Excellent)

7. **Notes** (`notes/notes.php`)
   - ✅ Create, Read, Update, Delete notes
   - ✅ Tag support
   - ✅ Link to students/courses

8. **Settings** (`settings/settings.php`)
   - ✅ Profile management
   - ✅ Password change
   - ✅ Profile picture upload
   - ✅ Two-Factor Authentication (enable/disable/verify)

9. **Authentication**
   - ✅ Login (`login.php`)
   - ✅ 2FA Verification (`login-2fa.php`)
   - ✅ Logout (`logout.php`)
   - ✅ User registration (signup)

### Placeholder Pages (Not Yet Implemented)

These pages exist but show "Coming soon" and have no backend logic:

1. **Overview** (`overview/overview.php`) - Dashboard overview
2. **Announcements** (`announcements/announcements.php`) - School announcements
3. **Events** (`events/events.php`) - School events calendar
4. **Attendance** (`attendance/attendance.php`) - Attendance tracking
5. **Appointments** (`appointments/appointments.php`) - Appointment scheduling

> **Note**: Database functions for these exist in `public/includes/data.php` but aren't connected to the pages yet.

## 🔐 Environment Variables

Key environment variables in `.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | `SchoolManagement` |
| `APP_ENV` | Environment (local/production) | `local` |
| `APP_URL` | Base URL | `http://localhost:49200` |
| `DB_HOST` | Database host | `mariadb` |
| `DB_PORT` | Database port | `3306` |
| `DB_DATABASE` | Database name | `app` |
| `DB_USERNAME` | Database user | `app` |
| `DB_PASSWORD` | Database password | `secret` |
| `DB_ROOT_PASSWORD` | MariaDB root password | `super-secret-root-password` |
| `HOST_HTTP_PORT` | Web server port | `49200` |
| `HOST_PHPMYADMIN_PORT` | phpMyAdmin port | `49201` |

## 🧪 Quality Checks

### Before Committing

Always run quality checks:

```bash
composer check
```

This runs:
- **Composer validation**: Checks `composer.json`
- **PHP Syntax Check**: Validates all PHP files
- **PHP CodeSniffer**: PSR-12 compliance
- **PHPStan**: Static analysis (level 9, strict rules)

### Auto-Fix Issues

```bash
composer lint-fix
```

This automatically fixes:
- Coding style issues
- Formatting problems

## 🐛 Troubleshooting

### Docker Compose Issues

**1. Port Already in Use**
```bash
# Error: "Bind for 0.0.0.0:49200 failed: port is already allocated"

# Solution: Change ports in .env
HOST_HTTP_PORT=49201
HOST_PHPMYADMIN_PORT=49202

# Or find and stop the process using the port
# On macOS/Linux:
lsof -ti:49200 | xargs kill -9

# Then restart:
docker compose up -d
```

**2. Container Won't Start / Health Check Failing**
```bash
# Check container status
docker compose ps

# View detailed logs
docker compose logs app
docker compose logs db

# Check if containers are stuck in "starting" state
docker compose ps -a

# Restart specific service
docker compose restart app
docker compose restart db

# If health checks keep failing, check logs:
docker compose logs db | grep -i error
docker compose logs app | grep -i error
```

**3. Database Container Won't Start**
```bash
# Error: "Error response from daemon: driver failed programming external connectivity"

# Solution 1: Stop all containers and restart
docker compose down
docker compose up -d

# Solution 2: Check if MariaDB port is already in use
lsof -ti:49202 | xargs kill -9

# Solution 3: Remove and recreate database container
docker compose stop db
docker compose rm -f db
docker compose up -d db

# Solution 4: Check database logs for initialization errors
docker compose logs db
# Look for: "Access denied", "Can't connect", "Permission denied"
```

**4. "Cannot connect to the Docker daemon"**
```bash
# Error: "Cannot connect to the Docker daemon. Is the docker daemon running?"

# Solution: Start Docker Desktop (macOS/Windows) or Docker service (Linux)
# macOS/Windows: Open Docker Desktop application
# Linux:
sudo systemctl start docker
sudo systemctl enable docker  # Enable on boot
```

**5. Volume Mount Errors**
```bash
# Error: "Error response from daemon: invalid mount config"

# Solution 1: Check file paths are correct
# Ensure docker-compose.yml paths are relative to project root

# Solution 2: Fix permissions (Linux)
sudo chown -R $USER:$USER docker/mysql-data
sudo chown -R $USER:$USER docker/php/sessions

# Solution 3: Check Docker Desktop file sharing settings
# macOS: Docker Desktop → Settings → Resources → File Sharing
# Ensure project directory is shared
```

**6. Build Failures**
```bash
# Error: "ERROR: failed to solve: failed to fetch"

# Solution 1: Clean build cache and rebuild
docker compose build --no-cache
docker compose up -d

# Solution 2: Check internet connection (needed to pull images)
docker pull mariadb:11.4
docker pull phpmyadmin/phpmyadmin:5.2

# Solution 3: Check Dockerfile syntax
docker compose config  # Validates docker-compose.yml
```

**7. Environment Variables Not Loading**
```bash
# Error: Containers start but can't connect to database

# Solution 1: Verify .env file exists and has correct format
cat .env
# Should see: DB_HOST=mariadb, DB_USERNAME=app, etc.

# Solution 2: Check .env file location (must be in project root)
ls -la .env

# Solution 3: Restart containers to reload .env
docker compose down
docker compose up -d

# Solution 4: Verify variables are loaded
docker compose exec app env | grep DB_
```

**8. Container Keeps Restarting**
```bash
# Check restart status
docker compose ps

# View logs to see why it's crashing
docker compose logs --tail=50 app
docker compose logs --tail=50 db

# Common causes:
# - Database not ready (wait for health check)
# - Wrong credentials in .env
# - Port conflicts
# - Missing required files

# Solution: Check logs and fix the underlying issue
```

**9. "No space left on device"**
```bash
# Error: "no space left on device" or "write: no space left on device"

# Solution 1: Clean up Docker system
docker system prune -a --volumes

# Solution 2: Check disk space
df -h  # Linux/macOS
# Free up space if needed

# Solution 3: Remove unused containers/images
docker container prune
docker image prune -a
docker volume prune
```

**10. Database Connection Timeout**
```bash
# Error: "SQLSTATE[HY000] [2002] Connection timed out"

# Solution 1: Wait for database to be healthy
docker compose ps db
# Should show "healthy" status

# Solution 2: Check database is actually running
docker compose exec db mariadb-admin ping -h 127.0.0.1 -u app -psecret

# Solution 3: Verify DB_HOST in .env matches service name
# Should be: DB_HOST=mariadb (not localhost or 127.0.0.1)

# Solution 4: Check network connectivity between containers
docker compose exec app ping db
```

**11. Permission Denied Errors**
```bash
# Error: "Permission denied" when accessing files

# Solution 1: Fix session directory permissions
mkdir -p docker/php/sessions
chmod 777 docker/php/sessions  # Development only

# Solution 2: Fix uploads directory permissions
mkdir -p public/uploads/profiles
chmod 777 public/uploads/profiles  # Development only

# Solution 3: Check Docker Desktop file sharing (macOS/Windows)
# Docker Desktop → Settings → Resources → File Sharing
```

**12. Container Name Already in Use**
```bash
# Error: "Conflict. The container name 'php_app' is already in use"

# Solution: Remove old containers
docker compose down
docker rm php_app mariadb phpmyadmin  # Remove by name if needed
docker compose up -d
```

**13. Health Check Timeout**
```bash
# Error: Health check keeps failing

# Solution 1: Increase health check timeout in docker-compose.yml
# Or wait longer - database initialization can take 30-60 seconds

# Solution 2: Check if service is actually running
docker compose exec db mariadb-admin ping -h 127.0.0.1

# Solution 3: View health check logs
docker inspect mariadb | grep -A 10 Health
```

**14. Platform/Architecture Issues**
```bash
# Error: "exec /bin/sh: exec format error" (on Apple Silicon)

# Solution: Ensure platform is specified (already done for phpmyadmin)
# For other services, add to docker-compose.yml:
platform: linux/amd64

# Or use native images if available
```

### General Application Issues

**1. Database Connection Failed**
- Check if containers are running: `docker compose ps`
- Verify database credentials in `.env`
- Check database logs: `docker compose logs db`
- Ensure `.env` file exists and has correct values

**2. Permission Errors**
- Check file permissions in `public/uploads/`
- Ensure Docker has write access
- Fix permissions: `chmod -R 777 public/uploads/` (development only)

**3. Session Issues**
- Check `docker/php/sessions/` directory permissions
- Clear browser cookies
- Verify session directory exists: `mkdir -p docker/php/sessions`

**4. Reset Everything**
```bash
# Complete reset (WARNING: Deletes all data)
docker compose down -v  # Remove volumes too
rm -rf docker/mysql-data/*
docker compose up -d --build
docker-compose exec db mariadb -u app -psecret app < database_schema.sql
```

**5. Clean Start**
```bash
# Stop and remove all containers, networks, volumes
docker compose down -v

# Remove all images (optional)
docker compose down --rmi all

# Rebuild from scratch
docker compose build --no-cache
docker compose up -d
```

### Viewing Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f db
```

### Container Commands

```bash
# Execute PHP command in container
docker-compose exec app php your-script.php

# Access database CLI
docker-compose exec db mariadb -u app -psecret app

# Access container shell
docker-compose exec app bash
```

## 📚 Additional Resources

- **Database Schema**: See `database_schema.sql`
- **Migration Guide**: See `docs/DATABASE_MIGRATION.md`
- **Project Requirements**: See `docs/requirements/`
- **Architecture Docs**: See `docs/requirements/TECHNICAL_ARCHITECTURE.md`

## 🤝 Contributing

1. Follow PSR-12 coding standards
2. Run `composer check` before committing
3. Add type hints to all functions
4. Use prepared statements for database queries
5. Validate and sanitize all user input
6. Test your changes thoroughly

## 📝 License

MIT © 2025 SchoolManagement

---

**Need Help?** Check the documentation in `docs/` or review the code examples in implemented pages like `users/users.php` or `courses/course.php`.
