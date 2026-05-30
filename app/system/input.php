<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function is_get(): bool
{
    return request_method() === 'GET';
}

function is_put(): bool
{
    return request_method() === 'PUT';
}

function is_patch(): bool
{
    return request_method() === 'PATCH';
}

function is_delete(): bool
{
    return request_method() === 'DELETE';
}

function is_ajax(): bool
{
    // Standard AJAX header
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    // Fetch wrapper already sends Accept: application/json
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false) return true;

    return false;
}

function input_get(string $key = null, $default = null)
{
    if ($key === null) return $_GET;
    return $_GET[$key] ?? $default;
}

function input_post(string $key = null, $default = null)
{
    if ($key === null) return $_POST;
    return $_POST[$key] ?? $default;
}

function input_raw(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $cached = file_get_contents('php://input') ?: '';
    return $cached;
}

function input_json(bool $assoc = true)
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $raw = input_raw();
    if ($raw === '') return $cached = null;

    $decoded = json_decode($raw, $assoc);
    return $cached = $decoded;
}

function input_file(string $key = null)
{
    if ($key === null) return $_FILES;
    return $_FILES[$key] ?? null;
}

function input(string $key = null, $default = null)
{
    $method = request_method();

    if ($method === 'GET') {
        return $key === null ? $_GET : ($_GET[$key] ?? $default);
    }

    if ($method === 'POST') {
        // Prefer JSON if present
        $json = input_json(true);
        if (is_array($json)) {
            return $key === null ? $json : ($json[$key] ?? $default);
        }
        return $key === null ? $_POST : ($_POST[$key] ?? $default);
    }

    // PUT / PATCH / DELETE → JSON or form-data
    $json = input_json(true);
    if (is_array($json)) {
        return $key === null ? $json : ($json[$key] ?? $default);
    }
    
    // Parse other request methods
    parse_str(input_raw(), $parsed);
    return $key === null ? $parsed : ($parsed[$key] ?? $default);
}

function has_input(string $key): bool
{
    $value = input($key);
    return $value !== null && $value !== '';
}

function input_has_file(string $key): bool
{
    return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
}

function all_input(): array
{
    return input(null, []);
}

function only_input(array $keys): array
{
    $input = all_input();
    return array_intersect_key($input, array_flip($keys));
}

function except_input(array $keys): array
{
    $input = all_input();
    return array_diff_key($input, array_flip($keys));
}