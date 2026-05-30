<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function env_parse_line(string $line): array
{
    $line = trim($line);
    if ($line === '' || $line[0] === '#') return [null, null];
    if (strpos($line, '=') === false) return [null, null];

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);

    // strip surrounding quotes
    if ($v !== '' && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
        $v = substr($v, 1, -1);
    }

    return [$k, $v];
}

/**
 * Load a .env file into getenv() / $_ENV / $_SERVER.
 * By default we DO NOT override existing env vars.
 */
function env_load_file(string $path, bool $override = false): bool
{
    if (!is_file($path) || !is_readable($path)) return false;

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (!$lines) return false;

    foreach ($lines as $line) {
        [$k, $v] = env_parse_line($line);
        if (!$k) continue;

        $exists = getenv($k) !== false;
        if ($exists && !$override) continue;

        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    return true;
}

/**
 * Load env in the following order:
 * 1) .env (base) - does NOT override existing
 * 2) .env.<APP_ENV> - overrides
 * 3) .env.local - overrides (developer machine)
 */
function env_boot(string $basePath): void
{
    env_load_file($basePath . '/.env', false);

    $appEnv = getenv('APP_ENV') ?: '';
    if ($appEnv) {
        env_load_file($basePath . '/.env.' . $appEnv, true);
    }

    env_load_file($basePath . '/.env.local', true);
}

/**
 * Typed env helpers
 */
function env(string $key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = env($key, null);
    if ($v === null) return $default;

    $v = strtolower(trim((string)$v));
    return in_array($v, ['1','true','yes','on'], true);
}

function env_int(string $key, int $default = 0): int
{
    $v = env($key, null);
    if ($v === null) return $default;
    return (int)$v;
}
