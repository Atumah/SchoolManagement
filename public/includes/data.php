<?php

declare(strict_types=1);

// Initialize database connection
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Database;
use App\Support\Cuid;

/**
 * Get database connection
 */
function getDb(): PDO
{
    return Database::connection();
}

// ============================================================================
// USER FUNCTIONS
// ============================================================================

/**
 * Get all users
 */
function getAllUsers(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM users ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllUsers: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get user by ID
 * @param string $id User ID (CUID)
 * @param bool $forceFresh If true, bypass session cache and fetch fresh from database
 */
function getUserById(string $id, bool $forceFresh = false): ?array
{
    // Check session first for updated user data (keep this for performance)
    // Skip session cache if forceFresh is true
    if (
        !$forceFresh
        && session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['user'])
        && isset($_SESSION['user']['id'])
        && $_SESSION['user']['id'] === $id
    ) {
        return $_SESSION['user'];
    }

    try {
        $stmt = getDb()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getUserById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get user by username
 */
function getUserByUsername(string $username): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getUserByUsername: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get user by email
 */
function getUserByEmail(string $email): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getUserByEmail: ' . $e->getMessage());
        return null;
    }
}

/**
 * Add a new user
 */
function addUser(array $userData): string
{
    // Validate email format
    if (!isset($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }

    // Validate password: at least 6 characters and contains at least one number
    if (!isset($userData['password']) || strlen($userData['password']) < 6 || !preg_match('/[0-9]/', $userData['password'])) {
        throw new InvalidArgumentException('Password must be at least 6 characters and contain at least one number');
    }

    // Generate username from email if not provided
    $username = $userData['username'] ?? null;
    if (!$username && isset($userData['email'])) {
        $username = explode('@', $userData['email'])[0];
    }

    // Generate name from first_name and last_name if not provided
    $name = $userData['name'] ?? null;
    if (!$name && isset($userData['first_name']) && isset($userData['last_name'])) {
        $name = trim($userData['first_name'] . ' ' . $userData['last_name']);
    }

    try {
        // Hash password with bcrypt
        $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);

        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO users (id, username, password, email, name, first_name, last_name, role, status, twofa_secret, twofa_enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $username,
            $hashedPassword,
            $userData['email'],
            $name,
            $userData['first_name'] ?? null,
            $userData['last_name'] ?? null,
            $userData['role'],
            $userData['status'] ?? 'Active',
            $userData['twofa_secret'] ?? null,
            ($userData['twofa_enabled'] ?? false) ? 1 : 0  // Ensure integer, not boolean
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addUser: ' . $e->getMessage());
        throw new RuntimeException('Failed to add user: ' . $e->getMessage());
    }
}

/**
 * Update user
 */
function updateUser(string $id, array $userData): bool
{
    $fields = [];
    $values = [];

    if (isset($userData['name'])) {
        $fields[] = 'name = ?';
        $values[] = $userData['name'];
    }
    if (isset($userData['first_name'])) {
        $fields[] = 'first_name = ?';
        $values[] = $userData['first_name'];
    }
    if (isset($userData['last_name'])) {
        $fields[] = 'last_name = ?';
        $values[] = $userData['last_name'];
    }
    if (isset($userData['username'])) {
        $fields[] = 'username = ?';
        $values[] = $userData['username'];
    }
    if (isset($userData['email'])) {
        $fields[] = 'email = ?';
        $values[] = $userData['email'];
    }
    if (isset($userData['role'])) {
        $fields[] = 'role = ?';
        $values[] = $userData['role'];
    }
    if (isset($userData['status'])) {
        $fields[] = 'status = ?';
        $values[] = $userData['status'];
    }
    if (!empty($userData['password'])) {
        $fields[] = 'password = ?';
        // Hash password with bcrypt
        $values[] = password_hash($userData['password'], PASSWORD_BCRYPT);
    }
    if (array_key_exists('twofa_secret', $userData)) {
        $fields[] = 'twofa_secret = ?';
        $secretValue = $userData['twofa_secret'];
        // Handle null, empty string, and actual secret values
        if ($secretValue === null || $secretValue === '') {
            $values[] = null;
        } else {
            $values[] = (string)$secretValue; // Ensure it's a string
        }
        error_log('updateUser - twofa_secret value being set: [' . ($secretValue ?? 'NULL') . '] (type: ' . gettype($secretValue) . ')');
    }
    if (array_key_exists('twofa_enabled', $userData)) {
        $fields[] = 'twofa_enabled = ?';
        $values[] = ($userData['twofa_enabled'] === true || $userData['twofa_enabled'] === 1) ? 1 : 0;
    }
    if (array_key_exists('twofa_last_used_timestep', $userData)) {
        $fields[] = 'twofa_last_used_timestep = ?';
        $values[] = $userData['twofa_last_used_timestep'] !== null ? (int)$userData['twofa_last_used_timestep'] : null;
    }

    if (empty($fields)) {
        return false;
    }

    $values[] = $id;

    try {
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';

        // Log the SQL and values for debugging
        error_log('updateUser SQL: ' . $sql);
        error_log('updateUser Values: ' . print_r($values, true));
        error_log('updateUser Fields count: ' . count($fields) . ', Values count: ' . count($values));

        $stmt = getDb()->prepare($sql);
        $result = $stmt->execute($values);

        // Check for errors
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log('Database error in updateUser - SQL: ' . $sql);
            error_log('Database error in updateUser - Values: ' . print_r($values, true));
            error_log('Database error in updateUser - Error: ' . print_r($errorInfo, true));
            return false;
        }

        // Log successful update
        error_log('updateUser executed successfully. Rows affected: ' . $stmt->rowCount());

        // Update session if this is the current user (force fresh fetch to get updated data)
        if ($result && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']) && $_SESSION['user_id'] === $id) {
            $_SESSION['user'] = getUserById($id, true);
        }

        return $result;
    } catch (\PDOException $e) {
        error_log('Database exception in updateUser: ' . $e->getMessage());
        error_log('SQL: ' . ($sql ?? 'N/A'));
        error_log('Values: ' . print_r($values ?? [], true));
        return false;
    }
}

/**
 * Delete user
 */
function deleteUser(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteUser: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// COURSE FUNCTIONS
// ============================================================================

/**
 * Get all courses
 */
function getAllCourses(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM courses ORDER BY id ASC');
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Populate students array for each course
        foreach ($courses as &$course) {
            $course['students'] = array_column(getCourseStudents($course['id']), 'id');
        }
        unset($course);

        return $courses;
    } catch (PDOException $e) {
        error_log('Database error in getAllCourses: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get course by ID
 */
function getCourseById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($course) {
            // Populate students array
            $course['students'] = array_column(getCourseStudents($id), 'id');
        }

        return $course ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getCourseById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get courses by teacher ID
 */
function getCoursesByTeacher(string $teacherId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM courses WHERE teacher_id = ? ORDER BY id ASC');
        $stmt->execute([$teacherId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Populate students array for each course
        foreach ($courses as &$course) {
            $course['students'] = array_column(getCourseStudents($course['id']), 'id');
        }
        unset($course);

        return $courses;
    } catch (PDOException $e) {
        error_log('Database error in getCoursesByTeacher: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get courses by student ID
 */
function getCoursesByStudent(string $studentId): array
{
    try {
        $stmt = getDb()->prepare('
            SELECT c.* FROM courses c
            INNER JOIN course_students cs ON c.id = cs.course_id
            WHERE cs.student_id = ?
            ORDER BY c.id ASC
        ');
        $stmt->execute([$studentId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Populate students array for each course
        foreach ($courses as &$course) {
            $course['students'] = array_column(getCourseStudents($course['id']), 'id');
        }
        unset($course);

        return $courses;
    } catch (PDOException $e) {
        error_log('Database error in getCoursesByStudent: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get total credits assigned to a specific year
 */
function getTotalCreditsByYear(int $year): int
{
    try {
        $stmt = getDb()->prepare('SELECT COALESCE(SUM(credits), 0) as total FROM courses WHERE year = ?');
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log('Database error in getTotalCreditsByYear: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get remaining credits available for a specific year
 */
function getRemainingCreditsForYear(int $year): int
{
    $total = getTotalCreditsByYear($year);
    return 60 - $total;
}

/**
 * Add a new course
 */
function addCourse(array $courseData): string
{
    try {
        $year = (int) ($courseData['year'] ?? 1);
        $credits = (int) ($courseData['credits'] ?? 5);

        // Validate EC constraint
        $currentTotal = getTotalCreditsByYear($year);
        if ($currentTotal + $credits > 60) {
            $remaining = 60 - $currentTotal;
            throw new RuntimeException("Cannot assign {$credits} ECs. Only {$remaining} ECs remaining for Year {$year}.");
        }

        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO courses (id, name, description, teacher_id, schedule, max_students, year, credits)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $courseData['name'],
            $courseData['description'] ?? null,
            $courseData['teacher_id'],
            $courseData['schedule'] ?? null,
            $courseData['max_students'] ?? 30,
            $year,
            $credits
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addCourse: ' . $e->getMessage());
        throw new RuntimeException('Failed to add course: ' . $e->getMessage());
    }
}

/**
 * Update course
 */
function updateCourse(string $id, array $courseData): bool
{
    try {
        // Get current course data to check year and credits
        $currentCourse = getCourseById($id);
        if (!$currentCourse) {
            throw new RuntimeException('Course not found');
        }

        $newYear = isset($courseData['year']) ? (int) $courseData['year'] : (int) ($currentCourse['year'] ?? 1);
        $newCredits = isset($courseData['credits']) ? (int) $courseData['credits'] : (int) ($currentCourse['credits'] ?? 5);
        $currentYear = (int) ($currentCourse['year'] ?? 1);
        $currentCredits = (int) ($currentCourse['credits'] ?? 5);

        // If year or credits changed, validate EC constraint
        if ($newYear !== $currentYear || $newCredits !== $currentCredits) {
            $totalForYear = getTotalCreditsByYear($newYear);
            $creditsToAdd = $newCredits;

            // If updating within the same year, subtract the current course's credits from the total
            if ($newYear === $currentYear) {
                $totalForYear -= $currentCredits;
            }

            if ($totalForYear + $creditsToAdd > 60) {
                $remaining = 60 - $totalForYear;
                throw new RuntimeException("Cannot assign {$creditsToAdd} ECs. Only {$remaining} ECs remaining for Year {$newYear}.");
            }
        }

        $stmt = getDb()->prepare('
            UPDATE courses
            SET name = ?, description = ?, teacher_id = ?, schedule = ?, max_students = ?, year = ?, credits = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $courseData['name'],
            $courseData['description'] ?? null,
            $courseData['teacher_id'],
            $courseData['schedule'] ?? null,
            $courseData['max_students'] ?? 30,
            $newYear,
            $newCredits,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateCourse: ' . $e->getMessage());
        return false;
    } catch (RuntimeException $e) {
        error_log('Validation error in updateCourse: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Delete course
 */
function deleteCourse(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM courses WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteCourse: ' . $e->getMessage());
        return false;
    }
}

/**
 * Join a course (enroll student)
 */
function joinCourse(string $courseId, string $userId): bool
{
    try {
        // Verify user is a student
        $user = getUserById($userId);
        if (!$user || $user['role'] !== 'Student') {
            return false; // Only students can join courses
        }

        // Check if already enrolled
        $checkStmt = getDb()->prepare('SELECT id FROM course_students WHERE course_id = ? AND student_id = ?');
        $checkStmt->execute([$courseId, $userId]);
        if ($checkStmt->fetch()) {
            return false; // Already enrolled
        }

        // Check if course is full
        $course = getCourseById($courseId);
        if (!$course) {
            return false;
        }

        $countStmt = getDb()->prepare('SELECT COUNT(*) as count FROM course_students WHERE course_id = ?');
        $countStmt->execute([$courseId]);
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($count >= $course['max_students']) {
            return false; // Course is full
        }

        // Enroll student
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('INSERT INTO course_students (id, course_id, student_id) VALUES (?, ?, ?)');
        if (!$stmt->execute([$cuid, $courseId, $userId])) {
            return false;
        }

        // Automatically create a grade record with NULL values for this student and course
        // Use INSERT IGNORE to prevent errors if grade record already exists
        $gradeCuid = Cuid::generate();
        $gradeStmt = getDb()->prepare('
            INSERT IGNORE INTO grades (id, student_id, course_id, teacher_id, original_grade, final_grade, date, notes)
            VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL)
        ');
        try {
            $gradeStmt->execute([
                $gradeCuid,
                $userId,
                $courseId,
                $course['teacher_id']
            ]);
        } catch (PDOException $e) {
            // If grade already exists, that's fine - continue
            error_log('Grade record may already exist for student ' . $userId . ' in course ' . $courseId . ': ' . $e->getMessage());
        }

        return true;
    } catch (PDOException $e) {
        error_log('Database error in joinCourse: ' . $e->getMessage());
        return false;
    }
}

/**
 * Leave a course (unenroll student)
 */
function leaveCourse(string $courseId, string $userId): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM course_students WHERE course_id = ? AND student_id = ?');
        return $stmt->execute([$courseId, $userId]);
    } catch (PDOException $e) {
        error_log('Database error in leaveCourse: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get students enrolled in a course
 */
function getCourseStudents(string $courseId): array
{
    try {
        $stmt = getDb()->prepare('
            SELECT u.* FROM users u
            INNER JOIN course_students cs ON u.id = cs.student_id
            WHERE cs.course_id = ?
            ORDER BY u.name ASC
        ');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getCourseStudents: ' . $e->getMessage());
        return [];
    }
}

// ============================================================================
// GRADE FUNCTIONS
// ============================================================================

/**
 * Get all grades
 */
function getAllGrades(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM grades ORDER BY date DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllGrades: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get grade by ID
 */
function getGradeById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM grades WHERE id = ?');
        $stmt->execute([$id]);
        $grade = $stmt->fetch(PDO::FETCH_ASSOC);
        return $grade ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getGradeById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get grades by teacher ID
 */
function getGradesByTeacher(string $teacherId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM grades WHERE teacher_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getGradesByTeacher: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get grades by student ID
 */
function getGradesByStudent(string $studentId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM grades WHERE student_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getGradesByStudent: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get grades by course ID
 */
function getGradesByCourse(string $courseId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM grades WHERE course_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getGradesByCourse: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if a grade already exists for a student and course combination
 */
function gradeExistsForStudentAndCourse(string $studentId, string $courseId): bool
{
    try {
        $stmt = getDb()->prepare('SELECT COUNT(*) as count FROM grades WHERE student_id = ? AND course_id = ?');
        $stmt->execute([$studentId, $courseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] ?? 0) > 0;
    } catch (PDOException $e) {
        error_log('Database error in gradeExistsForStudentAndCourse: ' . $e->getMessage());
        return false;
    }
}

/**
 * Round Dutch grade (1.0-10.0 scale)
 * Standard mathematical rounding: 7.5 → 8, 7.4 → 7, 5.5 → 6, 5.4 → 5
 */
function roundDutchGrade(float $originalGrade): int
{
    return (int) round($originalGrade);
}

/**
 * Check if a final grade is passing (> 5.5)
 */
function isPassingGrade(int $finalGrade): bool
{
    return $finalGrade > 5.5;
}

/**
 * Add a new grade
 */
function addGrade(array $gradeData): string
{
    try {
        // Validate and round grade - must have a valid grade value
        $originalGrade = isset($gradeData['original_grade']) ? (float) $gradeData['original_grade'] : null;

        if ($originalGrade === null) {
            throw new RuntimeException('Original grade is required');
        }

        if ($originalGrade < 1.0 || $originalGrade > 10.0) {
            throw new RuntimeException('Original grade must be between 1.0 and 10.0');
        }

        $finalGrade = roundDutchGrade($originalGrade);

        // Set date to current date if not provided (date published)
        $date = $gradeData['date'] ?? date('Y-m-d');

        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO grades (id, student_id, course_id, teacher_id, original_grade, final_grade, date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $gradeData['student_id'],
            $gradeData['course_id'],
            $gradeData['teacher_id'],
            $originalGrade,
            $finalGrade,
            $date,
            $gradeData['notes'] ?? null
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addGrade: ' . $e->getMessage());
        throw new RuntimeException('Failed to add grade: ' . $e->getMessage());
    }
}

/**
 * Update grade
 */
function updateGrade(string $id, array $gradeData): bool
{
    try {
        // Validate and round grade
        $originalGrade = isset($gradeData['original_grade']) ? (float) $gradeData['original_grade'] : null;

        // If original_grade not provided, try 'grade' for backward compatibility
        if ($originalGrade === null && isset($gradeData['grade'])) {
            $originalGrade = (float) $gradeData['grade'];
        }

        // Original grade is required for update - cannot be NULL
        if ($originalGrade === null) {
            throw new RuntimeException('Original grade is required for update');
        }

        if ($originalGrade < 1.0 || $originalGrade > 10.0) {
            throw new RuntimeException('Original grade must be between 1.0 and 10.0');
        }

        $finalGrade = roundDutchGrade($originalGrade);

        // Check if existing grade is NULL - if so, set date to current date (date published)
        $existingGrade = getGradeById($id);
        $date = $gradeData['date'] ?? null;
        if ($date === null) {
            if ($existingGrade && ($existingGrade['original_grade'] === null || $existingGrade['date'] === null)) {
                // First time entering a grade - set date published
                $date = date('Y-m-d');
            } elseif ($existingGrade && isset($existingGrade['date'])) {
                // Keep existing date
                $date = $existingGrade['date'];
            } else {
                // No existing date, set to current
                $date = date('Y-m-d');
            }
        }

        $stmt = getDb()->prepare('
            UPDATE grades
            SET student_id = ?, course_id = ?, original_grade = ?, final_grade = ?, date = ?, notes = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $gradeData['student_id'],
            $gradeData['course_id'],
            $originalGrade,
            $finalGrade,
            $date,
            $gradeData['notes'] ?? null,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateGrade: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete grade
 */
function deleteGrade(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM grades WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteGrade: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// PROGRESS FUNCTIONS
// ============================================================================

/**
 * Get all progress entries
 */
function getAllProgress(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM progress ORDER BY date DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllProgress: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get progress by ID
 */
function getProgressById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM progress WHERE id = ?');
        $stmt->execute([$id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        return $progress ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getProgressById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get progress by teacher ID
 */
function getProgressByTeacher(string $teacherId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM progress WHERE teacher_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getProgressByTeacher: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get progress by student ID
 */
function getProgressByStudent(string $studentId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM progress WHERE student_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getProgressByStudent: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add a new progress entry
 */
function addProgress(array $progressData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO progress (id, student_id, course_id, teacher_id, notes, date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $progressData['student_id'],
            $progressData['course_id'],
            $progressData['teacher_id'],
            $progressData['notes'],
            $progressData['date'],
            $progressData['status'] ?? 'Stable'
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addProgress: ' . $e->getMessage());
        throw new RuntimeException('Failed to add progress: ' . $e->getMessage());
    }
}

/**
 * Update progress
 */
function updateProgress(string $id, array $progressData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE progress
            SET student_id = ?, course_id = ?, notes = ?, date = ?, status = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $progressData['student_id'],
            $progressData['course_id'],
            $progressData['notes'],
            $progressData['date'],
            $progressData['status'] ?? 'Stable',
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateProgress: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete progress
 */
function deleteProgress(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM progress WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteProgress: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// PROGRESS SUMMARY FUNCTIONS
// ============================================================================

/**
 * Get all courses for a specific year
 */
function getCoursesByYear(int $year): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM courses WHERE year = ? ORDER BY name ASC');
        $stmt->execute([$year]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Populate students array for each course
        foreach ($courses as &$course) {
            $course['students'] = array_column(getCourseStudents($course['id']), 'id');
        }
        unset($course);

        return $courses;
    } catch (PDOException $e) {
        error_log('Database error in getCoursesByYear: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get courses a student is enrolled in for a specific year
 */
function getStudentCoursesByYear(string $studentId, int $year): array
{
    try {
        $stmt = getDb()->prepare('
            SELECT c.* FROM courses c
            INNER JOIN course_students cs ON c.id = cs.course_id
            WHERE cs.student_id = ? AND c.year = ?
            ORDER BY c.name ASC
        ');
        $stmt->execute([$studentId, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getStudentCoursesByYear: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get student grades filtered by year
 */
function getGradesByStudentAndYear(string $studentId, int $year): array
{
    try {
        $stmt = getDb()->prepare('
            SELECT g.* FROM grades g
            INNER JOIN courses c ON g.course_id = c.id
            WHERE g.student_id = ? AND c.year = ?
            ORDER BY g.date DESC, g.id DESC
        ');
        $stmt->execute([$studentId, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getGradesByStudentAndYear: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all students for progress view (teachers/admins)
 */
function getAllStudentsForProgress(): array
{
    try {
        $stmt = getDb()->prepare("SELECT * FROM users WHERE role = 'Student' ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllStudentsForProgress: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get student progress summary (Year 1-4 data with EC calculations)
 */
function getStudentProgressSummary(string $studentId): array
{
    try {
        $summary = [
            1 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            2 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            3 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            4 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
        ];

        // Get all courses the student is enrolled in
        $studentCourses = getCoursesByStudent($studentId);

        // Get all grades for the student
        $studentGrades = getGradesByStudent($studentId);
        $gradesByCourseId = [];
        foreach ($studentGrades as $grade) {
            $gradesByCourseId[$grade['course_id']] = $grade;
        }

        // Process each year
        for ($year = 1; $year <= 4; $year++) {
            $yearCourses = array_filter($studentCourses, static fn ($course) => (int)($course['year'] ?? 1) === $year);

            foreach ($yearCourses as $course) {
                $credits = (int) ($course['credits'] ?? 5);
                $summary[$year]['total_credits_enrolled'] += $credits;

                // Check if course has a passing grade
                if (isset($gradesByCourseId[$course['id']])) {
                    $grade = $gradesByCourseId[$course['id']];
                    $finalGrade = (int) ($grade['final_grade'] ?? 0);

                    if (isPassingGrade($finalGrade)) {
                        $summary[$year]['completed_credits'] += $credits;
                    }

                    // Add grade info to course
                    $course['grade'] = $grade;
                    $course['has_grade'] = true;
                    $course['is_passing'] = isPassingGrade($finalGrade);
                } else {
                    $course['grade'] = null;
                    $course['has_grade'] = false;
                    $course['is_passing'] = false;
                }

                $summary[$year]['courses'][] = $course;
            }

            // Calculate progress percentage
            if ($summary[$year]['total_credits_enrolled'] > 0) {
                $summary[$year]['progress_percentage'] = round(
                    ($summary[$year]['completed_credits'] / 60) * 100,
                    1
                );
            }
        }

        return $summary;
    } catch (PDOException $e) {
        error_log('Database error in getStudentProgressSummary: ' . $e->getMessage());
        return [
            1 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            2 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            3 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
            4 => ['total_credits_enrolled' => 0, 'completed_credits' => 0, 'courses' => [], 'progress_percentage' => 0],
        ];
    }
}

// ============================================================================
// NOTES FUNCTIONS
// ============================================================================

/**
 * Get all notes
 */
function getAllNotes(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM notes ORDER BY date DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllNotes: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get note by ID
 */
function getNoteById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM notes WHERE id = ?');
        $stmt->execute([$id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        return $note ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getNoteById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get notes by teacher ID
 */
function getNotesByTeacher(string $teacherId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM notes WHERE teacher_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getNotesByTeacher: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add a new note
 */
function addNote(array $noteData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO notes (id, teacher_id, student_id, course_id, title, content, tags, date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $noteData['teacher_id'],
            $noteData['student_id'] ?? null,
            $noteData['course_id'] ?? null,
            $noteData['title'],
            $noteData['content'],
            $noteData['tags'] ?? null,
            $noteData['date']
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addNote: ' . $e->getMessage());
        throw new RuntimeException('Failed to add note: ' . $e->getMessage());
    }
}

/**
 * Update note
 */
function updateNote(string $id, array $noteData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE notes
            SET title = ?, content = ?, student_id = ?, course_id = ?, tags = ?, date = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $noteData['title'],
            $noteData['content'],
            $noteData['student_id'] ?? null,
            $noteData['course_id'] ?? null,
            $noteData['tags'] ?? null,
            $noteData['date'],
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateNote: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete note
 */
function deleteNote(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM notes WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteNote: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ANNOUNCEMENTS FUNCTIONS
// ============================================================================

/**
 * Get all announcements
 */
function getAllAnnouncements(bool $publishedOnly = false): array
{
    try {
        if ($publishedOnly) {
            $stmt = getDb()->query('SELECT * FROM announcements WHERE is_published = 1 ORDER BY created_at DESC');
        } else {
            $stmt = getDb()->query('SELECT * FROM announcements ORDER BY created_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllAnnouncements: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get announcement by ID
 */
function getAnnouncementById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        return $announcement ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getAnnouncementById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Add a new announcement
 */
function addAnnouncement(array $announcementData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO announcements (id, title, content, author_id, is_published)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $announcementData['title'],
            $announcementData['content'],
            $announcementData['author_id'],
            $announcementData['is_published'] ?? true
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addAnnouncement: ' . $e->getMessage());
        throw new RuntimeException('Failed to add announcement: ' . $e->getMessage());
    }
}

/**
 * Update announcement
 */
function updateAnnouncement(string $id, array $announcementData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE announcements
            SET title = ?, content = ?, is_published = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $announcementData['title'],
            $announcementData['content'],
            $announcementData['is_published'] ?? true,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateAnnouncement: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete announcement
 */
function deleteAnnouncement(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM announcements WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteAnnouncement: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// EVENTS FUNCTIONS
// ============================================================================

/**
 * Get all events
 */
function getAllEvents(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM events ORDER BY event_date ASC, event_time ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllEvents: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get event by ID
 */
function getEventById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getEventById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Add a new event
 */
function addEvent(array $eventData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO events (id, title, description, event_date, event_time, location, author_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $eventData['title'],
            $eventData['description'] ?? null,
            $eventData['event_date'],
            $eventData['event_time'] ?? null,
            $eventData['location'] ?? null,
            $eventData['author_id']
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addEvent: ' . $e->getMessage());
        throw new RuntimeException('Failed to add event: ' . $e->getMessage());
    }
}

/**
 * Update event
 */
function updateEvent(string $id, array $eventData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE events
            SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $eventData['title'],
            $eventData['description'] ?? null,
            $eventData['event_date'],
            $eventData['event_time'] ?? null,
            $eventData['location'] ?? null,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateEvent: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete event
 */
function deleteEvent(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM events WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteEvent: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// APPOINTMENTS FUNCTIONS
// ============================================================================

/**
 * Get all appointments
 */
function getAllAppointments(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM appointments ORDER BY appointment_date ASC, appointment_time ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllAppointments: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get appointment by ID
 */
function getAppointmentById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM appointments WHERE id = ?');
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $appointment ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getAppointmentById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get appointments by user ID (created by or appointee)
 */
function getAppointmentsByUser(string $userId): array
{
    try {
        $stmt = getDb()->prepare('
            SELECT * FROM appointments
            WHERE created_by_id = ? OR appointee_id = ?
            ORDER BY appointment_date ASC, appointment_time ASC
        ');
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAppointmentsByUser: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add a new appointment
 */
function addAppointment(array $appointmentData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO appointments (id, created_by_id, appointee_id, title, description, appointment_date, appointment_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $appointmentData['created_by_id'],
            $appointmentData['appointee_id'] ?? null,
            $appointmentData['title'],
            $appointmentData['description'] ?? null,
            $appointmentData['appointment_date'],
            $appointmentData['appointment_time'],
            $appointmentData['status'] ?? 'Pending'
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addAppointment: ' . $e->getMessage());
        throw new RuntimeException('Failed to add appointment: ' . $e->getMessage());
    }
}

/**
 * Update appointment
 */
function updateAppointment(string $id, array $appointmentData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE appointments
            SET title = ?, description = ?, appointment_date = ?, appointment_time = ?, status = ?, appointee_id = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $appointmentData['title'],
            $appointmentData['description'] ?? null,
            $appointmentData['appointment_date'],
            $appointmentData['appointment_time'],
            $appointmentData['status'] ?? 'Pending',
            $appointmentData['appointee_id'] ?? null,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateAppointment: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete appointment
 */
function deleteAppointment(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM appointments WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteAppointment: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ATTENDANCE FUNCTIONS
// ============================================================================

/**
 * Get all attendance records
 */
function getAllAttendance(): array
{
    try {
        $stmt = getDb()->query('SELECT * FROM attendance ORDER BY date DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAllAttendance: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance by ID
 */
function getAttendanceById(string $id): ?array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM attendance WHERE id = ?');
        $stmt->execute([$id]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        return $attendance ?: null;
    } catch (PDOException $e) {
        error_log('Database error in getAttendanceById: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get attendance by student ID
 */
function getAttendanceByStudent(string $studentId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAttendanceByStudent: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance by course ID
 */
function getAttendanceByCourse(string $courseId): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM attendance WHERE course_id = ? ORDER BY date DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAttendanceByCourse: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance by date
 */
function getAttendanceByDate(string $date): array
{
    try {
        $stmt = getDb()->prepare('SELECT * FROM attendance WHERE date = ? ORDER BY id ASC');
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Database error in getAttendanceByDate: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add a new attendance record
 */
function addAttendance(array $attendanceData): string
{
    try {
        $cuid = Cuid::generate();
        $stmt = getDb()->prepare('
            INSERT INTO attendance (id, student_id, course_id, teacher_id, date, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $cuid,
            $attendanceData['student_id'],
            $attendanceData['course_id'],
            $attendanceData['teacher_id'],
            $attendanceData['date'],
            $attendanceData['status'],
            $attendanceData['notes'] ?? null
        ]);
        return $cuid;
    } catch (PDOException $e) {
        error_log('Database error in addAttendance: ' . $e->getMessage());
        throw new RuntimeException('Failed to add attendance: ' . $e->getMessage());
    }
}

/**
 * Update attendance
 */
function updateAttendance(string $id, array $attendanceData): bool
{
    try {
        $stmt = getDb()->prepare('
            UPDATE attendance
            SET student_id = ?, course_id = ?, teacher_id = ?, date = ?, status = ?, notes = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $attendanceData['student_id'],
            $attendanceData['course_id'],
            $attendanceData['teacher_id'],
            $attendanceData['date'],
            $attendanceData['status'],
            $attendanceData['notes'] ?? null,
            $id
        ]);
    } catch (PDOException $e) {
        error_log('Database error in updateAttendance: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete attendance
 */
function deleteAttendance(string $id): bool
{
    try {
        $stmt = getDb()->prepare('DELETE FROM attendance WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Database error in deleteAttendance: ' . $e->getMessage());
        return false;
    }
}
