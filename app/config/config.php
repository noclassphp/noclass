<?php

// =====================================================
// NoClass main configuration
// File: config/config.php
// =====================================================
// NoClass™ PHP Procedural Framework
// Copyright 2024-2026 Danny Mbanguni.
// Licensed under the Apache License, Version 2.0.
// =====================================================

// ── Application ───────────────────────────────────────────────────────────────
define('WEBSITE_NAME',              'NoClass Demo');
define('BASE_URL',                  env('BASE_URL', 'localhost'));
define('APP_ENV',                   env('APP_ENV', 'development'));
define('DEBUG',                     env_bool('APP_DEBUG', APP_ENV !== 'production'));
define('APP_DEBUG',                 DEBUG);

// The route key used when URL is empty (root request).
// Must match a key in config/routes.php.
// Fix: Route.php now reads this constant instead of hardcoding 'home'.
define('DEFAULT_CONTROLLER',        'Home');

// ── Assets ────────────────────────────────────────────────────────────────────
define('ASSET_PATH',                'assets');
define('CDN_URL',                   null);
define('ASSET_CACHE_BUST',          true);

// ── Layout ────────────────────────────────────────────────────────────────────
// Default layout file name resolved from views/layouts/{DEFAULT_LAYOUT}.php.
// If absent, the framework falls back to views/layouts/main.php by convention.
// Set to false to disable layout wrapping globally (rare).
define('DEFAULT_LAYOUT',            'main');

// ── Security / Session ────────────────────────────────────────────────────────
define('SESSION_SAMESITE',          'Lax');
define('VIEW_XSS_AUDIT',            false);
define('ALLOW_UNDECLARED_ACTIONS',  false);

// CSP — opt-in. When true, send_security_headers() emits a CSP header.
/* All inline <script> and <style> tags in views must carry nonce="<?= csp_nonce() ?>" */
// Fix: original called generate_csp_header() unconditionally in setup.php
// which broke any project with untagged inline styles/scripts.
define('USE_CSP',                   false);

// Optional CSP directive extensions (only used when USE_CSP = true):
// define('CSP_EXTRA_SCRIPT_SRC',  "'unsafe-eval'");
// define('CSP_EXTRA_STYLE_SRC',   'https://fonts.googleapis.com');
// define('CSP_EXTRA_FONT_SRC',    'https://fonts.gstatic.com');
// define('CSP_EXTRA_IMG_SRC',     'https://cdn.example.com');
// define('CSP_EXTRA_CONNECT_SRC', 'https://api.example.com');
// define('CSP_REPORT_URI',        '/csp-report');
// define('CSP_REPORT_ONLY',       true);

// ── Logging ───────────────────────────────────────────────────────────────────
define('APP_LOG',                   env_bool('APP_LOG', true));
define('APP_LOG_PATH',              BASE_PATH . '/storage/logs/app.log');

// ── Database ──────────────────────────────────────────────────────────────────
define('USE_DB',                    env_bool('USE_DB', false));
define('DB',                        'mysql');

// ── Cache ─────────────────────────────────────────────────────────────────────
define('CACHE_NONE',                'NONE');
define('CACHE_FILE',                'FILE');
define('CACHE_ENGINE',              'ENGINE');
define('CACHE_DRIVER',              strtoupper(env('CACHE_DRIVER', CACHE_NONE)));
define('CACHING',                   CACHE_DRIVER);

define('FILEMAP_CACHE',             env_bool('FILEMAP_CACHE', true));
define('FILEMAP_CACHE_PATH',        BASE_PATH . '/cache/filemap.php');
define('FILEMAP_CACHE_AUTO_REBUILD',env_bool('FILEMAP_CACHE_AUTO_REBUILD', true));
define('FILEMAP_AUTO_REBUILD',      DEBUG);

define('CACHE_TTL_DB',              5);
define('CACHE_TTL_ROUTE',           5);
define('CACHE_REFRESH_DEDUP_WINDOW',10);
define('CACHE_OBS_ENABLED',         true);
define('CACHE_OBS_LOG_FILE',        BASE_PATH . '/storage/logs/cache_refresh.log');

// ── Table helper ──────────────────────────────────────────────────────────────
define('TABLE_GRIDJS_INLINE',       false);

// ── Auto-create directories ───────────────────────────────────────────────────
if (CACHING === CACHE_FILE && !is_dir(BASE_PATH . '/cache')) {
    @mkdir(BASE_PATH . '/cache', 0755, true);
}
if (!is_dir(BASE_PATH . '/storage/logs')) {
    @mkdir(BASE_PATH . '/storage/logs', 0755, true);
}
if (!is_dir(BASE_PATH . '/storage/cache')) {
    @mkdir(BASE_PATH . '/storage/cache', 0755, true);
}
