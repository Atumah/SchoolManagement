<?php

declare(strict_types=1);

// Start output buffering to prevent headers from being sent prematurely
if (!ob_get_level()) {
    ob_start();
}

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    // Only start session if headers haven't been sent
    if (!headers_sent()) {
        // Configure session settings BEFORE starting the session
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '0'); // Set to 1 in production with HTTPS
        ini_set('session.use_strict_mode', '1');

        // Set cookie parameters (must be called before session_start)
        session_set_cookie_params([
            'httponly' => true,
            'secure' => false, // Set to true in production with HTTPS
            'samesite' => 'Lax'
        ]);

        session_start();

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    } else {
        // Headers already sent - try to start session anyway (may fail silently)
        @session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Get current logged-in user
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    require_once __DIR__ . '/data.php';
    return getUserById($_SESSION['user_id']);
}

/**
 * Require authentication - redirect to login if not logged in
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require specific role - redirect to 403 if wrong role
 */
function requireRole(string $role): void
{
    requireAuth();

    $user = getCurrentUser();
    if ($user === null || $user['role'] !== $role) {
        header('Location: /errors/403.php');
        exit;
    }
}

/**
 * Require one of multiple roles
 */
function requireAnyRole(array $roles): void
{
    requireAuth();

    $user = getCurrentUser();
    if ($user === null || !in_array($user['role'], $roles, true)) {
        header('Location: /errors/403.php');
        exit;
    }
}

/**
 * Check if user has specific role
 */
function hasRole(string $role): bool
{
    $user = getCurrentUser();
    return $user !== null && $user['role'] === $role;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole(array $roles): bool
{
    $user = getCurrentUser();
    return $user !== null && in_array($user['role'], $roles, true);
}

/**
 * Login user with security checks
 */
function login(string $email, string $password): bool
{
    require_once __DIR__ . '/data.php';

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Validate password is not empty
    if (empty($password)) {
        return false;
    }

    $user = getUserByEmail($email);
    if ($user === null) {
        return false;
    }

    // Verify password using bcrypt
    if (!password_verify($password, $user['password'])) {
        return false;
    }

    // Check if account is active
    if ($user['status'] !== 'Active') {
        return false;
    }

    // Check if 2FA is enabled
    $twofaEnabled = isset($user['twofa_enabled']) && ($user['twofa_enabled'] === true || $user['twofa_enabled'] === 1);
    $hasTwofaSecret = !empty($user['twofa_secret']);

    if ($twofaEnabled && $hasTwofaSecret) {
        // 2FA is enabled - require verification before completing login
        // Don't complete login yet, set pending 2FA state
        $_SESSION['pending_2fa_user_id'] = $user['id'];
        $_SESSION['pending_2fa_email'] = $user['email'];
        // Don't set authenticated session yet - wait for 2FA verification
        return true; // Return true to indicate password was correct, but 2FA is required
    }

    // No 2FA required - complete login
    // Regenerate session ID on login for security
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
    $_SESSION['username'] = $user['username'] ?? null;
    $_SESSION['role'] = $user['role'];
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();

    return true;
}

/**
 * Logout user and destroy session
 */
function logout(): void
{
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
    session_start();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

/**
 * Get and clear flash message
 */
function getFlashMessage(): ?array
{
    if (!isset($_SESSION['flash_type']) || !isset($_SESSION['flash_message'])) {
        return null;
    }

    $message = [
        'type' => $_SESSION['flash_type'],
        'message' => $_SESSION['flash_message']
    ];

    unset($_SESSION['flash_type'], $_SESSION['flash_message']);

    return $message;
}

/**
 * Get redirect URL after login
 */
function getRedirectAfterLogin(): string
{
    $redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
    unset($_SESSION['redirect_after_login']);
    return $redirect;
}
