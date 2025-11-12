<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getRedirectAfterLogin());
    exit;
}

// Determine active panel from URL parameter (PHP-driven, not JS)
$activePanel = isset($_GET['panel']) && $_GET['panel'] === 'signup' ? 'signup' : 'login';
$error = '';
$success = '';

// Get flash message if any (automatically cleared after reading)
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate email format
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif (login($email, $password)) {
            // Check if 2FA is required (login() sets pending_2fa_user_id if 2FA is enabled)
            if (isset($_SESSION['pending_2fa_user_id'])) {
                header('Location: /login-2fa.php');
                exit;
            }
            
            // No 2FA required, proceed to dashboard
            $redirect = getRedirectAfterLogin();
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'The email or password you entered is incorrect. Please try again.';
        }
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
        $activePanel = 'signup';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        // Validate all fields are filled
        if (empty($firstName) || empty($lastName) || empty($password) || empty($email)) {
            $error = 'Please fill in all required fields';
            $activePanel = 'signup';
        } 
        // Validate email format
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
            $activePanel = 'signup';
        }
        // Validate password length and contains number
        elseif (strlen($password) < 6 || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must be at least 6 characters long and contain at least one number';
            $activePanel = 'signup';
        }
        // Validate passwords match
        elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please try again.';
            $activePanel = 'signup';
        }
        // Check if email already exists
        elseif (getUserByEmail($email) !== null) {
            $error = 'An account with this email already exists. Please use a different email or sign in.';
            $activePanel = 'signup';
        } else {
            try {
                addUser([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => $password,
                    'email' => $email,
                    'name' => $firstName . ' ' . $lastName,
                    'role' => 'Student',
                    'status' => 'Active'
                ]);
                // Redirect to login panel with success message (PHP-driven)
                setFlashMessage('success', 'Account created successfully. Please login.');
                header('Location: /login.php?panel=login');
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
                $activePanel = 'signup';
            }
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
    <title>Login - Morning Star School</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <div class="auth-panel-container <?= $activePanel === 'signup' ? 'slide-right' : '' ?>" id="panel-container">
                <!-- Login Panel -->
                <div class="auth-panel login-panel">
                    <div class="auth-panel-content">
                        <div class="auth-panel-header">
                            <div class="logo-container">
                                <img src="/assets/logo-placeholder.svg" alt="Morning Star" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <span style="display: none; font-size: 3rem;">⭐</span>
                            </div>
                            <h1>Welcome Back</h1>
                            <p>Sign in to continue to Morning Star</p>
                        </div>
                        
                        <?php if ($error && $activePanel === 'login'): ?>
                            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="?panel=login" class="auth-form">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="form-group">
                                <label for="login-email">Email</label>
                                <input type="email" id="login-email" name="email" required autofocus placeholder="Enter your email">
                            </div>
                            
                            <div class="form-group">
                                <label for="login-password">Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="login-password" name="password" required placeholder="Enter your password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('login-password', this)" aria-label="Show password">
                                        <span class="password-toggle-icon">👁️</span>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="auth-btn">Sign In</button>
                        </form>
                        
                                <a href="?panel=signup" class="switch-panel-btn">
                                    Don't have an account? Sign Up →
                                </a>
                    </div>
                </div>
                
                <!-- Signup Panel -->
                <div class="auth-panel signup-panel">
                    <div class="auth-panel-content">
                        <div class="auth-panel-header">
                            <div class="logo-container">
                                <img src="/assets/logo-placeholder.svg" alt="Morning Star" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <span style="display: none; font-size: 3rem;">⭐</span>
                            </div>
                            <h1>Create Account</h1>
                            <p>Join Morning Star School today</p>
                        </div>
                        
                        <?php if ($error && $activePanel === 'signup'): ?>
                            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="?panel=signup" class="auth-form">
                            <input type="hidden" name="action" value="signup">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="signup-first-name">First Name</label>
                                    <input type="text" id="signup-first-name" name="first_name" required placeholder="First Name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="signup-last-name">Last Name</label>
                                    <input type="text" id="signup-last-name" name="last_name" required placeholder="Last Name">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="signup-password">Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="signup-password" name="password" required minlength="6" pattern=".*[0-9].*" placeholder="At least 6 chars & 1 number" title="Password must be at least 6 characters and contain at least one number">
                                        <button type="button" class="password-toggle" onclick="togglePassword('signup-password', this)" aria-label="Show password">
                                            <span class="password-toggle-icon">👁️</span>
                                        </button>
                                    </div>
                                    <small>Must be at least 6 characters with 1 number</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="signup-confirm-password">Confirm Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="signup-confirm-password" name="confirm_password" required placeholder="Re-enter password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('signup-confirm-password', this)" aria-label="Show password">
                                            <span class="password-toggle-icon">👁️</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="signup-email">Email</label>
                                <input type="email" id="signup-email" name="email" required placeholder="your.email@school.com">
                            </div>
                            
                            <button type="submit" class="auth-btn">Create Account</button>
                        </form>
                        
                                <a href="?panel=login" class="switch-panel-btn">
                                    ← Already have an account? Sign In
                                </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // UI-only JavaScript - All backend logic is handled by PHP
    
    // 1. Panel animation based on PHP-determined state
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('panel-container');
        if (!container) return;
        
        // Set initial state based on PHP-determined panel (from URL parameter)
        const urlParams = new URLSearchParams(window.location.search);
        const panel = urlParams.get('panel');
        
        if (panel === 'signup') {
            container.classList.add('slide-right');
        } else {
            container.classList.remove('slide-right');
        }
        
        // Smooth animation when clicking switch buttons (UI enhancement)
        document.querySelectorAll('.switch-panel-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent immediate navigation
                
                const targetPanel = this.getAttribute('href');
                const isGoingToSignup = targetPanel && targetPanel.includes('panel=signup');
                
                // Trigger smooth animation
                if (isGoingToSignup) {
                    container.classList.add('slide-right');
                } else {
                    container.classList.remove('slide-right');
                }
                
                // Wait for animation to complete using transitionend event
                const handleTransitionEnd = (event) => {
                    // Only proceed if this is the transform transition on the container
                    if (event.target === container && event.propertyName === 'transform') {
                        container.removeEventListener('transitionend', handleTransitionEnd);
                        window.location.href = targetPanel || '?panel=login';
                    }
                };
                
                container.addEventListener('transitionend', handleTransitionEnd);
                
                // Fallback timeout in case transitionend doesn't fire (shouldn't happen, but safety net)
                setTimeout(() => {
                    container.removeEventListener('transitionend', handleTransitionEnd);
                    window.location.href = targetPanel || '?panel=login';
                }, 1200); // Generous timeout as fallback
            });
        });
        
        // Handle browser back/forward buttons (UI only - PHP handles actual navigation)
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const panel = urlParams.get('panel');
            
            if (!container) return;
            
            if (panel === 'signup') {
                container.classList.add('slide-right');
            } else {
                container.classList.remove('slide-right');
            }
        });
    });
    
    // 2. Password toggle functionality (UI only)
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('.password-toggle-icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = '🙈';
            button.setAttribute('aria-label', 'Hide password');
        } else {
            input.type = 'password';
            icon.textContent = '👁️';
            button.setAttribute('aria-label', 'Show password');
        }
    }
    
    // 3. Frontend validation (UI feedback only - backend validation in PHP)
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const emailInput = form.querySelector('input[type="email"]');
            const passwordInput = form.querySelector('input[type="password"][name="password"]');
            const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');
            
            // Email validation (UI feedback)
            if (emailInput) {
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    emailInput.focus();
                    emailInput.setCustomValidity('Please enter a valid email address');
                    emailInput.reportValidity();
                    return false;
                } else {
                    emailInput.setCustomValidity('');
                }
            }
            
            // Password validation for signup (UI feedback)
            if (passwordInput && form.querySelector('input[name="action"][value="signup"]')) {
                const password = passwordInput.value;
                const passwordRegex = /^(?=.*[0-9]).{6,}$/;
                
                if (!passwordRegex.test(password)) {
                    e.preventDefault();
                    passwordInput.focus();
                    passwordInput.setCustomValidity('Password must be at least 6 characters and contain at least one number');
                    passwordInput.reportValidity();
                    return false;
                } else {
                    passwordInput.setCustomValidity('');
                }
                
                // Confirm password match (UI feedback)
                if (confirmPasswordInput && password !== confirmPasswordInput.value) {
                    e.preventDefault();
                    confirmPasswordInput.focus();
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.reportValidity();
                    return false;
                } else if (confirmPasswordInput) {
                    confirmPasswordInput.setCustomValidity('');
                }
            }
        });
    });
    </script>
</body>
</html>
