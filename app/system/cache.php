<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// NoClass Cache Dispatcher (core)
//
// Stable public API:
//   - cache_get($key, $default)
//   - cache_set($key, $value, $ttl)
//   - cache_del($key)
//   - cache_has($key)
//   - cache_clear()
//
// Passthroughs:
//   - cache_command(string $cmd)         Legacy string command (unsafe for values with spaces)
//   - cache_command_parts(array $parts)  Safe parts-based command (preferred)
//
// Driver resolution order:
//  1) app/lib/cache_drivers/<driver>.php
//  2) system/cache_drivers/<driver>.php
//  3) vendor/noclass/cache-engine/driver.php (Pro)
//
// Driver naming (recommended):
//   CACHING = 'NONE' | 'FILE' | 'ENGINE'

$GLOBALS['__noclass_cache_driver'] = null;

function cache_driver_name(): string
{
    $d = defined('CACHING') ? strtoupper((string)CACHING) : 'NONE';
    return $d ?: 'NONE';
}

function cache_load_driver(string $driver): bool
{
    $driver = strtoupper($driver);
    $file   = strtolower($driver) . '.php';

    // ENGINE has special priority
    if ($driver === 'ENGINE') {
        $appEngine = BASE_PATH . '/lib/noclass/cache-engine/driver.php';
        if (is_file($appEngine)) {
            require_once $appEngine;
            return true;
        }

        $vendorEngine = BASE_PATH . '/vendor/noclass/cache-engine/driver.php';
        if (is_file($vendorEngine)) {
            require_once $vendorEngine;
            return true;
        }
    }

    // App-level driver override
    $app = BASE_PATH . '/lib/cache_drivers/' . $file;
    if (is_file($app)) {
        require_once $app;
        return true;
    }

    // System driver fallback
    $sys = BASE_PATH . '/system/cache_drivers/' . $file;
    if (is_file($sys)) {
        require_once $sys;
        return true;
    }

    return false;
}

function cache_init(?string $driver = null): void
{
    $driver = $driver ? strtoupper($driver) : cache_driver_name();

    if ($GLOBALS['__noclass_cache_driver'] === $driver) return;

    if (!cache_load_driver($driver)) {
        $driver = 'NONE';
        cache_load_driver('NONE');
    }

    $GLOBALS['__noclass_cache_driver'] = $driver;
}

function cache_call(string $op, array $args = [])
{
    //var_dump($args);

    cache_init();

    $driver = strtolower($GLOBALS['__noclass_cache_driver'] ?? 'none');
    $fn = "cache_{$driver}_{$op}";

    if (!function_exists($fn) && $driver !== 'none') {
        $GLOBALS['__noclass_cache_driver'] = 'NONE';
        $fn = "cache_none_{$op}";
    }
//echo $fn.'</br>';

error_log('[CACHE OUT] ' . json_encode($args));
    if (!function_exists($fn)) return null;

    return $fn(...$args);
}

/* -------------------------
   Stable Public API
------------------------- */

function cache_get(string $key, $default = null)
{
    $v = cache_call('get', [$key]);
    return ($v === null || $v === false) ? $default : $v;
}

function cache_set(string $key, $value, ?int $ttl = null): bool
{
    if ($ttl === null) $ttl = defined('CACHE_TTL') ? (int)CACHE_TTL : 30;

    return cache_resp_is_true(cache_call('set', [$key, $value, (int)$ttl]));
}

function cache_del(string $key): bool
{
    return cache_resp_is_true(cache_call('del', [$key]));
}

function cache_has(string $key): bool
{
    return cache_resp_is_true(cache_call('has', [$key]));
}

function cache_clear(): bool
{
    return cache_resp_is_true(cache_call('clear', []));
}

/* -------------------------
   Safe command passthroughs
------------------------- */

function cache_command_parts(array $parts)
{
    return cache_call('command_parts', [$parts]);
}

/**
 * Legacy string passthrough (unsafe for values with spaces/JSON).
 * Keep for backward compatibility only.
 */
function cache_command(string $cmd)
{
    return cache_call('command', [$cmd]);
}

/* -------------------------
   Compatibility helpers (optional)
------------------------- */

function cache_setnx(string $key, string $value): bool
{
    $r = cache_call('setnx', [$key, $value]);
    return cache_resp_is_true($r);
}

function cache_expire(string $key, int $ttl): bool
{
    $r = cache_call('expire', [$key, $ttl]);
    return cache_resp_is_true($r);
}

function cache_getver(string $table): int
{
    $v = cache_call('getver', [$table]);
    return is_numeric($v) ? (int)$v : 0;
}

function cache_bumpver(string $table): bool
{
    return cache_resp_is_true(cache_call('bumpver', [$table]));
}

function cache_get_stale(string $key, int $ttl): array
{
    $r = cache_call('get_stale', [$key, $ttl]);
    return is_array($r) ? $r : ['data' => null, 'expired' => false];
}

function cache_set_stale(string $key, string $value, int $ttl): bool
{
    return cache_resp_is_true(cache_call('set_stale', [$key, $value, $ttl]));
}

function lockRow(string $table, $id, string $clientId, int $lockTtl = 5, int $timeout = 10): bool
{
    return cache_resp_is_true(cache_call('lockrow', [$table, $id, $clientId, $lockTtl, $timeout]));
}

function unlockRow(string $table, $id, string $clientId): void
{
    cache_call('unlockrow', [$table, $id, $clientId]);
}

function asyncRefreshCache(string $key, string $sql, array $params, int $ttl): void
{
    cache_call('asyncrefresh', [$key, $sql, $params, $ttl]);
}

/**
 * Normalize RESP / engine truthy responses.
 */
function cache_resp_is_true($r): bool
{
    if ($r === null || $r === false) return false;

    if (is_int($r)) return $r === 1;

    if (is_string($r)) {
        $v = trim($r);
        if ($v === '1') return true;
        if ($v === '0' || $v === '') return false;
        if (strcasecmp($v, 'OK') === 0) return true;
    }

    return (bool)$r;
}

/**
 * Normalize RESP integer responses.
 */
function cache_resp_int($r, int $default = 0): int
{
    if (is_int($r)) return $r;
    if (is_string($r) && is_numeric($r)) return (int)$r;
    return $default;
}