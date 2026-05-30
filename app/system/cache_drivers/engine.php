<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// system/cache_drivers/engine.php (TEMPLATE)
// This is a template driver showing REQUIRED return shapes for NoClass v1 normalization.
//
// IMPORTANT CONTRACT (so core normalization works correctly):
// - set/get: get returns string|null, set returns true/'OK'/1, etc.
// - del/has/expire/setnx MUST return 0/1 as int (preferred) or '0'/'1' strings.
// - bumpver MUST return an integer version (preferred) or numeric string.
//   If bumpver returns 'OK', versioned keys WILL break.
//
// Implement these by calling your engine socket client / RESP client.
// Example placeholders below:

function cache_engine_get(string $key) { /* return string|null */ return null; }
function cache_engine_set(string $key, $value, int $ttl = 0) { /* return OK/true */ return 'OK'; }
function cache_engine_del(string $key) { /* return int count */ return 0; }
function cache_engine_has(string $key) { /* return 0/1 */ return 0; }
function cache_engine_clear() { return 'OK'; }

function cache_engine_setnx(string $key, string $value) { return 0; }
function cache_engine_expire(string $key, int $ttl) { return 0; }

function cache_engine_getver(string $table) { return 0; }
function cache_engine_bumpver(string $table) { return 1; } // MUST return version int

function cache_engine_command_parts(array $parts) { /* raw RESP */ return null; }
function cache_engine_command(string $cmd) { return null; }

// Optional stale + locks
function cache_engine_get_stale(string $key, int $ttl) { return null; }
function cache_engine_set_stale(string $key, $value, int $ttl) { return 'OK'; }
function cache_engine_lockrow(string $table, $id, string $clientId, int $lockTtl, int $timeout) { return 0; }
function cache_engine_unlockrow(string $table, $id, string $clientId) { return 'OK'; }
function cache_engine_asyncrefresh(string $key, string $sql, array $params, int $ttl) { return 'OK'; }
