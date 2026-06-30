<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 *
 * ── CSRF Protection Middleware ───────────────────────────────────────────────
 *
 * Verifies the CSRF token on every unsafe HTTP method (POST, PUT, PATCH,
 * DELETE). Safe methods (GET, HEAD, OPTIONS) are always allowed through.
 *
 * Usage in config/routes.php:
 *
 *   'blog' => [
 *       'controller' => 'Blog',
 *       'action'     => ['create', 'update/{num}', 'delete/{num}'],
 *       'middleware'  => ['Csrf'],
 *   ],
 *
 * The framework also applies CSRF verification automatically on unsafe
 * methods when CSRF_AUTO is enabled (see config/config.php). This
 * middleware is provided for explicit per-route use when CSRF_AUTO is off.
 *
 * To exempt a route (e.g. a stateless API authenticated by bearer token),
 * simply do not attach this middleware and set CSRF_AUTO to false, or
 * list the route in CSRF_EXEMPT_ROUTES.
 */

function Csrf(): bool
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Safe methods do not require CSRF verification
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return true;
    }

    // Verify CSRF token (auto-extracts from $_POST or X-CSRF-TOKEN header)
    if (csrf_verify()) {
        return true;
    }

    // Token invalid or missing
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }

    if (function_exists('is_ajax') && is_ajax()) {
        respond_json(['error' => 'CSRF token missing or invalid'], 403);
    }

    return false;
}
