<?php

// =====================================================
// NoClass main configuration
// File: config/config.php
// =====================================================
// NoClass™ PHP Procedural Framework
// Copyright 2024-2026 Danny Mbanginu.
// Licensed under the Apache License, Version 2.0.
// See the LICENSE file for details.
// =====================================================

// Environment
define('WEBSITE_NAME',                  'NoClass Demo');
define('BASE_URL',                      env('BASE_URL', 'localhost'));
define('APP_ENV',                       env('APP_ENV', 'development'));
define('DEBUG',                         env_bool('APP_DEBUG', APP_ENV !== 'production'));
define('APP_DEBUG',                     DEBUG);
define('DEFAULT_CONTROLLER',            'Home');

// Assets
define('ASSET_PATH',                    'assets');
define('CDN_URL',                       null);
define('ASSET_CACHE_BUST',              true);
define('TABLE_GRIDJS_INLINE',           false);

// Security / session
define('SESSION_SAMESITE',              'Lax');
define('VIEW_XSS_AUDIT',                false);
define('ALLOW_UNDECLARED_ACTIONS',      false);

//---------- System Configs ----------------

define('APP_LOG',                       env_bool('APP_LOG', true));
define('APP_LOG_PATH',                  BASE_PATH . '/logs/app.log');

//---------- Database Configs ----------------
// The default demo intentionally does not require a database.
// Developers can enable this later after configuring config/database.php.

define('USE_DB',                        env_bool('USE_DB', false));
define('DB',                            'mysql');

//---------- Cache Configs ----------------

define('CACHE_NONE',                    'NONE');
define('CACHE_FILE',                    'FILE');
define('CACHE_ENGINE',                  'ENGINE');
define('CACHE_DRIVER',                  strtoupper(env('CACHE_DRIVER', CACHE_NONE)));
define('CACHING',                       CACHE_DRIVER);

define('FILEMAP_CACHE',                 env_bool('FILEMAP_CACHE', true));
define('FILEMAP_CACHE_PATH',            BASE_PATH . '/cache/filemap.php');
define('FILEMAP_CACHE_AUTO_REBUILD',    env_bool('FILEMAP_CACHE_AUTO_REBUILD', true));
define('FILEMAP_AUTO_REBUILD',          DEBUG);

define('CACHE_TTL_DB',                  5);
define('CACHE_TTL_ROUTE',               5);
define('CACHE_REFRESH_DEDUP_WINDOW',    10);
define('CACHE_OBS_ENABLED',             true);
define('CACHE_OBS_LOG_FILE',            BASE_PATH . '/storage/logs/cache_refresh.log');

// ------------DO NOT EDIT ------------

if (CACHING === CACHE_FILE && ! is_dir(BASE_PATH . '/cache')) {
    mkdir(BASE_PATH . '/cache', 0755, true);
}
