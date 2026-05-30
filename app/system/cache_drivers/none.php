<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// No-op cache driver (always behaves safely; never stores data)

function cache_none_get(string $key)
{
    return null;
}

function cache_none_set(string $key, $value, int $ttl = 0)
{
    // No-op set: return true so app logic doesn't break
    return true;
}

function cache_none_del(string $key)
{
    return 0; // DEL count
}

function cache_none_has(string $key)
{
    return 0; // EXISTS-style
}

function cache_none_clear()
{
    return true;
}

function cache_none_command_parts(array $parts)
{
    return null;
}

function cache_none_command(string $cmd)
{
    return null;
}

// Optional ops used by higher-level features
function cache_none_setnx(string $key, string $value)
{
    return 0;
}

function cache_none_expire(string $key, int $ttl)
{
    return 0;
}

function cache_none_getver(string $table)
{
    return 0;
}

function cache_none_bumpver(string $table)
{
    // If you want versioned keys even in NONE mode, you could return an incrementing number.
    // For v1 NONE, just return 0.
    return 0;
}

function cache_none_get_stale(string $key, int $ttl)
{
    return null;
}

function cache_none_set_stale(string $key, $value, int $ttl)
{
    return true;
}

function cache_none_lockrow(string $table, $id, string $clientId, int $lockTtl, int $timeout)
{
    return 0;
}

function cache_none_unlockrow(string $table, $id, string $clientId)
{
    return true;
}

function cache_none_asyncrefresh(string $key, string $sql, array $params, int $ttl)
{
    return true;
}
