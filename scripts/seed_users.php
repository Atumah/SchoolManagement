<?php

declare(strict_types=1);

/**
 * Seed script to create users for all roles
 * 
 * Usage: php scripts/seed_users.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../public/includes/data.php';

echo "Seeding users for all roles...\n\n";

$users = [
    [
        'email' => 'admin@school.com',
        'password' => 'admin123',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'role' => 'Admin',
        'status' => 'Active'
    ],
    [
        'email' => 'principal@school.com',
        'password' => 'principal123',
        'first_name' => 'Principal',
        'last_name' => 'User',
        'role' => 'Principal',
        'status' => 'Active'
    ],
    [
        'email' => 'teacher@school.com',
        'password' => 'teacher123',
        'first_name' => 'Teacher',
        'last_name' => 'User',
        'role' => 'Teacher',
        'status' => 'Active'
    ],
    [
        'email' => 'designer@school.com',
        'password' => 'designer123',
        'first_name' => 'Web',
        'last_name' => 'Designer',
        'role' => 'Web Designer',
        'status' => 'Active'
    ],
    [
        'email' => 'student@school.com',
        'password' => 'student123',
        'first_name' => 'Student',
        'last_name' => 'User',
        'role' => 'Student',
        'status' => 'Active'
    ],
];

foreach ($users as $userData) {
    try {
        // Check if user already exists
        $existing = getUserByEmail($userData['email']);
        if ($existing) {
            echo "User {$userData['email']} already exists, skipping...\n";
            continue;
        }

        $userId = addUser($userData);
        echo "✓ Created {$userData['role']} user: {$userData['email']} (ID: {$userId})\n";
    } catch (Exception $e) {
        echo "✗ Failed to create {$userData['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ User seeding completed!\n";

