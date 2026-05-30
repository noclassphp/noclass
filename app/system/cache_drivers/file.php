<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// Simple file-based cache driver
// Storage: BASE_PATH.'/cache'

function cache_file_dir(): string
{
    $dir = defined('BASE_PATH') ? (string)BASE_PATH . '/cache' : __DIR__ . '/../../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function cache_file_path(string $key): string
{
    // Keep filenames safe and uniform
    return rtrim(cache_file_dir(), "/\\") . '/' . sha1($key) . '.cache';
}

function cache_file_read(string $path)
{
    if (!is_file($path)) return null;

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return null;

    $data = @json_decode($raw, true);
    if (!is_array($data)) return null;

    $exp = isset($data['exp']) ? (int)$data['exp'] : 0;
    if ($exp > 0 && $exp < time()) {
        @unlink($path);
        return null;
    }

    return $data;
}

function cache_file_get(string $key)
{
    $path = cache_file_path($key);
    $data = cache_file_read($path);
    if ($data === null) return null;
    return $data['val'] ?? null;
}

function cache_file_set(string $key, $value, int $ttl = 0)
{
    $ttl = (int)$ttl;
    $exp = ($ttl > 0) ? (time() + $ttl) : 0;

    // Store raw strings; if caller wants JSON they should pass JSON.
    // If value is array/object, store JSON string.
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
    }

    $payload = json_encode([
        'exp' => $exp,
        'val' => $value,
    ]);

    $path = cache_file_path($key);
    return (@file_put_contents($path, $payload, LOCK_EX) !== false);
}

function cache_file_del(string $key)
{
    $path = cache_file_path($key);
    if (is_file($path)) {
        @unlink($path);
        return 1; // DEL count
    }
    return 0;
}

function cache_file_has(string $key)
{
    $path = cache_file_path($key);
    $data = cache_file_read($path);
    return ($data === null) ? 0 : 1; // EXISTS-style
}

function cache_file_clear()
{
    $dir = cache_file_dir();
    $ok = true;

    $files = @glob(rtrim($dir, "/\\") . '/*.cache');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f) && !@unlink($f)) $ok = false;
        }
    }
    return $ok;
}

// Optional ops
function cache_file_setnx(string $key, string $value)
{
    // Set only if not exists
    if (cache_file_has($key)) return 0;
    return cache_file_set($key, $value, 0) ? 1 : 0;
}

function cache_file_expire(string $key, int $ttl)
{
    $path = cache_file_path($key);
    $data = cache_file_read($path);
    if ($data === null) return 0;

    $ttl = (int)$ttl;
    $data['exp'] = ($ttl > 0) ? (time() + $ttl) : 0;
    $payload = json_encode($data);
    return (@file_put_contents($path, $payload, LOCK_EX) !== false) ? 1 : 0;
}

// Version helpers for versioned keys
function cache_file_getver(string $table)
{
    $k = "VER:" . strtolower(trim($table));
    $v = cache_file_get($k);
    if ($v === null) return 0;
    if (is_numeric($v)) return (int)$v;
    return 0;
}

function cache_file_bumpver(string $table)
{
    $k = "VER:" . strtolower(trim($table));
    $v = cache_file_getver($table);
    $v = (int)$v + 1;
    cache_file_set($k, (string)$v, 0);
    return $v; // IMPORTANT: return int version
}

// Pass-through commands are not supported in file driver
function cache_file_command_parts(array $parts)
{
    return null;
}
function cache_file_command(string $cmd)
{
    return null;
}

// Optional stale helpers (simple: treat same as get/set)
function cache_file_get_stale(string $key, int $ttl)
{
    $value = cache_file_get($key);

    return [
        'data' => $value,
        'expired' => false,
    ];
}
function cache_file_set_stale(string $key, $value, int $ttl)
{
    return cache_file_set($key, $value, $ttl);
}

// Locking (not implemented for file driver v1)
function cache_file_lockrow(string $table, $id, string $clientId, int $lockTtl, int $timeout)
{
    return 0;
}
function cache_file_unlockrow(string $table, $id, string $clientId)
{
    return true;
}
function cache_file_asyncrefresh(string $key, string $sql, array $params, int $ttl)
{
    return true;
}
