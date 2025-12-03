<?php

declare(strict_types=1);

/**
 * Active Directory Authentication Functions
 * 
 * This module provides LDAP/AD authentication for the web application.
 * It authenticates against Active Directory and syncs user data to the database.
 */

/**
 * Authenticate user against Active Directory
 * 
 * @param string $username Username (can be email, username, or UPN)
 * @param string $password Password
 * @return array|null User data array if authenticated, null otherwise
 */
function authenticateWithAD(string $username, string $password): ?array
{
    // AD Configuration
    $adServer = '192.168.1.10'; // Windows Server IP
    $adDomain = 'morningstar.local';
    $adBaseDN = 'DC=morningstar,DC=local';
    
    // Check if LDAP extension is available
    if (!function_exists('ldap_connect')) {
        error_log('LDAP extension not available. Install php-ldap extension.');
        return null;
    }
    
    // Connect to AD
    $ldap = @ldap_connect("ldap://{$adServer}:389");
    if (!$ldap) {
        error_log('Failed to connect to AD server');
        return null;
    }
    
    // Set LDAP options
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    
    // Try different username formats
    $bindDNs = [
        "{$username}@{$adDomain}",           // UPN format
        "{$adDomain}\\{$username}",         // Domain\username format
        "CN={$username},{$adBaseDN}",      // Distinguished name (if username is CN)
    ];
    
    // Also try email format if username looks like email
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $emailParts = explode('@', $username);
        $bindDNs[] = "{$emailParts[0]}@{$adDomain}";
        $bindDNs[] = "{$adDomain}\\{$emailParts[0]}";
    }
    
    $authenticated = false;
    $userDN = null;
    
    // Try to bind with each format
    foreach ($bindDNs as $bindDN) {
        $bind = @ldap_bind($ldap, $bindDN, $password);
        if ($bind) {
            $authenticated = true;
            $userDN = $bindDN;
            break;
        }
    }
    
    if (!$authenticated) {
        ldap_close($ldap);
        return null;
    }
    
    // Search for user in AD
    $searchFilter = "(|(sAMAccountName={$username})(userPrincipalName={$username}@{$adDomain})(mail={$username}))";
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $emailParts = explode('@', $username);
        $searchFilter = "(|(sAMAccountName={$emailParts[0]})(userPrincipalName={$username})(mail={$username}))";
    }
    
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
        ldap_close($ldap);
        return null;
    }
    
    $entries = @ldap_get_entries($ldap, $search);
    ldap_close($ldap);
    
    if (!$entries || $entries['count'] === 0) {
        return null;
    }
    
    $adUser = $entries[0];
    
    // Extract user information
    $samAccountName = $adUser['samaccountname'][0] ?? $username;
    $email = $adUser['mail'][0] ?? ($adUser['userprincipalname'][0] ?? "{$samAccountName}@{$adDomain}");
    $displayName = $adUser['displayname'][0] ?? ($adUser['givenname'][0] . ' ' . $adUser['sn'][0]);
    $firstName = $adUser['givenname'][0] ?? '';
    $lastName = $adUser['sn'][0] ?? '';
    
    // Determine role from AD groups
    $role = determineRoleFromADGroups($adUser['memberof'] ?? []);
    
    return [
        'username' => $samAccountName,
        'email' => $email,
        'name' => $displayName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'role' => $role,
        'ad_groups' => $adUser['memberof'] ?? [],
        'ad_dn' => $adUser['distinguishedname'][0] ?? null
    ];
}

/**
 * Determine web application role from AD group membership
 * 
 * @param array $adGroups Array of AD group distinguished names
 * @return string Role name
 */
function determineRoleFromADGroups(array $adGroups): string
{
    // Map AD groups to web app roles
    $groupRoleMap = [
        'CN=Teachers,' => 'Teacher',
        'CN=Administration,' => 'Admin',
        'CN=Principal,' => 'Principal',
        'CN=WebDesigner,' => 'Web Designer',
        'CN=Students,' => 'Student', // If students are added later
    ];
    
    foreach ($adGroups as $group) {
        if (is_array($group)) {
            continue;
        }
        foreach ($groupRoleMap as $groupPattern => $role) {
            if (stripos($group, $groupPattern) !== false) {
                return $role;
            }
        }
    }
    
    // Default role if no match
    return 'Student';
}

/**
 * Sync AD user to database
 * Creates or updates user record in database based on AD data
 * 
 * @param array $adUserData User data from AD
 * @return string|null User ID (CUID) from database, or null on failure
 */
function syncADUserToDatabase(array $adUserData): ?string
{
    require_once __DIR__ . '/data.php';
    
    try {
        // Check if user exists by email or username
        $existingUser = getUserByEmail($adUserData['email']);
        if (!$existingUser) {
            $existingUser = getUserByUsername($adUserData['username']);
        }
        
        if ($existingUser) {
            // Update existing user
            $updateData = [
                'username' => $adUserData['username'],
                'email' => $adUserData['email'],
                'name' => $adUserData['name'],
                'first_name' => $adUserData['first_name'] ?? null,
                'last_name' => $adUserData['last_name'] ?? null,
                'role' => $adUserData['role'],
                'status' => 'Active'
            ];
            
            updateUser($existingUser['id'], $updateData);
            return $existingUser['id'];
        } else {
            // Create new user (password will be empty since AD handles auth)
            $newUserData = [
                'username' => $adUserData['username'],
                'email' => $adUserData['email'],
                'password' => bin2hex(random_bytes(32)), // Random password, won't be used
                'name' => $adUserData['name'],
                'first_name' => $adUserData['first_name'] ?? null,
                'last_name' => $adUserData['last_name'] ?? null,
                'role' => $adUserData['role'],
                'status' => 'Active'
            ];
            
            $userId = addUser($newUserData);
            
            // Clear password field since AD handles authentication
            // We'll mark this user as AD-authenticated
            $db = getDb();
            $stmt = $db->prepare('UPDATE users SET password = NULL WHERE id = ?');
            $stmt->execute([$userId]);
            
            return $userId;
        }
    } catch (Exception $e) {
        error_log('Error syncing AD user to database: ' . $e->getMessage());
        return null;
    }
}

