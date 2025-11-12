<?php

declare(strict_types=1);

/**
 * Script to create an Admin account in the database
 * Usage: php scripts/create_admin.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Config\DatabaseConfig;

// Prevent session start in CLI
if (session_status() === PHP_SESSION_NONE) {
    // Don't start session in CLI mode
}

try {
    $db = Database::connection();
    
    // Default admin credentials
    $adminData = [
        'username' => 'admin',
        'email' => 'admin@morningstar.edu',
        'password' => 'admin123', // Change this password after first login!
        'name' => 'System Administrator',
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'role' => 'Admin',
        'status' => 'Active'
    ];
    
    // Check if admin already exists
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$adminData['email'], $adminData['username']]);
    $existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAdmin) {
        echo "Admin account already exists!\n";
        echo "  ID: {$existingAdmin['id']}\n";
        echo "  Username: {$existingAdmin['username']}\n";
        echo "  Email: {$existingAdmin['email']}\n";
        echo "  Role: {$existingAdmin['role']}\n";
        exit(0);
    }
    
    // Validate email format
    if (!filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }
    
    // Validate password: at least 6 characters and contains at least one number
    if (strlen($adminData['password']) < 6 || !preg_match('/[0-9]/', $adminData['password'])) {
        throw new InvalidArgumentException('Password must be at least 6 characters and contain at least one number');
    }
    
    // Hash password with bcrypt
    $hashedPassword = password_hash($adminData['password'], PASSWORD_BCRYPT);
    
    // Insert admin account
    $stmt = $db->prepare('
        INSERT INTO users (username, password, email, name, first_name, last_name, role, status, twofa_secret, twofa_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $adminData['username'],
        $hashedPassword,
        $adminData['email'],
        $adminData['name'],
        $adminData['first_name'],
        $adminData['last_name'],
        $adminData['role'],
        $adminData['status'],
        null, // twofa_secret
        0     // twofa_enabled (0 = false, 1 = true)
    ]);
    
    $adminId = (int)$db->lastInsertId();
    
    echo "✓ Admin account created successfully!\n";
    echo "\n";
    echo "Account Details:\n";
    echo "  ID: {$adminId}\n";
    echo "  Username: {$adminData['username']}\n";
    echo "  Email: {$adminData['email']}\n";
    echo "  Password: {$adminData['password']}\n";
    echo "  Role: {$adminData['role']}\n";
    echo "\n";
    echo "⚠️  IMPORTANT: Please change the password after first login!\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Make sure:\n";
    echo "  1. Docker containers are running (docker-compose up -d)\n";
    echo "  2. Database connection settings in .env are correct\n";
    echo "  3. Database tables are created (run database_schema.sql)\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error creating admin account: " . $e->getMessage() . "\n";
    exit(1);
}

