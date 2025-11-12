<?php

declare(strict_types=1);

/**
 * TOTP (Time-based One-Time Password) implementation for 2FA
 */

/**
 * Generate a random secret key for TOTP
 */
function generateTOTPSecret(): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

/**
 * Base32 decode
 */
function base32Decode(string $secret): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $binary = '';
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        $value = strpos($chars, $char);
        if ($value === false) {
            continue;
        }
        $binary .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }
    
    $result = '';
    for ($i = 0; $i < strlen($binary); $i += 8) {
        $byte = substr($binary, $i, 8);
        if (strlen($byte) === 8) {
            $result .= chr(bindec($byte));
        }
    }
    
    return $result;
}

/**
 * Generate TOTP code from secret
 */
function generateTOTPCode(string $secret, int $timeStep = 30): string
{
    $time = floor(time() / $timeStep);
    $secretBinary = base32Decode($secret);
    
    $timeBinary = pack('N*', 0) . pack('N*', $time);
    $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);
    
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify TOTP code
 */
function verifyTOTPCode(string $secret, string $code, int $timeStep = 30, int $window = 1): bool
{
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    
    // Check current time step and adjacent time steps (for clock skew)
    for ($i = -$window; $i <= $window; $i++) {
        $time = floor(time() / $timeStep) + $i;
        $secretBinary = base32Decode($secret);
        
        $timeBinary = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);
        
        $offset = ord($hash[19]) & 0xf;
        $calculatedCode = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        $calculatedCode = str_pad((string)$calculatedCode, 6, '0', STR_PAD_LEFT);
        
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verify TOTP code with replay protection (one-time use)
 * This function checks if a code has been used before and prevents reuse
 * 
 * @param int $userId User ID to track code usage
 * @param string $secret TOTP secret
 * @param string $code 6-digit code to verify
 * @param int $timeStep Time step in seconds (default 30)
 * @param int $window Clock skew window (default 1)
 * @return bool True if code is valid and hasn't been used before
 */
function verifyTOTPCodeWithReplayProtection(int $userId, string $secret, string $code, int $timeStep = 30, int $window = 1): bool
{
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    
    // Require data.php for database access
    require_once __DIR__ . '/data.php';
    
    // Get current user data to check last used time step
    $user = getUserById($userId, true);
    if (!$user) {
        return false;
    }
    
    $lastUsedTimestep = $user['twofa_last_used_timestep'] ?? null;
    $currentTime = time();
    $currentTimestep = floor($currentTime / $timeStep);
    
    // Check current time step and adjacent time steps (for clock skew)
    for ($i = -$window; $i <= $window; $i++) {
        $time = $currentTimestep + $i;
        
        // Skip if this time step was already used
        if ($lastUsedTimestep !== null && $time <= $lastUsedTimestep) {
            continue;
        }
        
        $secretBinary = base32Decode($secret);
        $timeBinary = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);
        
        $offset = ord($hash[19]) & 0xf;
        $calculatedCode = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        $calculatedCode = str_pad((string)$calculatedCode, 6, '0', STR_PAD_LEFT);
        
        if (hash_equals($calculatedCode, $code)) {
            // Code is valid, update last used time step in database
            // Advance to current timestep + window to immediately invalidate current code
            // This ensures the current code shown in authenticator becomes invalid instantly
            $currentTimestep = floor(time() / $timeStep);
            $advancedTimestep = $currentTimestep + $window;
            
            updateUser($userId, [
                'twofa_last_used_timestep' => $advancedTimestep
            ]);
            return true;
        }
    }
    
    return false;
}

/**
 * Generate QR code URL for Google Authenticator
 */
function generateQRCodeURL(string $secret, string $email, string $issuer = 'Morning Star School'): string
{
    $label = urlencode($email);
    $issuerEncoded = urlencode($issuer);
    $secretEncoded = urlencode($secret);
    
    $otpauth = "otpauth://totp/{$label}?secret={$secretEncoded}&issuer={$issuerEncoded}";
    
    // Use Google Charts API for QR code generation
    return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($otpauth);
}

