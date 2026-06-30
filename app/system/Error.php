<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

/**
 * Escape HTML output safely.
 */
function error_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Return the current CSP nonce when available.
 *
 * This fallback keeps Error.php safe even if the main csp_nonce()
 * helper has not been loaded yet.
 */
function error_csp_nonce(): string
{
    if (function_exists('csp_nonce')) {
        return csp_nonce();
    }

    return defined('CSP_NONCE') ? (string) CSP_NONCE : '';
}

/**
 * Return a nonce attribute for CSP-safe <style> blocks.
 */
function error_nonce_attr(): string
{
    $nonce = error_csp_nonce();

    if ($nonce === '') {
        return '';
    }

    return ' nonce="' . error_html($nonce) . '"';
}

/**
 * Shared CSS for framework-generated error pages.
 *
 * No inline style="" attributes are used in this file so that
 * NoClass can keep a stricter Content Security Policy.
 */
function error_page_styles(): string
{
    return '<style' . error_nonce_attr() . '>
        .nc-error-page {
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            line-height: 1.6;
            padding: 20px;
        }

        .nc-error-box {
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .nc-error-basic {
            background: #ffffff;
            border: 1px solid #dddddd;
        }

        .nc-error-warning {
            background: #fff3e0;
            border: 1px solid #ff9800;
        }

        .nc-error-fatal {
            background: #ffebee;
            border: 1px solid #f44336;
        }

        .nc-error-title-warning {
            color: #e65100;
        }

        .nc-error-title-fatal {
            color: #c62828;
        }

        .nc-error-level {
            color: #d32f2f;
        }

        .nc-error-muted {
            color: #666666;
            font-size: 12px;
        }

        .nc-error-pre {
            background: #f5f5f5;
            padding: 15px;
            border: 1px solid #dddddd;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .nc-debug-trace {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .nc-debug-trace th,
        .nc-debug-trace td {
            border: 1px solid #cccccc;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        .nc-debug-trace th {
            background: #f5f5f5;
        }

        .nc-maintenance-body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #333333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            text-align: center;
        }

        .nc-maintenance-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nc-maintenance-title {
            color: #e74c3c;
            margin-bottom: 20px;
        }

        .nc-maintenance-message {
            background: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
            text-align: left;
            border-radius: 4px;
        }

        .nc-maintenance-debug {
            background: #fff8e1;
            padding: 15px;
            border: 1px solid #ffd54f;
            margin-top: 20px;
            border-radius: 4px;
            text-align: left;
            font-size: 12px;
        }
    </style>';
}

/**
 * Wrap basic error content in a small HTML shell.
 */
function error_page_wrap(string $content, string $title = 'Error'): string
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . error_html($title) . '</title>
    ' . error_page_styles() . '
</head>
<body class="nc-error-page">
    <div class="nc-error-box nc-error-basic">' . $content . '</div>
</body>
</html>';
}

/**
 * Beautified debug backtrace table.
 */

function debug_backtrace_html(): string
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    $html = '<table class="nc-debug-trace">';
    $html .= '<thead><tr>';
    $html .= '<th>#</th>';
    $html .= '<th>File</th>';
    $html .= '<th>Line</th>';
    $html .= '<th>Function</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($trace as $i => $row) {
        $function = $row['function'] ?? '-';

        if (isset($row['class'], $row['type'])) {
            $function = $row['class'] . $row['type'] . $function;
        }

        $html .= '<tr>';
        $html .= '<td>' . (int) $i . '</td>';
        $html .= '<td>' . error_html((string) ($row['file'] ?? '-')) . '</td>';
        $html .= '<td>' . error_html((string) ($row['line'] ?? '-')) . '</td>';
        $html .= '<td>' . error_html((string) $function) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}


/**
 * 404 - Action Not Found
 */
function notFoundAction(string $msg = ''): void
{
    if (function_exists('api_is_request') && api_is_request()) {
        api_not_found();
    }

    $content = '';

    if ($msg !== '') {
        $content .= '<h4>' . error_html($msg) . '</h4>';
    }

    $content .= '<h1>404 - Action Not Found</h1>';
    $content .= '<p>The requested page could not be found.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '404 - Action Not Found'), 404);
}

/**
 * 404 - Controller Not Found
 */
function notFoundController(string $msg = ''): void
{
    $content = '';

    if ($msg !== '') {
        $content .= '<h4>' . error_html($msg) . '</h4>';
    }

    $content .= '<h1>404 - Controller Not Found</h1>';
    $content .= '<p>The requested page could not be found.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '404 - Controller Not Found'), 404);
}

/**
 * 404 - View Not Found
 */
function notFoundView(string $msg = ''): void
{
    $content = '';

    if ($msg !== '') {
        $content .= '<h4>' . error_html($msg) . '</h4>';
    }

    $content .= '<h1>404 - View Not Found</h1>';
    $content .= '<p>The requested page could not be found.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '404 - View Not Found'), 404);
}

/**
 * 404 - Page Not Found
 */
function notFoundPage(string $msg = ''): void
{
    $content = '';

    if ($msg !== '') {
        $content .= '<h4>' . error_html($msg) . '</h4>';
    }

    $content .= '<h1>404 - Page Not Found</h1>';
    $content .= '<p>The requested page could not be found.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '404 - Page Not Found'), 404);
}

/**
 * 401 - Unauthorized
 */
function unauthorizedAction(): void
{
    $content = '<h1>401 - Unauthorized</h1>';
    $content .= '<p>You are not authorized to access this page.</p>';

    send_response(error_page_wrap($content, '401 - Unauthorized'), 401);
}

/**
 * 500 - Internal Server Error
 */
function internalServerErrorAction(?string $text = null): void
{
    $content = $text ?: '<h1>500 - Internal Server Error</h1><p>An unexpected error occurred.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '500 - Internal Server Error'), 500);
}

/**
 * Log 404 events for auditing.
 */
function log404(string $message): void
{
    $url = str_replace(["\r", "\n"], '', $_SERVER['REQUEST_URI'] ?? 'unknown');
    $message = str_replace(["\r", "\n"], '', $message);
    error_log("[NoClass][404] {$message} | URL: {$url}");
}

/**
 * Log error with different severity levels.
 */
function logError(string $message, string $level = 'ERROR', array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $url = str_replace(["\r", "\n"], '', substr($_SERVER['REQUEST_URI'] ?? 'unknown', 0, 2048));
    $ip  = str_replace(["\r", "\n"], '', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $message = str_replace(["\r", "\n"], '', $message);
    $level   = str_replace(["\r", "\n"], '', $level);

    $logMessage = "[{$timestamp}] [{$level}] {$message} | URL: {$url} | IP: {$ip}";

    if (!empty($context)) {
        $json = json_encode($context);
        $logMessage .= ' | Context: ' . ($json !== false ? $json : '[unserializable context]');
    }

    error_log($logMessage);

    if (defined('LOG_FILE') && LOG_FILE) {
        @file_put_contents(LOG_FILE, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Handle PHP errors and exceptions.
 */
function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
{
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];

    $level = $errorTypes[$errno] ?? 'UNKNOWN';

    logError("PHP {$level}: {$errstr} in {$errfile} on line {$errline}", $level, [
        'file' => $errfile,
        'line' => $errline,
    ]);

    if (defined('DEBUG') && DEBUG) {
        echo error_page_styles();
        echo '<div class="nc-error-box nc-error-fatal">';
        echo '<strong class="nc-error-level">PHP ' . error_html($level) . ':</strong> ' . error_html($errstr) . '<br>';
        echo '<small class="nc-error-muted">in ' . error_html($errfile) . ' on line ' . (int) $errline . '</small>';
        echo '</div>';
    }

    return true;
}

/**
 * Handle uncaught exceptions.
 */
function handle_exception(Throwable $exception): void
{
    $message = 'Uncaught Exception: ' . $exception->getMessage();
    $context = [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'code' => $exception->getCode(),
        'trace' => $exception->getTrace(),
    ];

    logError($message, 'EXCEPTION', $context);

    if (defined('DEBUG') && DEBUG) {
        $content = '<div class="nc-error-box nc-error-warning">';
        $content .= '<h1 class="nc-error-title-warning">500 - Uncaught Exception</h1>';
        $content .= '<p><strong>Message:</strong> ' . error_html($exception->getMessage()) . '</p>';
        $content .= '<p><strong>File:</strong> ' . error_html($exception->getFile()) . '</p>';
        $content .= '<p><strong>Line:</strong> ' . (int) $exception->getLine() . '</p>';
        $content .= '<p><strong>Code:</strong> ' . error_html((string) $exception->getCode()) . '</p>';
        $content .= '<h3>Stack Trace:</h3>';
        $content .= '<pre class="nc-error-pre">' . error_html($exception->getTraceAsString()) . '</pre>';
        $content .= '</div>';

        send_response(error_page_wrap($content, '500 - Uncaught Exception'), 500);
    }

    internalServerErrorAction('<h1>500 - Internal Server Error</h1><p>An unexpected error occurred. Please check the server logs.</p>');
}

/**
 * Handle fatal errors.
 */
function handleFatalError(): void
{
    $error = error_get_last();

    if ($error === null || $error['type'] !== E_ERROR) {
        return;
    }

    $message = 'Fatal Error: ' . $error['message'];
    $context = [
        'file' => $error['file'],
        'line' => $error['line'],
    ];

    logError($message, 'FATAL', $context);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if (defined('DEBUG') && DEBUG) {
        $content = '<div class="nc-error-box nc-error-fatal">';
        $content .= '<h1 class="nc-error-title-fatal">500 - Fatal Error</h1>';
        $content .= '<p><strong>Message:</strong> ' . error_html($error['message']) . '</p>';
        $content .= '<p><strong>File:</strong> ' . error_html($error['file']) . '</p>';
        $content .= '<p><strong>Line:</strong> ' . (int) $error['line'] . '</p>';
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
        $content .= '</div>';

        echo error_page_wrap($content, '500 - Fatal Error');
        return;
    }

    $content = '<h1>500 - Internal Server Error</h1>';
    $content .= '<p>An unexpected error has occurred. Please check the server logs.</p>';

    echo error_page_wrap($content, '500 - Internal Server Error');
}

/**
 * Setup error handlers.
 */
function setupErrorHandlers(bool $displayErrors = false): void
{
    ini_set('display_errors', $displayErrors ? '1' : '0');
    ini_set('display_startup_errors', $displayErrors ? '1' : '0');

    error_reporting(E_ALL);
    set_error_handler('handleError');
    set_exception_handler('handle_exception');
    register_shutdown_function('handleFatalError');
}

/**
 * 400 - Bad Request
 */
function badRequestAction(string $message = ''): void
{
    $content = '';

    if ($message !== '') {
        $content .= '<h4>' . error_html($message) . '</h4>';
    }

    $content .= '<h1>400 - Bad Request</h1>';
    $content .= '<p>The server cannot process the request due to a client error.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '400 - Bad Request'), 400);
}

/**
 * 403 - Forbidden
 */
function forbiddenAction(string $message = ''): void
{
    $content = '';

    if ($message !== '') {
        $content .= '<h4>' . error_html($message) . '</h4>';
    }

    $content .= '<h1>403 - Forbidden</h1>';
    $content .= '<p>You do not have permission to access this resource.</p>';

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '403 - Forbidden'), 403);
}

/**
 * 405 - Method Not Allowed
 */
function methodNotAllowedAction(array $allowedMethods = []): void
{
    $content = '<h1>405 - Method Not Allowed</h1>';
    $content .= '<p>The requested method is not allowed for this resource.</p>';

    if (!empty($allowedMethods)) {
        $content .= '<p>Allowed methods: ' . error_html(implode(', ', $allowedMethods)) . '</p>';
    }

    if (defined('DEBUG') && DEBUG) {
        $content .= '<h3>Debug Backtrace:</h3>';
        $content .= debug_backtrace_html();
    }

    send_response(error_page_wrap($content, '405 - Method Not Allowed'), 405);
}

/**
 * 503 - Service Unavailable
 */
function serviceUnavailableAction(string $retryAfter = '60'): void
{
    if (!headers_sent()) {
        header('Retry-After: ' . $retryAfter);
    }

    $content = '<h1>503 - Service Unavailable</h1>';
    $content .= '<p>The server is currently unavailable. Please try again later.</p>';

    send_response(error_page_wrap($content, '503 - Service Unavailable'), 503);
}

/**
 * Show maintenance mode page.
 */
function maintenanceModeAction(string $message = ''): void
{
    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=UTF-8');
    }

    $debugInfo = '';

    if (defined('DEBUG') && DEBUG) {
        $debugInfo = '<div class="nc-maintenance-debug"><strong>Debug Info:</strong><br><pre class="nc-error-pre">'
            . error_html(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true))
            . '</pre></div>';
    }

    $content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    ' . error_page_styles() . '
</head>
<body class="nc-maintenance-body">
    <div class="nc-maintenance-container">
        <h1 class="nc-maintenance-title">Maintenance Mode</h1>
        <p>Our website is currently undergoing scheduled maintenance.</p>
        <p>We apologize for the inconvenience and appreciate your patience.</p>';

    if ($message !== '') {
        $content .= '<div class="nc-maintenance-message">' . error_html($message) . '</div>';
    }

    $content .= $debugInfo;
    $content .= '<p>Please check back soon.</p>
    </div>
</body>
</html>';

    echo $content;
    exit;
}
