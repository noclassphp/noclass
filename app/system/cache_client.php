<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// cache_client.php — NoClass cache client primitives (versioned keys + atomic multi-key updates)
//
// IMPORTANT:
//  - Requires the dispatcher (system/cache.php), not the engine directly.
//  - Uses cache_command_parts([...]) for safety (no broken JSON/spaces).
//
// Expected constants (optional):
//  - CACHE_TTL

//require_once BASE_PATH . '/system/cache.php';

function cache_set_versioned(string $key, string $value, int $ttl = null): bool
{
    $ttl = ($ttl === null) ? (defined('CACHE_TTL') ? (int)CACHE_TTL : null) : (int)$ttl;

    $v = cache_command_parts(['BUMPVER', $key]);
    //$v = cache_resp_int($v, null);
    //if ($v === null) return false;
    if ($v === false || $v === null) return false;

    $versionedKey = "{$key}:{$v}";

    $ok1 = ($ttl !== null && $ttl > 0)
        ? (cache_command_parts(['SET', $versionedKey, $value, 'EX', (string)$ttl]) !== false)
        : (cache_command_parts(['SET', $versionedKey, $value]) !== false);
    if (!$ok1) return false;

    $ok2 = ($ttl !== null && $ttl > 0)
        ? (cache_command_parts(['SET', "LATEST:{$key}", (string)$v, 'EX', (string)$ttl]) !== false)
        : (cache_command_parts(['SET', "LATEST:{$key}", (string)$v]) !== false);
    if (!$ok2) return false;

    return true;
}

function cache_get_versioned(string $key)
{
    $v = cache_get("LATEST:{$key}");
    if ($v === false || $v === null) return null;

    return cache_get("{$key}:{$v}");
}

function cache_tx_begin(): bool
{
    $r = cache_command_parts(['MULTI']);
    return ($r === 'OK' || $r === true);
}

function cache_tx_commit()
{
    return cache_command_parts(['EXEC']);
}

function cache_tx_rollback(): bool
{
    $r = cache_command_parts(['DISCARD']);
    return ($r === 'OK' || $r === true);
}

function cache_update_multiple(array $pairs, ?int $ttl = null): bool
{
    if (!cache_tx_begin()) return false;

    foreach ($pairs as $k => $v) {
        $key = (string)$k;
        $val = is_string($v) ? $v : json_encode($v);

        $resp = ($ttl !== null && (int)$ttl > 0)
            ? cache_command_parts(['SET', $key, $val, 'EX', (string)(int)$ttl])
            : cache_command_parts(['SET', $key, $val]);

        if ($resp === false || $resp === null) {
            cache_tx_rollback();
            return false;
        }
    }

    $exec = cache_tx_commit();
    if ($exec === false || $exec === null) {
        cache_tx_rollback();
        return false;
    }

    if (is_array($exec)) {
        foreach ($exec as $r) {
            if ($r === false || $r === null) return false;
        }
    }

    return true;
}
