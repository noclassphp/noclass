<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

declare(strict_types=1);

define('PUBLIC_PATH', __DIR__);
define('BASE_PATH', noclass_base_path());
define('BASE_URI', rtrim(str_replace(
    basename($_SERVER['SCRIPT_NAME']),
    '',
    $_SERVER['SCRIPT_NAME']
), '/'));


// Load configuration and functions
require_once BASE_PATH.'/system/setup.php';


if (DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}




/// ----- DO NOT EDIT ---- ///


// NoClass BASE_PATH resolver
function noclass_base_path(): string
{
    $here = __DIR__;

    // 1) Legacy: system/ beside index.php
    if (is_dir($here . '/system')) {
        return realpath($here);
    }

    // 2) Option 2: /app inside web root
    if (is_dir($here . '/app/system')) {
        return realpath($here . '/app');
    }

    // 3) Option 1: app outside web root
    if (is_dir($here . '/../noclass_app/system')) {
        return realpath($here . '/../noclass_app');
    }

    // 4) Common: public/ -> project root
    if (is_dir($here . '/../system')) {
        return realpath($here . '/..');
    }

    // Fail fast
    http_response_code(500);
    echo 'NoClass bootstrap error: Unable to resolve BASE_PATH';
    exit;
}


// Security Headers
send_security_headers();

//DB initalising - Comment out if not using DB or configure in config/config.php
if(USE_DB)$mysqli = db_connect();

// Route and execute
secure_session_start();
route();
