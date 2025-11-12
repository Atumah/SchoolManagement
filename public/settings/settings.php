<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/totp.php';

requireAuth();

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: /login.php');
    exit;
}

$flash = getFlashMessage();
$activeTab = $_GET['tab'] ?? 'profile';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /settings/settings.php?tab=' . ($_POST['action'] === 'update_profile' ? 'profile' : '2fa'));
        exit;
    }

    if ($_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $updateData = [
            'name' => $currentUser['name'],
            'email' => $currentUser['email'],
            'role' => $currentUser['role'],
            'status' => $currentUser['status'] ?? 'Active'
        ];

        $hasChanges = false;

        // Update first name and last name
        $currentFirstName = $currentUser['first_name'] ?? '';
        $currentLastName = $currentUser['last_name'] ?? '';

        if (!empty($firstName) && $firstName !== $currentFirstName) {
            $updateData['first_name'] = $firstName;
            $hasChanges = true;
        } elseif (isset($currentUser['first_name'])) {
            $updateData['first_name'] = $currentUser['first_name'];
        }

        if (!empty($lastName) && $lastName !== $currentLastName) {
            $updateData['last_name'] = $lastName;
            $hasChanges = true;
        } elseif (isset($currentUser['last_name'])) {
            $updateData['last_name'] = $currentUser['last_name'];
        }

        // Update full name based on first and last name
        if ($hasChanges || (!empty($firstName) && !empty($lastName))) {
            $fullName = trim(($firstName ?: $currentFirstName) . ' ' . ($lastName ?: $currentLastName));
            if (!empty($fullName) && $fullName !== $currentUser['name']) {
                $updateData['name'] = $fullName;
                $hasChanges = true;
            }
        }

        // Update password if provided
        if (!empty($password)) {
            if ($password !== $confirmPassword) {
                setFlashMessage('error', 'Passwords do not match.');
                header('Location: /settings/settings.php?tab=profile');
                exit;
            }
            if (strlen($password) < 6 || !preg_match('/[0-9]/', $password)) {
                setFlashMessage('error', 'Password must be at least 6 characters long and contain at least one number.');
                header('Location: /settings/settings.php?tab=profile');
                exit;
            }
            // Hash password with bcrypt
            $updateData['password'] = password_hash($password, PASSWORD_BCRYPT);
            $hasChanges = true;
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowedTypes)) {
                setFlashMessage('error', 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.');
                header('Location: /settings/settings.php?tab=profile');
                exit;
            }

            if ($file['size'] > $maxSize) {
                setFlashMessage('error', 'File size too large. Maximum size is 5MB.');
                header('Location: /settings/settings.php?tab=profile');
                exit;
            }

            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $currentUser['id'] . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Delete old profile picture if exists
            if (!empty($currentUser['profile_picture'])) {
                $oldFile = __DIR__ . '/../uploads/profiles/' . basename($currentUser['profile_picture']);
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $updateData['profile_picture'] = '/uploads/profiles/' . $filename;
                $hasChanges = true;
            } else {
                setFlashMessage('error', 'Failed to upload profile picture. Please try again.');
                header('Location: /settings/settings.php?tab=profile');
                exit;
            }
        }

        if ($hasChanges) {
            if (updateUser($currentUser['id'], $updateData)) {
                $_SESSION['user'] = getUserById($currentUser['id']);
                setFlashMessage('success', 'Profile updated successfully.');
            } else {
                setFlashMessage('error', 'Failed to update profile. Please try again.');
            }
        } else {
            setFlashMessage('info', 'No changes detected.');
        }

        header('Location: /settings/settings.php?tab=profile');
        exit;
    }
}

// Handle 2FA activation (only if not already handled by profile update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'update_profile') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: /settings/settings.php?tab=2fa');
        exit;
    }

    if ($_POST['action'] === 'enable_2fa') {
        // Generate new secret
        $secret = generateTOTPSecret();

        // Log the secret being generated
        error_log('enable_2fa - Generated secret: [' . $secret . '] (length: ' . strlen($secret) . ')');

        // Only update the 2FA fields
        $updateData = [
            'twofa_secret' => $secret,
            'twofa_enabled' => false // Not enabled until verified
        ];

        // Log the update data
        error_log('enable_2fa - Update data: ' . print_r($updateData, true));
        error_log('enable_2fa - User ID: ' . $currentUser['id']);

        $updateResult = updateUser($currentUser['id'], $updateData);

        // Log the result
        error_log('enable_2fa - updateUser returned: ' . ($updateResult ? 'true' : 'false'));

        if ($updateResult) {
            // Small delay to ensure database write is complete
            usleep(100000); // 0.1 seconds

            // Verify the secret was saved - refresh user data from database (force fresh fetch)
            $updatedUser = getUserById($currentUser['id'], true);
            $savedSecret = trim($updatedUser['twofa_secret'] ?? '');
            $expectedSecret = trim($secret);

            if ($updatedUser && !empty($savedSecret) && $savedSecret === $expectedSecret) {
                $_SESSION['user'] = $updatedUser;
                setFlashMessage('success', '2FA secret generated. Please scan the QR code and enter the verification code.');
                header('Location: /settings/settings.php?tab=2fa&verify=1');
                exit;
            } else {
                // Log debug information
                error_log('2FA secret save verification failed. User ID: ' . $currentUser['id']);
                error_log('Expected secret: [' . $expectedSecret . '] (length: ' . strlen($expectedSecret) . ')');
                error_log('Retrieved secret: [' . $savedSecret . '] (length: ' . strlen($savedSecret) . ')');
                error_log('Secret match: ' . ($savedSecret === $expectedSecret ? 'true' : 'false'));
                error_log('Updated user data: ' . print_r($updatedUser, true));
                setFlashMessage('error', 'Failed to save 2FA secret. Please check the error logs for details.');
            }
        } else {
            error_log('updateUser returned false for user ID: ' . $currentUser['id']);
            setFlashMessage('error', 'Failed to update user. Please check the error logs for details.');
        }
    }

    if ($_POST['action'] === 'verify_2fa') {
        $code = trim($_POST['code'] ?? '');
        // Force fresh fetch to ensure we have the latest secret
        $user = getUserById($currentUser['id'], true);

        if (empty($code)) {
            setFlashMessage('error', 'Please enter the verification code');
            header('Location: /settings/settings.php?tab=2fa&verify=1');
            exit;
        } elseif (empty($user['twofa_secret'])) {
            setFlashMessage('error', 'No 2FA secret found. Please generate one first.');
            header('Location: /settings/settings.php?tab=2fa');
            exit;
        } elseif (verifyTOTPCode($user['twofa_secret'], $code)) {
            // Only update the twofa_enabled field
            updateUser($currentUser['id'], [
                'twofa_enabled' => true
            ]);

            // Refresh session with latest data (updateUser already updates session, but ensure consistency)
            $_SESSION['user'] = getUserById($currentUser['id'], true);
            setFlashMessage('success', '2FA has been successfully enabled!');
            header('Location: /settings/settings.php?tab=2fa');
            exit;
        } else {
            setFlashMessage('error', 'Invalid verification code. Please check your authenticator app and try again.');
            header('Location: /settings/settings.php?tab=2fa&verify=1');
            exit;
        }
    }

    if ($_POST['action'] === 'disable_2fa') {
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            setFlashMessage('error', 'Password is required to disable 2FA.');
            header('Location: /settings/settings.php?tab=2fa');
            exit;
        }

        // Get fresh user data to verify password
        $user = getUserById($currentUser['id'], true);
        if (!$user) {
            setFlashMessage('error', 'User not found.');
            header('Location: /settings/settings.php?tab=2fa');
            exit;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            setFlashMessage('error', 'Invalid password. Please try again.');
            header('Location: /settings/settings.php?tab=2fa');
            exit;
        }

        // Password verified - disable 2FA
        updateUser($currentUser['id'], [
            'twofa_secret' => null,
            'twofa_enabled' => false,
            'twofa_last_used_timestep' => null
        ]);

        // Refresh session with latest data (updateUser already updates session, but ensure consistency)
        $_SESSION['user'] = getUserById($currentUser['id'], true);
        setFlashMessage('success', '2FA has been disabled.');
        header('Location: /settings/settings.php?tab=2fa');
        exit;
    }
}

$currentUser = getCurrentUser(); // Refresh to get latest data
// Force refresh from data source to get latest 2FA data
if ($currentUser && isset($currentUser['id'])) {
    $freshUser = getUserById($currentUser['id'], true);
    if ($freshUser) {
        $currentUser = $freshUser;
        $_SESSION['user'] = $currentUser; // Update session
    }
}

$csrfToken = generateCSRFToken();
$showVerify = isset($_GET['verify']) && $_GET['verify'] == '1';
$twofaSecret = $currentUser['twofa_secret'] ?? null;
$twofaEnabled = isset($currentUser['twofa_enabled']) && ($currentUser['twofa_enabled'] === true || $currentUser['twofa_enabled'] === 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/nav.css">
    <link rel="stylesheet" href="/assets/components.css">
    <style>
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .settings-tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .settings-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .settings-tab:hover {
            color: var(--text-primary);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .settings-tab.active {
            color: var(--primary-lighter);
            border-bottom-color: var(--primary-light);
        }
        
        .settings-tab-content {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .settings-tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .twofa-setup {
            text-align: center;
            padding: 2rem;
        }
        
        .qr-code-container {
            display: inline-block;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            margin: 1.5rem 0;
            box-shadow: var(--shadow-lg);
        }
        
        .qr-code-container img {
            display: block;
            max-width: 300px;
            height: auto;
        }
        
        .verification-code-input {
            max-width: 300px;
            margin: 2rem auto;
        }
        
        .verification-code-input input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-family: 'Courier New', monospace;
            padding: 1rem;
        }
        
        .twofa-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(37, 47, 69, 0.9));
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .twofa-status-info h3 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }
        
        .twofa-status-info p {
            margin: 0;
            color: var(--text-muted);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.enabled {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }
        
        .status-badge.disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        
        .instructions {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--primary-light);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin: 1.5rem 0;
        }
        
        .instructions h4 {
            margin-top: 0;
            color: var(--primary-lighter);
        }
        
        .instructions ol {
            margin: 1rem 0 0 0;
            padding-left: 1.5rem;
            color: var(--text-secondary);
        }
        
        .instructions li {
            margin-bottom: 0.5rem;
        }
        
        /* Profile Tab Styles */
        .profile-form {
            max-width: 800px;
        }
        
        .profile-picture-section {
            display: flex;
            align-items: flex-start;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-picture-container {
            flex-shrink: 0;
        }
        
        .profile-picture-preview,
        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
            box-shadow: var(--shadow-lg);
        }
        
        .profile-picture-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.1));
            border: 3px solid var(--primary-light);
        }
        
        .placeholder-icon {
            font-size: 4rem;
            opacity: 0.7;
        }
        
        .profile-picture-section .form-group {
            flex: 1;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group input[disabled] {
            background: rgba(30, 41, 59, 0.5);
            color: var(--text-muted);
            cursor: not-allowed;
            border-color: var(--border-color);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .password-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .password-section h3 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .form-help {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>
    <main class="container">
        <?php include __DIR__ . '/../includes/back-button.php'; ?>
        <div class="page-header">
            <h1>Settings</h1>
        </div>
        
        <?php if ($flash) : ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-container">
            <div class="settings-tabs">
                <button class="settings-tab <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">
                    Profile
                </button>
                <button class="settings-tab <?= $activeTab === '2fa' ? 'active' : '' ?>" onclick="switchTab('2fa')">
                    Two-Factor Authentication
                </button>
            </div>
            
            <!-- Profile Tab -->
            <div class="settings-tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>" id="tab-profile">
                <div class="card">
                    <h2>Profile Information</h2>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="profile-picture-section">
                            <div class="profile-picture-container">
                                <?php
                                $profilePic = $currentUser['profile_picture'] ?? null;
                                if ($profilePic && file_exists(__DIR__ . '/..' . $profilePic)) :
                                    ?>
                                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="profile-picture-preview" id="profile-preview">
                                <?php else : ?>
                                    <div class="profile-picture-placeholder" id="profile-preview">
                                        <span class="placeholder-icon">👤</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="profile_picture">Profile Picture</label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewProfilePicture(this)">
                                <small>Upload a JPEG, PNG, GIF, or WebP image (max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email-display">Email</label>
                                <input type="text" id="email-display" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled>
                                <small>Email cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="role-display">Role</label>
                                <input type="text" id="role-display" value="<?= htmlspecialchars($currentUser['role']) ?>" disabled>
                                <small>Role cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="password-section">
                            <h3>Change Password</h3>
                            <p class="form-help">Leave blank if you don't want to change your password</p>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">New Password</label>
                                    <input type="password" id="password" name="password" minlength="6" pattern=".*[0-9].*" placeholder="At least 6 chars & 1 number">
                                    <small>Must be at least 6 characters with 1 number</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- 2FA Tab -->
            <div class="settings-tab-content <?= $activeTab === '2fa' ? 'active' : '' ?>" id="tab-2fa">
                <div class="twofa-status">
                    <div class="twofa-status-info">
                        <h3>Two-Factor Authentication</h3>
                        <p>Add an extra layer of security to your account</p>
                    </div>
                    <span class="status-badge <?= $twofaEnabled ? 'enabled' : 'disabled' ?>">
                        <?= $twofaEnabled ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                
                <?php if ($twofaEnabled) : ?>
                    <!-- 2FA Enabled State -->
                    <div class="card">
                        <h2>2FA is Active</h2>
                        <p>Your account is protected with two-factor authentication. You'll be required to enter a code from your authenticator app when logging in.</p>
                        
                        <form method="POST" action="" style="margin-top: 2rem;">
                            <input type="hidden" name="action" value="disable_2fa">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="form-group" style="max-width: 400px; margin-bottom: 1.5rem;">
                                <label for="disable_2fa_password">Enter your password to disable 2FA</label>
                                <input 
                                    type="password" 
                                    id="disable_2fa_password" 
                                    name="password" 
                                    required 
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    style="width: 100%;"
                                >
                                <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">
                                    For security reasons, you must verify your password to disable 2FA.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.')">
                                Disable 2FA
                            </button>
                        </form>
                    </div>
                <?php elseif ($showVerify) : ?>
                    <!-- Verification Step -->
                    <?php if (empty($twofaSecret)) : ?>
                        <div class="card">
                            <div class="alert alert-error">
                                <p><strong>Error:</strong> No 2FA secret found. The secret may not have been saved properly.</p>
                                <p style="margin-top: 0.5rem; font-size: 0.9rem;">Debug info: User ID <?= htmlspecialchars((string)($currentUser['id'] ?? 'unknown')) ?>, Secret: <?= var_export($twofaSecret, true) ?></p>
                                <form method="POST" action="" style="margin-top: 1rem;">
                                    <input type="hidden" name="action" value="enable_2fa">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn btn-primary">Generate New Secret</button>
                                </form>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="card">
                            <h2>Verify 2FA Setup</h2>
                            <p>Scan the QR code with your authenticator app, then enter the 6-digit code to complete setup.</p>
                            
                            <div class="twofa-setup">
                                <div class="qr-code-container">
                                    <?php
                                    $qrUrl = generateQRCodeURL($twofaSecret, $currentUser['email']);
                                    ?>
                                    <img src="<?= htmlspecialchars($qrUrl) ?>" alt="2FA QR Code" style="max-width: 100%; height: auto; display: block; border: 2px solid rgba(59, 130, 246, 0.3); border-radius: var(--radius-md);">
                                </div>
                                
                                <div style="margin: 1.5rem 0; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-md); border-left: 4px solid var(--primary-light);">
                                    <p style="margin: 0; color: var(--text-secondary);"><strong>Can't scan?</strong> Enter this code manually: <code style="background: rgba(0,0,0,0.3); padding: 0.25rem 0.5rem; border-radius: 4px; font-family: 'Courier New', monospace; color: var(--primary-lighter);"><?= htmlspecialchars($twofaSecret) ?></code></p>
                                </div>
                            
                            <div class="instructions">
                                <h4>Setup Instructions:</h4>
                                <ol>
                                    <li>Open your authenticator app (Google Authenticator, Authy, etc.)</li>
                                    <li>Scan the QR code above</li>
                                    <li>Enter the 6-digit code shown in your app below</li>
                                </ol>
                            </div>
                            
                            <form method="POST" action="" class="verification-code-input">
                                <input type="hidden" name="action" value="verify_2fa">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                
                                <div class="form-group">
                                    <label for="verify-code">Enter Verification Code</label>
                                    <input 
                                        type="text" 
                                        id="verify-code" 
                                        name="code" 
                                        required 
                                        maxlength="6" 
                                        pattern="[0-9]{6}"
                                        placeholder="000000"
                                        autocomplete="off"
                                        autofocus
                                        style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; font-family: 'Courier New', monospace;"
                                    >
                                    <small>Enter the 6-digit code from your authenticator app</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                                    Verify and Enable 2FA
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else : ?>
                    <!-- Setup 2FA -->
                    <div class="card">
                        <h2>Enable Two-Factor Authentication</h2>
                        <p>Protect your account by requiring a code from your mobile device in addition to your password.</p>
                        
                        <div class="instructions">
                            <h4>How it works:</h4>
                            <ol>
                                <li>Click "Enable 2FA" to generate a QR code</li>
                                <li>Scan the QR code with an authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.)</li>
                                <li>Enter the verification code to complete setup</li>
                                <li>You'll be asked for a code every time you log in</li>
                            </ol>
                        </div>
                        
                        <form method="POST" action="" style="margin-top: 2rem;">
                            <input type="hidden" name="action" value="enable_2fa">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="btn btn-primary">
                                Enable 2FA
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
    function switchTab(tab) {
        // Update URL
        const newUrl = window.location.pathname + '?tab=' + tab;
        window.history.pushState({}, '', newUrl);
        
        // Get current active content
        const currentActiveContent = document.querySelector('.settings-tab-content.active');
        const newActiveContent = document.getElementById('tab-' + tab);
        
        // If switching to the same tab, do nothing
        if (currentActiveContent === newActiveContent) {
            return;
        }
        
        // Update active tab buttons
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        // Fade out current content, then fade in new content
        if (currentActiveContent) {
            currentActiveContent.style.opacity = '0';
            currentActiveContent.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                currentActiveContent.classList.remove('active');
                currentActiveContent.style.display = 'none';
                
                // Show and animate new content
                newActiveContent.style.display = 'block';
                newActiveContent.classList.add('active');
                
                // Force reflow to ensure display change is applied
                newActiveContent.offsetHeight;
                
                // Trigger animation
                setTimeout(() => {
                    newActiveContent.style.opacity = '1';
                    newActiveContent.style.transform = 'translateY(0)';
                }, 10);
            }, 150);
        } else {
            // No current active content, just show new one
            newActiveContent.style.display = 'block';
            newActiveContent.classList.add('active');
            setTimeout(() => {
                newActiveContent.style.opacity = '1';
                newActiveContent.style.transform = 'translateY(0)';
            }, 10);
        }
    }
    
    // Auto-format verification code input
    const verifyCodeInput = document.getElementById('verify-code');
    if (verifyCodeInput) {
        verifyCodeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        verifyCodeInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const numbers = pasted.replace(/[^0-9]/g, '').substring(0, 6);
            this.value = numbers;
            if (numbers.length === 6) {
                this.form.submit();
            }
        });
    }
    
    // Profile picture preview
    function previewProfilePicture(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('profile-preview');
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    // Replace placeholder with image
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Picture';
                    img.className = 'profile-picture-preview';
                    img.id = 'profile-preview';
                    preview.parentNode.replaceChild(img, preview);
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Initialize active tab on page load
    document.addEventListener('DOMContentLoaded', function() {
        const activeContent = document.querySelector('.settings-tab-content.active');
        if (activeContent) {
            activeContent.style.opacity = '1';
            activeContent.style.transform = 'translateY(0)';
        }
    });
    </script>
</body>
</html>

