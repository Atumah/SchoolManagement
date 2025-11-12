<?php

declare(strict_types=1);

/**
 * Migration script to hash existing plain-text passwords
 * Usage: docker-compose exec app php scripts/migrate_passwords.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

try {
    $db = Database::connection();
    
    echo "Starting password migration...\n\n";
    
    $stmt = $db->query('SELECT id, password FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "No users found in database.\n";
        exit(0);
    }
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($users as $user) {
        // Check if password is already hashed (bcrypt hashes start with $2y$)
        if (str_starts_with($user['password'], '$2y$') || 
            str_starts_with($user['password'], '$2a$') || 
            str_starts_with($user['password'], '$2b$')) {
            echo "Skipping user ID {$user['id']} - password already hashed\n";
            $skipped++;
            continue;
        }
        
        // Hash the plain-text password
        $hashed = password_hash($user['password'], PASSWORD_BCRYPT);
        
        $updateStmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $updateStmt->execute([$hashed, $user['id']]);
        
        echo "✓ Updated password for user ID: {$user['id']}\n";
        $updated++;
    }
    
    echo "\n";
    echo "Migration complete!\n";
    echo "  Updated: {$updated} users\n";
    echo "  Skipped: {$skipped} users (already hashed)\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

