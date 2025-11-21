<?php

declare(strict_types=1);

/**
 * Migration script to add updated_at column to grades table
 * 
 * This migration adds the updated_at timestamp column to the grades table
 * to track when grades are last modified.
 * 
 * WARNING: This script modifies the database structure.
 * Make sure to backup your database before running this script.
 * 
 * Usage: php scripts/migrate_grades_system.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

echo "Starting Grades System migration (updated_at column)...\n\n";

$db = Database::connection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Check if updated_at column already exists
    $columns = $db->query("SHOW COLUMNS FROM grades LIKE 'updated_at'")->fetch();
    $updatedAtExists = $columns !== false;
    
    if (!$updatedAtExists) {
        echo "Adding 'updated_at' column to grades table...\n";
        try {
            // Add updated_at column with default and ON UPDATE behavior
            $db->exec("ALTER TABLE grades ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
            echo "✓ Successfully added 'updated_at' column to grades table.\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Note: 'updated_at' column already exists, skipping.\n";
            } else {
                echo "Error adding updated_at column: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    } else {
        echo "Column 'updated_at' already exists in grades table.\n";
    }

    // Check if created_at column exists, if not add it
    $createdAtColumns = $db->query("SHOW COLUMNS FROM grades LIKE 'created_at'")->fetch();
    $createdAtExists = $createdAtColumns !== false;
    
    if (!$createdAtExists) {
        echo "Adding 'created_at' column to grades table...\n";
        try {
            $db->exec("ALTER TABLE grades ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes");
            echo "✓ Successfully added 'created_at' column to grades table.\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Note: 'created_at' column already exists, skipping.\n";
            } else {
                echo "Error adding created_at column: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    } else {
        echo "Column 'created_at' already exists in grades table.\n";
    }

    // Update existing records to set created_at and updated_at if they're NULL
    echo "Updating existing grade records with timestamps...\n";
    
    // Set created_at for records that don't have it (use current date or NOW())
    $affectedCreated = $db->exec("UPDATE grades SET created_at = COALESCE(created_at, NOW()) WHERE created_at IS NULL");
    echo "✓ Updated {$affectedCreated} records with created_at.\n";
    
    // Set updated_at for records that don't have it (use created_at or NOW())
    $affectedUpdated = $db->exec("UPDATE grades SET updated_at = COALESCE(updated_at, created_at, NOW()) WHERE updated_at IS NULL");
    echo "✓ Updated {$affectedUpdated} records with updated_at.\n";

    echo "\n✓ Migration completed successfully!\n";
    echo "The grades table now has created_at and updated_at tracking enabled.\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Note: DDL statements auto-commit, so partial migration may have occurred.\n";
    exit(1);
}

