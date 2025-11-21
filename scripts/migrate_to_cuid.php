<?php

declare(strict_types=1);

/**
 * Migration script to convert all INT AUTO_INCREMENT IDs to CUID strings
 * 
 * WARNING: This script modifies the database structure and data.
 * Make sure to backup your database before running this script.
 * 
 * Usage: php scripts/migrate_to_cuid.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Support\Cuid;

echo "Starting CUID migration...\n\n";

$db = Database::connection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db->beginTransaction();

    // Tables and their foreign key relationships
    $tables = [
        'users' => [
            'foreign_keys' => [
                'courses' => ['teacher_id'],
                'course_students' => ['student_id'],
                'attendance' => ['student_id', 'teacher_id'],
                'progress' => ['student_id', 'teacher_id'],
                'grades' => ['student_id', 'teacher_id'],
                'notes' => ['teacher_id', 'student_id'],
                'announcements' => ['author_id'],
                'events' => ['author_id'],
                'appointments' => ['created_by_id', 'appointee_id'],
            ]
        ],
        'courses' => [
            'foreign_keys' => [
                'course_students' => ['course_id'],
                'attendance' => ['course_id'],
                'progress' => ['course_id'],
                'grades' => ['course_id'],
                'notes' => ['course_id'],
            ]
        ],
    ];

    // Step 1: Add CUID columns to all tables (if they don't exist)
    echo "Step 1: Adding CUID columns...\n";
    $allTables = ['users', 'courses', 'course_students', 'attendance', 'progress', 'grades', 'notes', 'announcements', 'events', 'appointments'];
    
    foreach ($allTables as $table) {
        echo "  - Checking/Adding cuid column to {$table}...\n";
        try {
            // Check if column exists
            $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE 'cuid'");
            if ($stmt->rowCount() === 0) {
                $db->exec("ALTER TABLE {$table} ADD COLUMN cuid VARCHAR(25) NULL AFTER id");
                echo "    Added cuid column\n";
            } else {
                echo "    cuid column already exists, skipping\n";
            }
        } catch (PDOException $e) {
            // If column exists, continue
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
            echo "    cuid column already exists, skipping\n";
        }
    }

    // Step 2: Generate CUIDs for all existing records
    echo "\nStep 2: Generating CUIDs for existing records...\n";
    
    // Start with users (no dependencies)
    echo "  - Generating CUIDs for users...\n";
    $stmt = $db->query("SELECT id FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userCuidMap = [];
    foreach ($users as $user) {
        $cuid = Cuid::generate();
        $userCuidMap[$user['id']] = $cuid;
        $db->prepare("UPDATE users SET cuid = ? WHERE id = ?")->execute([$cuid, $user['id']]);
    }
    echo "    Generated " . count($userCuidMap) . " CUIDs for users\n";

    // Then courses (depends on users)
    echo "  - Generating CUIDs for courses...\n";
    $stmt = $db->query("SELECT id FROM courses ORDER BY id");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $courseCuidMap = [];
    foreach ($courses as $course) {
        $cuid = Cuid::generate();
        $courseCuidMap[$course['id']] = $cuid;
        $db->prepare("UPDATE courses SET cuid = ? WHERE id = ?")->execute([$cuid, $course['id']]);
    }
    echo "    Generated " . count($courseCuidMap) . " CUIDs for courses\n";

    // Then other tables
    $otherTables = ['course_students', 'attendance', 'progress', 'grades', 'notes', 'announcements', 'events', 'appointments'];
    foreach ($otherTables as $table) {
        echo "  - Generating CUIDs for {$table}...\n";
        $stmt = $db->query("SELECT id FROM {$table} ORDER BY id");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $cuid = Cuid::generate();
            $db->prepare("UPDATE {$table} SET cuid = ? WHERE id = ?")->execute([$cuid, $record['id']]);
        }
        echo "    Generated CUIDs for " . count($records) . " records in {$table}\n";
    }

    // Step 3: Update foreign key columns to VARCHAR(25) and migrate data
    echo "\nStep 3: Updating foreign key columns...\n";
    
    // Update course_students
    echo "  - Updating course_students foreign keys...\n";
    $db->exec("ALTER TABLE course_students ADD COLUMN course_cuid VARCHAR(25) NULL, ADD COLUMN student_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, course_id, student_id FROM course_students");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $courseCuid = $courseCuidMap[$row['course_id']] ?? null;
        $studentCuid = $userCuidMap[$row['student_id']] ?? null;
        if ($courseCuid && $studentCuid) {
            $db->prepare("UPDATE course_students SET course_cuid = ?, student_cuid = ? WHERE id = ?")
               ->execute([$courseCuid, $studentCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE course_students DROP FOREIGN KEY course_students_ibfk_1, DROP FOREIGN KEY course_students_ibfk_2");
    $db->exec("ALTER TABLE course_students DROP COLUMN course_id, DROP COLUMN student_id");
    $db->exec("ALTER TABLE course_students CHANGE course_cuid course_id VARCHAR(25) NOT NULL, CHANGE student_cuid student_id VARCHAR(25) NOT NULL");

    // Update attendance
    echo "  - Updating attendance foreign keys...\n";
    $db->exec("ALTER TABLE attendance ADD COLUMN student_cuid VARCHAR(25) NULL, ADD COLUMN course_cuid VARCHAR(25) NULL, ADD COLUMN teacher_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, student_id, course_id, teacher_id FROM attendance");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentCuid = $userCuidMap[$row['student_id']] ?? null;
        $courseCuid = $courseCuidMap[$row['course_id']] ?? null;
        $teacherCuid = $userCuidMap[$row['teacher_id']] ?? null;
        if ($studentCuid && $courseCuid && $teacherCuid) {
            $db->prepare("UPDATE attendance SET student_cuid = ?, course_cuid = ?, teacher_cuid = ? WHERE id = ?")
               ->execute([$studentCuid, $courseCuid, $teacherCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE attendance DROP FOREIGN KEY attendance_ibfk_1, DROP FOREIGN KEY attendance_ibfk_2, DROP FOREIGN KEY attendance_ibfk_3");
    $db->exec("ALTER TABLE attendance DROP COLUMN student_id, DROP COLUMN course_id, DROP COLUMN teacher_id");
    $db->exec("ALTER TABLE attendance CHANGE student_cuid student_id VARCHAR(25) NOT NULL, CHANGE course_cuid course_id VARCHAR(25) NOT NULL, CHANGE teacher_cuid teacher_id VARCHAR(25) NOT NULL");

    // Update progress
    echo "  - Updating progress foreign keys...\n";
    $db->exec("ALTER TABLE progress ADD COLUMN student_cuid VARCHAR(25) NULL, ADD COLUMN course_cuid VARCHAR(25) NULL, ADD COLUMN teacher_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, student_id, course_id, teacher_id FROM progress");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentCuid = $userCuidMap[$row['student_id']] ?? null;
        $courseCuid = $courseCuidMap[$row['course_id']] ?? null;
        $teacherCuid = $userCuidMap[$row['teacher_id']] ?? null;
        if ($studentCuid && $courseCuid && $teacherCuid) {
            $db->prepare("UPDATE progress SET student_cuid = ?, course_cuid = ?, teacher_cuid = ? WHERE id = ?")
               ->execute([$studentCuid, $courseCuid, $teacherCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE progress DROP FOREIGN KEY progress_ibfk_1, DROP FOREIGN KEY progress_ibfk_2, DROP FOREIGN KEY progress_ibfk_3");
    $db->exec("ALTER TABLE progress DROP COLUMN student_id, DROP COLUMN course_id, DROP COLUMN teacher_id");
    $db->exec("ALTER TABLE progress CHANGE student_cuid student_id VARCHAR(25) NOT NULL, CHANGE course_cuid course_id VARCHAR(25) NOT NULL, CHANGE teacher_cuid teacher_id VARCHAR(25) NOT NULL");

    // Update grades
    echo "  - Updating grades foreign keys...\n";
    $db->exec("ALTER TABLE grades ADD COLUMN student_cuid VARCHAR(25) NULL, ADD COLUMN course_cuid VARCHAR(25) NULL, ADD COLUMN teacher_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, student_id, course_id, teacher_id FROM grades");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $studentCuid = $userCuidMap[$row['student_id']] ?? null;
        $courseCuid = $courseCuidMap[$row['course_id']] ?? null;
        $teacherCuid = $userCuidMap[$row['teacher_id']] ?? null;
        if ($studentCuid && $courseCuid && $teacherCuid) {
            $db->prepare("UPDATE grades SET student_cuid = ?, course_cuid = ?, teacher_cuid = ? WHERE id = ?")
               ->execute([$studentCuid, $courseCuid, $teacherCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE grades DROP FOREIGN KEY grades_ibfk_1, DROP FOREIGN KEY grades_ibfk_2, DROP FOREIGN KEY grades_ibfk_3");
    $db->exec("ALTER TABLE grades DROP COLUMN student_id, DROP COLUMN course_id, DROP COLUMN teacher_id");
    $db->exec("ALTER TABLE grades CHANGE student_cuid student_id VARCHAR(25) NOT NULL, CHANGE course_cuid course_id VARCHAR(25) NOT NULL, CHANGE teacher_cuid teacher_id VARCHAR(25) NOT NULL");

    // Update notes
    echo "  - Updating notes foreign keys...\n";
    $db->exec("ALTER TABLE notes ADD COLUMN teacher_cuid VARCHAR(25) NULL, ADD COLUMN student_cuid VARCHAR(25) NULL, ADD COLUMN course_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, teacher_id, student_id, course_id FROM notes");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teacherCuid = $userCuidMap[$row['teacher_id']] ?? null;
        $studentCuid = $row['student_id'] ? ($userCuidMap[$row['student_id']] ?? null) : null;
        $courseCuid = $row['course_id'] ? ($courseCuidMap[$row['course_id']] ?? null) : null;
        $db->prepare("UPDATE notes SET teacher_cuid = ?, student_cuid = ?, course_cuid = ? WHERE id = ?")
           ->execute([$teacherCuid, $studentCuid, $courseCuid, $row['id']]);
    }
    $db->exec("ALTER TABLE notes DROP FOREIGN KEY notes_ibfk_1, DROP FOREIGN KEY notes_ibfk_2, DROP FOREIGN KEY notes_ibfk_3");
    $db->exec("ALTER TABLE notes DROP COLUMN teacher_id, DROP COLUMN student_id, DROP COLUMN course_id");
    $db->exec("ALTER TABLE notes CHANGE teacher_cuid teacher_id VARCHAR(25) NOT NULL, CHANGE student_cuid student_id VARCHAR(25) NULL, CHANGE course_cuid course_id VARCHAR(25) NULL");

    // Update announcements
    echo "  - Updating announcements foreign keys...\n";
    $db->exec("ALTER TABLE announcements ADD COLUMN author_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, author_id FROM announcements");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $authorCuid = $userCuidMap[$row['author_id']] ?? null;
        if ($authorCuid) {
            $db->prepare("UPDATE announcements SET author_cuid = ? WHERE id = ?")->execute([$authorCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE announcements DROP FOREIGN KEY announcements_ibfk_1");
    $db->exec("ALTER TABLE announcements DROP COLUMN author_id");
    $db->exec("ALTER TABLE announcements CHANGE author_cuid author_id VARCHAR(25) NOT NULL");

    // Update events
    echo "  - Updating events foreign keys...\n";
    $db->exec("ALTER TABLE events ADD COLUMN author_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, author_id FROM events");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $authorCuid = $userCuidMap[$row['author_id']] ?? null;
        if ($authorCuid) {
            $db->prepare("UPDATE events SET author_cuid = ? WHERE id = ?")->execute([$authorCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE events DROP FOREIGN KEY events_ibfk_1");
    $db->exec("ALTER TABLE events DROP COLUMN author_id");
    $db->exec("ALTER TABLE events CHANGE author_cuid author_id VARCHAR(25) NOT NULL");

    // Update appointments
    echo "  - Updating appointments foreign keys...\n";
    $db->exec("ALTER TABLE appointments ADD COLUMN created_by_cuid VARCHAR(25) NULL, ADD COLUMN appointee_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, created_by_id, appointee_id FROM appointments");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $createdByCuid = $userCuidMap[$row['created_by_id']] ?? null;
        $appointeeCuid = $row['appointee_id'] ? ($userCuidMap[$row['appointee_id']] ?? null) : null;
        $db->prepare("UPDATE appointments SET created_by_cuid = ?, appointee_cuid = ? WHERE id = ?")
           ->execute([$createdByCuid, $appointeeCuid, $row['id']]);
    }
    $db->exec("ALTER TABLE appointments DROP FOREIGN KEY appointments_ibfk_1, DROP FOREIGN KEY appointments_ibfk_2");
    $db->exec("ALTER TABLE appointments DROP COLUMN created_by_id, DROP COLUMN appointee_id");
    $db->exec("ALTER TABLE appointments CHANGE created_by_cuid created_by_id VARCHAR(25) NOT NULL, CHANGE appointee_cuid appointee_id VARCHAR(25) NULL");

    // Update courses teacher_id
    echo "  - Updating courses teacher_id...\n";
    $db->exec("ALTER TABLE courses ADD COLUMN teacher_cuid VARCHAR(25) NULL");
    $stmt = $db->query("SELECT id, teacher_id FROM courses");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teacherCuid = $userCuidMap[$row['teacher_id']] ?? null;
        if ($teacherCuid) {
            $db->prepare("UPDATE courses SET teacher_cuid = ? WHERE id = ?")->execute([$teacherCuid, $row['id']]);
        }
    }
    $db->exec("ALTER TABLE courses DROP FOREIGN KEY courses_ibfk_1");
    $db->exec("ALTER TABLE courses DROP COLUMN teacher_id");
    $db->exec("ALTER TABLE courses CHANGE teacher_cuid teacher_id VARCHAR(25) NOT NULL");

    // Step 4: Make CUID columns NOT NULL and add unique indexes
    echo "\nStep 4: Making CUID columns NOT NULL and adding indexes...\n";
    foreach ($allTables as $table) {
        echo "  - Updating {$table}...\n";
        $db->exec("ALTER TABLE {$table} MODIFY COLUMN cuid VARCHAR(25) NOT NULL");
        $db->exec("ALTER TABLE {$table} ADD UNIQUE INDEX idx_cuid (cuid)");
    }

    // Step 5: Drop old primary keys and make CUID primary key
    echo "\nStep 5: Replacing primary keys with CUID...\n";
    foreach ($allTables as $table) {
        echo "  - Updating {$table} primary key...\n";
        $db->exec("ALTER TABLE {$table} DROP PRIMARY KEY");
        $db->exec("ALTER TABLE {$table} DROP COLUMN id");
        $db->exec("ALTER TABLE {$table} CHANGE cuid id VARCHAR(25) NOT NULL");
        $db->exec("ALTER TABLE {$table} ADD PRIMARY KEY (id)");
    }

    // Step 6: Re-add foreign key constraints
    echo "\nStep 6: Re-adding foreign key constraints...\n";
    
    $db->exec("ALTER TABLE courses ADD CONSTRAINT courses_ibfk_1 FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE course_students ADD CONSTRAINT course_students_ibfk_1 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE course_students ADD CONSTRAINT course_students_ibfk_2 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE attendance ADD CONSTRAINT attendance_ibfk_1 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE attendance ADD CONSTRAINT attendance_ibfk_2 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE attendance ADD CONSTRAINT attendance_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE progress ADD CONSTRAINT progress_ibfk_1 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE progress ADD CONSTRAINT progress_ibfk_2 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE progress ADD CONSTRAINT progress_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE grades ADD CONSTRAINT grades_ibfk_1 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE grades ADD CONSTRAINT grades_ibfk_2 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE grades ADD CONSTRAINT grades_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE notes ADD CONSTRAINT notes_ibfk_1 FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE notes ADD CONSTRAINT notes_ibfk_2 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL");
    $db->exec("ALTER TABLE notes ADD CONSTRAINT notes_ibfk_3 FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL");
    $db->exec("ALTER TABLE announcements ADD CONSTRAINT announcements_ibfk_1 FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE events ADD CONSTRAINT events_ibfk_1 FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE appointments ADD CONSTRAINT appointments_ibfk_1 FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE CASCADE");
    $db->exec("ALTER TABLE appointments ADD CONSTRAINT appointments_ibfk_2 FOREIGN KEY (appointee_id) REFERENCES users(id) ON DELETE SET NULL");

    $db->commit();
    
    echo "\n✓ Migration completed successfully!\n";
    echo "All IDs have been converted to CUID strings.\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Database has been rolled back to previous state.\n";
    exit(1);
}

