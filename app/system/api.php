<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function api_is_request(): bool
{
    // Path-based: /api/...
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?: '';
    $path = trim($path, '/');

    // If project is in /NoClass, strip that prefix safely
    $base = trim(BASE_PATH, '/');
    if ($base && str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base) + 1);
    } elseif ($base && $path === $base) {
        $path = '';
    }

    if (str_starts_with($path, 'api/')) return true;
    if ($path === 'api') return true;

    // Header-based: Accept: application/json
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false) return true;

    return false;
}

function api_json($payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok($data = null, int $status = 200): void
{
    api_json(['ok' => true, 'data' => $data], $status);
}

function api_err(string $message, int $status = 400, $details = null): void
{
    $out = ['ok' => false, 'error' => $message];
    if ($details !== null) $out['details'] = $details;
    api_json($out, $status);
}

function api_not_found(string $message = 'Not Found'): void
{
    api_err($message, 404);
}

function api_unauthorized(string $message = 'Unauthorized'): void
{
    api_err($message, 401);
}

function api_forbidden(string $message = 'Forbidden'): void
{
    api_err($message, 403);
}

function api_method_not_allowed(string $message = 'Method Not Allowed'): void
{
    api_err($message, 405);
}
