<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure   = is_https();
    $sameSite = defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax';
    $sameSite = in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';

    // Browsers require Secure=true when SameSite=None
    if ($sameSite === 'None' && !$secure) $sameSite = 'Lax';

    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure'   => $secure,
        'cookie_httponly' => true,
        'cookie_samesite' => $sameSite,
        'use_strict_mode' => 1,
    ]);

    // Regenerate once to prevent session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;

    // Only trust forwarded headers when behind a known proxy.
    // Set TRUST_PROXY=true in config/config.php or .env when running behind
    // a reverse proxy (nginx, Cloudflare, AWS ELB) that sets this header.
    // Without this gate, any client can spoof X-Forwarded-Proto.
    $trustProxy = defined('TRUST_PROXY') && TRUST_PROXY;
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;

    return false;
}

// ── Password helpers ──────────────────────────────────────────────────────────

function security_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function security_verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

// Alias shorthands
function hashPassword(string $password): string  { return security_hash_password($password); }
function verifyPassword(string $password, string $hash): bool { return security_verify_password($password, $hash); }

// ── Input sanitization helpers ────────────────────────────────────────────────

function sanitizeInput($input): string
{
    return htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8');
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeForSQL($mysqli, string $input): string
{
    return mysqli_real_escape_string($mysqli, $input);
}

function validateUploadedFile(array $file): bool
{
    // Verify the upload completed without errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Verify this is a genuine PHP upload (not a forged tmp_name path)
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    return in_array($finfo->file($file['tmp_name']), $allowed, true);
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrf_token(int $ttlSeconds = 900): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) secure_session_start();

    $now = time();

    if (empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expiry']) ||
        $_SESSION['csrf_token_expiry'] < $now) {
        $_SESSION['csrf_token']        = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = $now + $ttlSeconds;
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">' . "\n";
}

/**
 * csrf_verify() — verify the CSRF token.
 *
 * Fix: original required the caller to pass the token explicitly.
 * Now extracts automatically from $_POST or X-CSRF-TOKEN header
 * when called with no argument. Callers can still pass a token
 * explicitly if needed.
 */
function csrf_verify(string $tokenFromRequest = ''): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) secure_session_start();

    // Auto-extract when not passed explicitly
    if ($tokenFromRequest === '') {
        $tokenFromRequest = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
    }

    if (empty($tokenFromRequest))                   return false;
    if (empty($_SESSION['csrf_token']))              return false;
    if (empty($_SESSION['csrf_token_expiry']))       return false;
    if ($_SESSION['csrf_token_expiry'] < time())     return false;

    $ok = hash_equals($_SESSION['csrf_token'], $tokenFromRequest);

    // Rotate after successful verification and send new token in response header
    // so JavaScript clients (noclass.js) can update their stored token without
    // a page reload. Without this, every second AJAX request fails after rotation.
    if ($ok) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expiry']);
        $newToken = csrf_token(); // seed fresh token immediately
        if (!headers_sent()) {
            header('X-CSRF-Token: ' . $newToken);
        }
    }

    return $ok;
}

// ── CSP ───────────────────────────────────────────────────────────────────────

function csp_nonce(): string
{
    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', bin2hex(random_bytes(16)));
    }
    return CSP_NONCE;
}

/**
 * generate_csp_header() — emit a Content-Security-Policy header.
 *
 * Directives are extensible via constants in config/config.php:
 *
 *   define('CSP_EXTRA_SCRIPT_SRC',  "'unsafe-eval'");
 *   define('CSP_EXTRA_STYLE_SRC',   'https://fonts.googleapis.com');
 *   define('CSP_EXTRA_FONT_SRC',    'https://fonts.gstatic.com');
 *   define('CSP_EXTRA_IMG_SRC',     'https://cdn.example.com');
 *   define('CSP_EXTRA_CONNECT_SRC', 'https://api.example.com');
 *   define('CSP_REPORT_URI',        '/csp-report');
 *   define('CSP_REPORT_ONLY',       true);   // use Report-Only mode for rollout
 */
function generate_csp_header(): void
{
    if (headers_sent()) return;

    $nonce = csp_nonce();

    $script_src = "'self' 'nonce-{$nonce}'";
    if (defined('CSP_EXTRA_SCRIPT_SRC') && CSP_EXTRA_SCRIPT_SRC) {
        $script_src .= ' ' . CSP_EXTRA_SCRIPT_SRC;
    }

    $style_src = "'self' 'nonce-{$nonce}'";
    if (defined('CSP_EXTRA_STYLE_SRC') && CSP_EXTRA_STYLE_SRC) {
        $style_src .= ' ' . CSP_EXTRA_STYLE_SRC;
    }

    $font_src = "'self' data:";
    if (defined('CSP_EXTRA_FONT_SRC') && CSP_EXTRA_FONT_SRC) {
        $font_src .= ' ' . CSP_EXTRA_FONT_SRC;
    }

    $img_src = "'self' data: https:";
    if (defined('CSP_EXTRA_IMG_SRC') && CSP_EXTRA_IMG_SRC) {
        $img_src .= ' ' . CSP_EXTRA_IMG_SRC;
    }

    $connect_src = "'self'";
    if (defined('CSP_EXTRA_CONNECT_SRC') && CSP_EXTRA_CONNECT_SRC) {
        $connect_src .= ' ' . CSP_EXTRA_CONNECT_SRC;
    }

    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "script-src {$script_src}",
        "style-src {$style_src}",
        "img-src {$img_src}",
        "font-src {$font_src}",
        "connect-src {$connect_src}",
    ];

    if (defined('CSP_REPORT_URI') && CSP_REPORT_URI) {
        $directives[] = 'report-uri ' . CSP_REPORT_URI;
    }

    $headerName = (defined('CSP_REPORT_ONLY') && CSP_REPORT_ONLY)
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';

    header($headerName . ': ' . implode('; ', $directives), true);
}

/**
 * send_security_headers() — emit common hardening headers.
 *
 * CSP is opt-in via USE_CSP in config/config.php.
 * Fix: original called generate_csp_header() unconditionally here
 * (via setup.php) which broke any project with inline styles/scripts.
 */
function send_security_headers(): void
{
    if (headers_sent()) return;

    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Opt-in CSP
    if (defined('USE_CSP') && USE_CSP) {
        generate_csp_header();
    }
}
