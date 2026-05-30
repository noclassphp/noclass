<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function secure_session_start()
{
    // Only start once
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure = is_https();

    // Use Lax by default for login flows; switch to Strict if you really want it.
    // Browsers require Secure cookies when SameSite=None.
    $sameSite = defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax';
    $sameSite = in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';

    if ($sameSite === 'None' && !$secure) {
        $sameSite = 'Lax';
    }

    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure'   => $secure,  // ✅ works on localhost http + production https
        'cookie_httponly' => true,
        'cookie_samesite' => $sameSite,
        'use_strict_mode' => 1,
    ]);

    // Regenerate once to prevent fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

function sanitizeInput($input) {
    // Add your sanitation logic here
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeForSQL($mysqli, $input) {
    return mysqli_real_escape_string($mysqli, $input);
}

function hashPassword($password) {
    // Add your password hashing logic here
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    // Add your password verification logic here
    return password_verify($password, $hash);
}

/*function encode($password)
{
    // Encoding logic
    // Implement your encoding code here
    echo "Encoding password: $password";
}

function decode($password)
{
    // Decoding logic
    // Implement your decoding code here
    echo "Decoding password: $password";
}

function security_encode($password)
{
    // Encoding logic
    // Implement your encoding code here
    echo "Encoding password: $password";
}

function security_decode($password)
{
    // Decoding logic
    // Implement your decoding code here
    echo "Decoding password: $password";
}*/

function security_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function security_verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// 6. CSRF Protection
// Updated CSRF protection implementation
/*function validateCsrfToken()
{
    // Check if CSRF token exists in the request
    if (!isset($_POST['csrf_token'])) {
        return false;
    }

    $csrfToken = $_POST['csrf_token'];

    // Validate the CSRF token against the token stored in the session
    if (isset($_SESSION['csrf_token']) && $csrfToken === $_SESSION['csrf_token']) {
        // Check if token has expired
        if (isset($_SESSION['csrf_token_expiry']) && $_SESSION['csrf_token_expiry'] >= time()) {
            // CSRF token is valid and not expired, proceed with the request

            // Refresh CSRF token for the next request
            generateCsrfToken();

            return true;
        }
    }

    // Invalid CSRF token
    return false;
}*/

// ---------------- CSRF ----------------

function csrf_token(int $ttlSeconds = 900)
{
    if (session_status() !== PHP_SESSION_ACTIVE) secure_session_start();

    $now = time();

    // If missing/expired, generate new
    if (
        empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expiry']) ||
        $_SESSION['csrf_token_expiry'] < $now
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = $now + $ttlSeconds;
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $t = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

function csrf_verify(string $tokenFromRequest)
{
    if (session_status() !== PHP_SESSION_ACTIVE) secure_session_start();

    if (empty($tokenFromRequest)) return false;
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expiry'])) return false;
    if ($_SESSION['csrf_token_expiry'] < time()) return false;

    $ok = hash_equals($_SESSION['csrf_token'], $tokenFromRequest);

    // Rotate token after successful verification (good practice)
    if ($ok) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expiry']);
        csrf_token(); // generate fresh
    }

    return $ok;
}


/*function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) &&
           $_SESSION['csrf_token'] === $token &&
           $_SESSION['csrf_token_expiry'] >= time();
}


// Generate a new CSRF token and store it in the session
function generateCsrfToken()
{
    // Generate a random token
    $csrfToken = bin2hex(random_bytes(32));

    // Set the token in the session
    $_SESSION['csrf_token'] = $csrfToken;

    // Set the token expiration time (e.g., 15 minutes from now)
    $_SESSION['csrf_token_expiry'] = time() + (15 * 60); // 15 minutes
}*/


/**
 * Send common HTTP security headers.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    if (is_https()) {
        header(
            'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload'
        );
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function validateUploadedFile($file) {
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileMimeType = $finfo->file($file['tmp_name']);

    if (in_array($fileMimeType, $allowedMimeTypes)) {
        return true;
    }
    return false;
}

function csp_nonce(): string
{
    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', bin2hex(random_bytes(16)));
    }

    return CSP_NONCE;
}

/**
 * Generate and send the Content Security Policy header.
 *
 * Uses a per-request nonce to allow inline scripts/styles
 * generated by NoClass while blocking unauthorized content.
 */
function generate_csp_header(): void
{
    if (headers_sent()) {
        return;
    }

    $nonce = csp_nonce();

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "img-src 'self' data: https:",
        "font-src 'self' data: https:",
        "connect-src 'self'",
        "script-src 'self' 'nonce-{$nonce}'",
        "style-src 'self' 'nonce-{$nonce}'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp), true);
}

