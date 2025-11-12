<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/totp.php';

// Check if user is in 2FA verification flow
if (!isset($_SESSION['pending_2fa_user_id'])) {
    // Not in 2FA flow, redirect to login
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['pending_2fa_user_id'];
$user = getUserById($userId);
$error = '';

if (!$user) {
    // User not found, clear session and redirect to login
    session_destroy();
    header('Location: /login.php');
    exit;
}

// Verify user has 2FA enabled (security check)
$twofaEnabled = isset($user['twofa_enabled']) && ($user['twofa_enabled'] === true || $user['twofa_enabled'] === 1);
if (!$twofaEnabled || empty($user['twofa_secret'])) {
    // 2FA not properly configured, clear session and redirect
    session_destroy();
    header('Location: /login.php');
    exit;
}

// Handle 2FA verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            $error = 'Please enter the verification code';
        } elseif (empty($user['twofa_secret'])) {
            $error = '2FA is not properly configured. Please contact support.';
        } elseif (verifyTOTPCodeWithReplayProtection($user['id'], $user['twofa_secret'], $code)) {
            // 2FA verified, complete login
            // Clear pending 2FA data
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_email']);

            // Regenerate session ID for security after successful 2FA
            session_regenerate_id(true);

            // Set authenticated session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            $_SESSION['username'] = $user['username'] ?? null;
            $_SESSION['role'] = $user['role'];
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();

            $redirect = getRedirectAfterLogin();
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
    <?php include __DIR__ . '/includes/back-button.php'; ?>
    <div class="auth-container">
        <div class="auth-wrapper">
            <div class="auth-panel login-panel" style="width: 100%; max-width: 500px; margin: 0 auto;">
                <div class="auth-panel-content">
                    <div class="auth-panel-header">
                        <div class="logo-container">
                            <img src="/assets/logo-placeholder.svg" alt="Morning Star" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span style="display: none; font-size: 3rem;">⭐</span>
                        </div>
                        <h1>Two-Factor Authentication</h1>
                        <p>Enter the code from your authenticator app</p>
                    </div>
                    
                    <?php if ($error) : ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="form-group">
                            <label for="2fa-code">Verification Code</label>
                            <input 
                                type="text" 
                                id="2fa-code" 
                                name="code" 
                                required 
                                maxlength="6" 
                                pattern="[0-9]{6}"
                                placeholder="000000"
                                autocomplete="off"
                                autofocus
                                style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; font-family: 'Courier New', monospace; padding: 1rem;"
                            >
                            <small>Enter the 6-digit code from your authenticator app</small>
                        </div>
                        
                        <button type="submit" class="auth-btn">Verify</button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="/logout.php" style="color: var(--text-muted); font-size: 0.9rem;">Cancel and return to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-format and auto-submit verification code
    const codeInput = document.getElementById('2fa-code');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        codeInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const numbers = pasted.replace(/[^0-9]/g, '').substring(0, 6);
            this.value = numbers;
            if (numbers.length === 6) {
                this.form.submit();
            }
        });
    }
    </script>
</body>
</html>

