<?php

/**
 * Sync Active Directory Users to Database
 * 
 * This script syncs all AD users to the database, creating or updating records.
 * Run this after AD setup or when AD users change.
 * 
 * Usage: php scripts/sync_ad_users.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/includes/ad_auth.php';
require_once __DIR__ . '/../public/includes/data.php';

use App\Database\Database;

// AD Configuration
$adServer = '192.168.1.10';
$adDomain = 'morningstar.local';
$adBaseDN = 'DC=morningstar,DC=local';

echo "=== Syncing AD Users to Database ===\n\n";

// Check if LDAP extension is available
if (!function_exists('ldap_connect')) {
    echo "ERROR: LDAP extension not available.\n";
    echo "On Windows Server, enable php_ldap extension in php.ini:\n";
    echo "  extension=ldap\n";
    exit(1);
}

// Connect to AD
echo "Connecting to AD server: {$adServer}...\n";
$ldap = @ldap_connect("ldap://{$adServer}:389");
if (!$ldap) {
    echo "ERROR: Failed to connect to AD server\n";
    exit(1);
}

// Set LDAP options
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

// Bind as administrator (or use service account)
// For initial sync, you may need admin credentials
echo "Binding to AD...\n";

// Try to get password from environment or prompt
$bindPassword = getenv('AD_ADMIN_PASSWORD');
if (!$bindPassword) {
    // Try common passwords or prompt
    echo "Enter Administrator password (or press Enter to try default): ";
    $bindPassword = trim(fgets(STDIN));
    if (empty($bindPassword)) {
        $bindPassword = 'Morningstar1'; // Default password
    }
}

// Try multiple bind formats
$bindMethods = [
    "Administrator@{$adDomain}",                    // UPN format
    "{$adDomain}\\Administrator",                   // Domain\username format
    "CN=Administrator,CN=Users,{$adBaseDN}",        // Distinguished name
];

$bind = false;
foreach ($bindMethods as $bindDN) {
    $bind = @ldap_bind($ldap, $bindDN, $bindPassword);
    if ($bind) {
        echo "Bound successfully using: $bindDN\n";
        break;
    }
}

if (!$bind) {
    echo "ERROR: Failed to bind to AD with any method.\n";
    echo "Tried: " . implode(", ", $bindMethods) . "\n";
    echo "Please check:\n";
    echo "1. Administrator password is correct\n";
    echo "2. AD server is accessible\n";
    echo "3. LDAP ports are open (Step 2.11)\n";
    ldap_close($ldap);
    exit(1);
}

echo "Connected successfully!\n\n";

// Search for all users
echo "Searching for users in AD...\n";
$searchFilter = '(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))'; // Active users only
$search = @ldap_search($ldap, $adBaseDN, $searchFilter, [
    'sAMAccountName',
    'mail',
    'displayName',
    'givenName',
    'sn',
    'memberOf',
    'userPrincipalName',
    'distinguishedName'
]);

if (!$search) {
    echo "ERROR: Failed to search AD\n";
    ldap_close($ldap);
    exit(1);
}

$entries = @ldap_get_entries($ldap, $search);
ldap_close($ldap);

if (!$entries || $entries['count'] === 0) {
    echo "No users found in AD\n";
    exit(0);
}

echo "Found {$entries['count']} users in AD\n\n";

$synced = 0;
$updated = 0;
$errors = 0;

// Process each user
foreach ($entries as $index => $adUser) {
    if ($index === 'count') {
        continue;
    }
    
    $samAccountName = $adUser['samaccountname'][0] ?? null;
    if (!$samAccountName) {
        continue;
    }
    
    $email = $adUser['mail'][0] ?? ($adUser['userprincipalname'][0] ?? "{$samAccountName}@{$adDomain}");
    $displayName = $adUser['displayname'][0] ?? ($adUser['givenname'][0] . ' ' . ($adUser['sn'][0] ?? ''));
    $firstName = $adUser['givenname'][0] ?? '';
    $lastName = $adUser['sn'][0] ?? '';
    
    // Determine role from AD groups
    $role = determineRoleFromADGroups($adUser['memberof'] ?? []);
    
    $adUserData = [
        'username' => $samAccountName,
        'email' => $email,
        'name' => $displayName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'role' => $role,
        'ad_groups' => $adUser['memberof'] ?? [],
        'ad_dn' => $adUser['distinguishedname'][0] ?? null
    ];
    
    // Check if user exists
    $existingUser = getUserByEmail($email);
    if (!$existingUser) {
        $existingUser = getUserByUsername($samAccountName);
    }
    
    try {
        if ($existingUser) {
            // Update existing user
            updateUser($existingUser['id'], [
                'username' => $adUserData['username'],
                'email' => $adUserData['email'],
                'name' => $adUserData['name'],
                'first_name' => $adUserData['first_name'],
                'last_name' => $adUserData['last_name'],
                'role' => $adUserData['role'],
                'status' => 'Active'
            ]);
            
            // Clear password if it exists (AD handles auth)
            $db = getDb();
            $stmt = $db->prepare('UPDATE users SET password = NULL WHERE id = ? AND password IS NOT NULL');
            $stmt->execute([$existingUser['id']]);
            
            echo "  ✓ Updated: {$samAccountName} ({$role})\n";
            $updated++;
        } else {
            // Create new user
            $newUserData = [
                'username' => $adUserData['username'],
                'email' => $adUserData['email'],
                'password' => bin2hex(random_bytes(32)), // Temporary, will be cleared
                'name' => $adUserData['name'],
                'first_name' => $adUserData['first_name'],
                'last_name' => $adUserData['last_name'],
                'role' => $adUserData['role'],
                'status' => 'Active'
            ];
            
            $userId = addUser($newUserData);
            
            // Clear password since AD handles authentication
            $db = getDb();
            $stmt = $db->prepare('UPDATE users SET password = NULL WHERE id = ?');
            $stmt->execute([$userId]);
            
            echo "  ✓ Created: {$samAccountName} ({$role})\n";
            $synced++;
        }
    } catch (Exception $e) {
        echo "  ✗ Error syncing {$samAccountName}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n=== Sync Complete ===\n";
echo "Created: {$synced}\n";
echo "Updated: {$updated}\n";
echo "Errors: {$errors}\n";
echo "Total processed: " . ($synced + $updated + $errors) . "\n";

