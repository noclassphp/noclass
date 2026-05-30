<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// Setup file for NoClass
// Include the necessary files only if they haven't been included before
// BASE_PATH must be defined by index.php.
// Fallback keeps legacy/direct calls from exploding (still not recommended).

require_once BASE_PATH . '/system/env.php';
env_boot(BASE_PATH);

//require BASE_PATH . '/system/env_check.php';
//env_validate_or_die();


if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

// Include the required files
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

// Enable OPcache if available - To be handle properly later
// Only disable timestamp validation at runtime if you really need to:
/*if(!DEBUG){
    if (function_exists('opcache_get_status')) {
        @ini_set('opcache.validate_timestamps', '0');
        // You *cannot* ini_set('opcache.enable', '1') here
    }
}*/
require_once BASE_PATH . '/system/func.php';           // lib(), model(), sanitize(), e(), etc.
require_once BASE_PATH . '/system/cache.php';          // caching functions
require_once BASE_PATH . '/system/cache_client.php';   // advance caching functions
require_once BASE_PATH . '/system/db.php';             // db_raw(), db_select(), ...
require_once BASE_PATH . '/system/Respond.php';        // send_response(), redirectResponse(), etc.
require_once BASE_PATH . '/system/Error.php';          // error handlers and error response helpers
require_once BASE_PATH . '/system/form.php';           // form_open(), validate_file(), etc.
require_once BASE_PATH . '/system/table.php';          // table_open(), render_table(), etc.
require_once BASE_PATH . '/system/input.php';          // handling post, get and json
require_once BASE_PATH . '/system/security.php';       // 
require_once BASE_PATH . '/system/services.php';       // 
require_once BASE_PATH . '/system/api.php';            // 

setupErrorHandlers(DEBUG);
setup_logging();


// Autoloader for controllers, models, middleware
/*
spl_autoload_register(function($class) {
    $dirs = [
        //__DIR__ . '/../controllers/',
        //__DIR__ . '/../model/',
        //__DIR__ . '/../middleware/',
        //__DIR__ . '/../lib/',
        __DIR__ . '/vendor/', // if you add vendor libs
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (is_readable($file)) {
            include_once $file;
            return;
        }
    }
});*/
//spl_autoload_register('thirdPartyAutoloader');


/** 
 * Build our own spl_outoload_register like call
 * Recursively build a map of basename => full file path for all .php files
 * under the given directories.
 *
 * @param string[] $dirs  List of base directories to scan recursively.
 * @return array          [ 'Basename' => '/full/path/to/Basename.php', ... ]
 */
function build_file_map(array $dirs): array {
    $map = [];
    foreach ($dirs as $dir) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() === 'php') {
                $name = $file->getBasename('.php');
                // For views, include subdirectory as key
                $relative = str_replace(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $key = str_replace(['/', '\\', '.php'], ['/', '/', ''], $relative);
                $map[$key] = $file->getPathname();
            }
        }
    }
    return $map;
}

/*// Folders to scan - Still valid but we are now implementing filemap.php below

$libMap        = build_file_map([ BASE_PATH . '/lib/' ]);
$viewMap       = build_file_map([ BASE_PATH . '/views/' ]);
$modelMap      = build_file_map([ BASE_PATH . '/models/' ]);
$moduleMap     = build_file_map([ BASE_PATH . '/mmodules/' ]);
$middlewareMap = build_file_map([ BASE_PATH . '/middleware/' ]);
$controllerMap = build_file_map([ BASE_PATH . '/controllers/' ]);

// Store in globals
$GLOBALS['libs']        = $libMap;
$GLOBALS['views']       = $viewMap;
$GLOBALS['models']      = $modelMap;
$GLOBALS['modules']     = $moduleMap;
$GLOBALS['middleware']  = $middlewareMap;
$GLOBALS['controllers'] = $controllerMap;*/

require_once BASE_PATH . '/system/filemap.php';

// Optional dev setting. Keep disabled by default for release/performance.
/*if (!defined('FILEMAP_DEBUG_ALWAYS_REBUILD')) {
    define('FILEMAP_DEBUG_ALWAYS_REBUILD', false);
}*/

filemap_boot_globals();


if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', bin2hex(random_bytes(16)));
}
generate_csp_header();

require_once BASE_PATH . '/system/Route.php'; // route(), render_view(), notFound*, etc. Placed here since we are using globals above