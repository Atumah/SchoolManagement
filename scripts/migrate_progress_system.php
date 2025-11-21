<?php

declare(strict_types=1);

/**
 * Migration script to add year/credits to courses and original_grade/final_grade to grades
 * 
 * WARNING: This script modifies the database structure and data.
 * Make sure to backup your database before running this script.
 * 
 * Usage: php scripts/migrate_progress_system.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Support\Cuid;

echo "Starting Progress System migration...\n\n";

$db = Database::connection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Round Dutch grade (1.0-10.0 scale)
 * 7.5 → 8, 7.4 → 7, 5.5 → 6, 5.4 → 5
 */
function roundDutchGrade(float $originalGrade): int
{
    return (int) round($originalGrade);
}

try {
    // Note: DDL statements (ALTER TABLE) auto-commit in MySQL/MariaDB
    // So we can't use transactions for the migration, but we'll try to make it safe

    // Check if columns already exist
    $coursesTableInfo = $db->query("SHOW COLUMNS FROM courses LIKE 'year'")->fetch();
    $yearColumnExists = $coursesTableInfo !== false;
    
    $coursesTableInfo2 = $db->query("SHOW COLUMNS FROM courses LIKE 'credits'")->fetch();
    $creditsColumnExists = $coursesTableInfo2 !== false;
    
    $gradesTableInfo = $db->query("SHOW COLUMNS FROM grades LIKE 'original_grade'")->fetch();
    $originalGradeExists = $gradesTableInfo !== false;

    // Add year column to courses if it doesn't exist
    if (!$yearColumnExists) {
        echo "Adding 'year' column to courses table...\n";
        try {
            $db->exec("ALTER TABLE courses ADD COLUMN year INT NOT NULL DEFAULT 1 AFTER max_students");
            $db->exec("ALTER TABLE courses ADD INDEX idx_year (year)");
            // Try to add constraint, may fail if it exists
            try {
                $db->exec("ALTER TABLE courses ADD CONSTRAINT chk_year CHECK (year BETWEEN 1 AND 4)");
            } catch (PDOException $e) {
                // Constraint might already exist or MySQL version doesn't support CHECK constraints
                echo "Note: Could not add year constraint (may already exist or not supported)\n";
            }
        } catch (PDOException $e) {
            echo "Error adding year column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column 'year' already exists in courses table.\n";
    }

    // Add credits column to courses if it doesn't exist
    if (!$creditsColumnExists) {
        echo "Adding 'credits' column to courses table...\n";
        try {
            $db->exec("ALTER TABLE courses ADD COLUMN credits INT NOT NULL DEFAULT 5 AFTER year");
            // Try to add constraint, may fail if it exists
            try {
                $db->exec("ALTER TABLE courses ADD CONSTRAINT chk_credits CHECK (credits > 0 AND credits <= 60)");
            } catch (PDOException $e) {
                // Constraint might already exist or MySQL version doesn't support CHECK constraints
                echo "Note: Could not add credits constraint (may already exist or not supported)\n";
            }
        } catch (PDOException $e) {
            echo "Error adding credits column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column 'credits' already exists in courses table.\n";
    }

    // Update existing courses to have default values if needed
    echo "Updating existing courses with default values if needed...\n";
    $db->exec("UPDATE courses SET year = 1 WHERE year IS NULL OR year < 1 OR year > 4");
    $db->exec("UPDATE courses SET credits = 5 WHERE credits IS NULL OR credits < 1 OR credits > 60");

    // Add original_grade and final_grade columns to grades if they don't exist
    if (!$originalGradeExists) {
        echo "Adding 'original_grade' and 'final_grade' columns to grades table...\n";
        try {
            $db->exec("ALTER TABLE grades ADD COLUMN original_grade DECIMAL(3,1) NULL AFTER teacher_id");
            $db->exec("ALTER TABLE grades ADD COLUMN final_grade TINYINT NULL AFTER original_grade");
            $db->exec("ALTER TABLE grades ADD INDEX idx_final_grade (final_grade)");
            // Try to add constraints, may fail if they exist or not supported
            try {
                $db->exec("ALTER TABLE grades ADD CONSTRAINT chk_original_grade CHECK (original_grade IS NULL OR (original_grade >= 1.0 AND original_grade <= 10.0))");
            } catch (PDOException $e) {
                echo "Note: Could not add original_grade constraint\n";
            }
            try {
                $db->exec("ALTER TABLE grades ADD CONSTRAINT chk_final_grade CHECK (final_grade IS NULL OR (final_grade >= 1 AND final_grade <= 10))");
            } catch (PDOException $e) {
                echo "Note: Could not add final_grade constraint\n";
            }
        } catch (PDOException $e) {
            echo "Error adding grade columns: " . $e->getMessage() . "\n";
            throw $e;
        }
    } else {
        echo "Columns 'original_grade' and 'final_grade' already exist in grades table.\n";
    }

    // Migrate existing grade data
    echo "Migrating existing grade data...\n";
    $grades = $db->query("SELECT id, grade FROM grades WHERE original_grade IS NULL OR final_grade IS NULL")->fetchAll();
    
    foreach ($grades as $gradeRow) {
        $gradeId = $gradeRow['id'];
        $gradeValue = $gradeRow['grade'];
        
        // Try to parse the grade value
        $originalGrade = null;
        $finalGrade = null;
        
        // Remove any whitespace
        $gradeValue = trim($gradeValue);
        
        // Try to parse as float
        if (is_numeric($gradeValue)) {
            $floatValue = (float) $gradeValue;
            
            // Validate range (1.0 - 10.0)
            if ($floatValue >= 1.0 && $floatValue <= 10.0) {
                $originalGrade = $floatValue;
                $finalGrade = roundDutchGrade($floatValue);
            } else {
                // If it's out of range, default to 5.0
                echo "Warning: Grade '{$gradeValue}' for grade ID '{$gradeId}' is out of range. Defaulting to 5.0.\n";
                $originalGrade = 5.0;
                $finalGrade = 5;
            }
        } else {
            // If we can't parse it, default to 5.0
            echo "Warning: Could not parse grade '{$gradeValue}' for grade ID '{$gradeId}'. Defaulting to 5.0.\n";
            $originalGrade = 5.0;
            $finalGrade = 5;
        }
        
        // Update the grade
        $stmt = $db->prepare("UPDATE grades SET original_grade = ?, final_grade = ? WHERE id = ?");
        $stmt->execute([$originalGrade, $finalGrade, $gradeId]);
    }
    
    echo "Migrated " . count($grades) . " grade records.\n";

    // Make columns NOT NULL after migration
    echo "Making grade columns NOT NULL...\n";
    $db->exec("ALTER TABLE grades MODIFY COLUMN original_grade DECIMAL(3,1) NOT NULL");
    $db->exec("ALTER TABLE grades MODIFY COLUMN final_grade TINYINT NOT NULL");

    // Remove old grade column if it exists and new columns are populated
    $oldGradeColumn = $db->query("SHOW COLUMNS FROM grades LIKE 'grade'")->fetch();
    if ($oldGradeColumn !== false) {
        echo "Old 'grade' column exists. You may want to drop it manually after verifying migration.\n";
        echo "To drop it, run: ALTER TABLE grades DROP COLUMN grade;\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "\nError during migration: " . $e->getMessage() . "\n";
    echo "Note: DDL statements auto-commit, so partial migration may have occurred.\n";
    exit(1);
}

