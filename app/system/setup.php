<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// ── BASE_PATH guard MUST come first — before any require_once ─────────────────
// Bug fix: original had this guard AFTER the first require_once that uses
// BASE_PATH, making it unreachable on failure.
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

// ── Environment ───────────────────────────────────────────────────────────────
require_once BASE_PATH . '/system/env.php';

// .env files are loaded from BASE_PATH (the app root directory).
// BASE_PATH is NOT the same as the web root or where index.php lives.
// See APP_STRUCTURE.md — Environment variables section for the correct
// .env location for each deployment layout.
//
// Load order: .env → .env.<APP_ENV> → .env.local
// Later files override earlier ones.
env_boot(BASE_PATH);

// ── Core system files ─────────────────────────────────────────────────────────
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

require_once BASE_PATH . '/system/func.php';
require_once BASE_PATH . '/system/cache.php';
require_once BASE_PATH . '/system/cache_client.php';
require_once BASE_PATH . '/system/db.php';
require_once BASE_PATH . '/system/Respond.php';
require_once BASE_PATH . '/system/Error.php';
require_once BASE_PATH . '/system/form.php';
require_once BASE_PATH . '/system/table.php';
require_once BASE_PATH . '/system/input.php';
require_once BASE_PATH . '/system/security.php';
require_once BASE_PATH . '/system/view_helpers.php';   // layout, partial, section system
require_once BASE_PATH . '/system/modules.php';         // module discovery, registry, meta, boot
require_once BASE_PATH . '/system/services.php';
require_once BASE_PATH . '/system/api.php';

setupErrorHandlers(DEBUG);
setup_logging();

// ── File map ──────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/system/filemap.php';
filemap_boot_globals();

// ── Boot active modules ───────────────────────────────────────────────────────
// Loads meta, permissions and menus for all active modules into
// $GLOBALS['__noclass_modules'] so they are available everywhere
// without any controller needing to call module_boot_active() explicitly.
// Modules with unmet requirements are loaded but flagged — their
// permissions/menus are excluded. Safe when modules/ dir is empty.
if (is_dir(BASE_PATH . '/modules')) {
    module_boot_active();
}

// ── Application init loader ──────────────────────────────────────────────────
// Every .php file directly inside init/ is required at boot, in alphabetical
// order. This is the correct place for app-level helpers, constants, macros,
// permission matrices, event listeners — anything that must be globally
// available on every request without an explicit lib() call.
//
// Load order is controlled by numeric prefixes when inter-file dependencies
// exist:   01_constants.php → 02_helpers.php → 03_permissions.php
//
// lib/ files are NOT auto-loaded — they remain lazy via lib('name').
// init/ and lib/ have distinct and complementary contracts.
//
// Under OPcache (production) this loop costs virtually nothing — files are
// compiled once and served from shared memory. See php.ini for OPcache setup.
//
// init/ is optional — projects that need no global init simply omit the folder.
$_nc_init_dir = BASE_PATH . '/init';
if (is_dir($_nc_init_dir)) {
    $_nc_init_files = glob($_nc_init_dir . '/*.php');
    if ($_nc_init_files) {
        sort($_nc_init_files);
        foreach ($_nc_init_files as $_nc_init_file) {
            require_once $_nc_init_file;
        }
    }
    unset($_nc_init_files, $_nc_init_file);
}
unset($_nc_init_dir);

// ── CSP nonce (generated once per request) ────────────────────────────────────
// generate_csp_header() is NOT called here unconditionally.
// It is called by send_security_headers() when USE_CSP is true.
// Bug fix: original called generate_csp_header() here on every request,
// which blocked inline styles/scripts on any project without full nonce tagging.
if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', bin2hex(random_bytes(16)));
}

// ── Route.php last — it uses globals set above ────────────────────────────────
require_once BASE_PATH . '/system/Route.php';


/**
 * Build a map of basename => full file path for all .php files
 * under the given directories. Used as a legacy fallback if filemap.php
 * is not available. filemap.php is the preferred mechanism.
 */
function build_file_map(array $dirs): array
{
    $map = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') continue;
            $relative = str_replace(
                rtrim($dir, '/\\') . DIRECTORY_SEPARATOR, '', $file->getPathname()
            );
            $key = str_replace(['/', '\\', '.php'], ['/', '/', ''], $relative);
            $map[$key] = $file->getPathname();
        }
    }
    return $map;
}
