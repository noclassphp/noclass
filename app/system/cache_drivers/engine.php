<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// system/cache_drivers/engine.php
//
// ENGINE driver — connects NoClass to an external cache engine
// (Redis, Valkey, KeyDB, or any RESP-compatible store) via the
// NoClass Cache Engine package or a custom RESP client.
//
// ── HOW THIS FILE IS LOADED ───────────────────────────────────────────────────
// cache_load_driver('ENGINE') checks, in order:
//   1. app/lib/noclass/cache-engine/driver.php   ← your custom implementation
//   2. vendor/noclass/cache-engine/driver.php    ← NoClass Cache Engine package
//   3. THIS FILE (system/cache_drivers/engine.php) ← fallback stub
//
// In production you should replace this file with a real implementation,
// or install the NoClass Cache Engine package.
//
// ── RETURN VALUE CONTRACT ─────────────────────────────────────────────────────
// The cache normalization layer in cache.php expects specific return shapes.
// Deviating from these will silently break versioned keys and other features.
//
//   cache_engine_get()        → string|null
//   cache_engine_set()        → 'OK' | true | 1
//   cache_engine_del()        → int (count of deleted keys, typically 0 or 1)
//   cache_engine_has()        → 0 | 1  (int, not bool — mirrors Redis EXISTS)
//   cache_engine_clear()      → 'OK' | true
//   cache_engine_setnx()      → 0 | 1  (0 = already existed, 1 = set)
//   cache_engine_expire()     → 0 | 1  (0 = key not found, 1 = expiry set)
//   cache_engine_getver()     → int    (table version number, 0 if not set)
//   cache_engine_bumpver()    → int    (new version number after increment)
//                               !! MUST return an int, NOT 'OK' !!
//                               If this returns 'OK', versioned cache keys break.
//   cache_engine_command_parts() → raw engine response (RESP array passthrough)
//   cache_engine_command()    → raw engine response (legacy string passthrough)
//
// ── IMPLEMENTING A REAL ENGINE DRIVER ────────────────────────────────────────
// Copy this file to app/lib/noclass/cache-engine/driver.php and replace each
// stub with a real call to your RESP client or socket connection.
//
// Example using a hypothetical $resp client:
//
//   function cache_engine_get(string $key) {
//       return resp_client()->get($key);
//   }
//   function cache_engine_set(string $key, $value, int $ttl = 0) {
//       if ($ttl > 0) return resp_client()->setex($key, $ttl, $value);
//       return resp_client()->set($key, $value);
//   }
//   function cache_engine_bumpver(string $table) {
//       return (int) resp_client()->incr('VER:' . strtolower(trim($table)));
//   }
//   // ... etc.

// ── STUB IMPLEMENTATIONS (safe no-ops until replaced) ─────────────────────────

function cache_engine_get(string $key)
{
    return null;
}

function cache_engine_set(string $key, $value, int $ttl = 0)
{
    return 'OK';
}

function cache_engine_del(string $key)
{
    return 0;
}

function cache_engine_has(string $key)
{
    return 0;
}

function cache_engine_clear()
{
    return 'OK';
}

function cache_engine_setnx(string $key, string $value)
{
    return 0;
}

function cache_engine_expire(string $key, int $ttl)
{
    return 0;
}

function cache_engine_getver(string $table)
{
    return 0;
}

/**
 * IMPORTANT: bumpver MUST return an int (the new version number).
 * Returning 'OK' will silently break versioned cache keys.
 */
function cache_engine_bumpver(string $table)
{
    return 1;
}

function cache_engine_command_parts(array $parts)
{
    return null;
}

function cache_engine_command(string $cmd)
{
    return null;
}

function cache_engine_get_stale(string $key, int $ttl)
{
    return null;
}

function cache_engine_set_stale(string $key, $value, int $ttl)
{
    return 'OK';
}

function cache_engine_lockrow(string $table, $id, string $clientId, int $lockTtl, int $timeout)
{
    // Stub — replace with real RESP SETNX-based lock when implementing ENGINE driver.
    // Returns 1 (success) until a real implementation is provided.
    return 1;
}

function cache_engine_unlockrow(string $table, $id, string $clientId)
{
    return 'OK';
}

function cache_engine_asyncrefresh(string $key, string $sql, array $params, int $ttl)
{
    return 'OK';
}
