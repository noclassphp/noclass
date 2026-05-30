<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function send_response($content, $statusCode = 200, $headers = [], bool $exit = true): void
{
    http_response_code($statusCode);

    foreach ($headers as $key => $value) {
        header($key . ': ' . $value);
    }

    echo $content;

    if ($exit) {
        exit;
    }
}

/*function redirect($url, $statusCode = 302)
{
    header('Location: ' . url($url), true, $statusCode);
    exit;
}*/

function redirect(string $path = '', int $statusCode = 302): void
{
    $location = has_scheme($path) ? $path : url($path);

    if (!headers_sent()) {
        header('Location: ' . $location, true, $statusCode);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($location) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

function respond_json($payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Controller can just: return ok([...]);
 */
function ok($data = null, int $status = 200): array
{
    return ['ok' => true, 'data' => $data, '_status' => $status];
}

/**
 * Controller can just: return err('message', 422);
 */
function err(string $message, int $status = 400, $details = null): array
{
    $out = ['ok' => false, 'error' => $message, '_status' => $status];
    if ($details !== null) $out['details'] = $details;
    return $out;
}


function json_success($data = null, int $status = 200, array $meta = []): void
{
    $response = ['ok' => true, 'data' => $data];
    if (!empty($meta)) {
        $response['meta'] = $meta;
    }
    respond_json($response, $status);
}

function json_error(string $message, int $status = 400, array $details = []): void
{
    respond_json(array_merge(['ok' => false, 'error' => $message], $details), $status);
}