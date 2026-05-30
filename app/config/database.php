<?php

// =====================================================
// NoClass database configuration
// File: config/database.php
// =====================================================
// NoClass™ PHP Procedural Framework
// Copyright 2024-2026 Danny Mbanginu.
// Licensed under the Apache License, Version 2.0.
// See the LICENSE file for details.
// =====================================================

// --------------------------------------------------
// Config (adjust as needed)
// --------------------------------------------------
// The starter demo keeps USE_DB=false in config/config.php, so these values
// are placeholders until a developer enables database-backed features.

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env_int('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'noclass'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Simple per-request cache: [ sql + params_json => [timestamp, mysqli_result|true] ]
$GLOBALS['DB_CACHE']     = [];
$GLOBALS['DB_CACHE_TTL'] = 60;
