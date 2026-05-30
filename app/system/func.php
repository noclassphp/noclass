<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

/*
|--------------------------------------------------------------------------
| PHP Compatibility Functions
|--------------------------------------------------------------------------
|
| These functions provide compatibility for older PHP versions.
| If the native PHP function already exists, NoClass will use the native
| implementation automatically.
|
*/

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);

        return substr($haystack, -$length) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}

if (!function_exists('get_debug_type')) {
    function get_debug_type($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }

        if (is_resource($value)) {
            return 'resource (' . get_resource_type($value) . ')';
        }

        return gettype($value);
    }
}

if (!function_exists('is_countable')) {
    function is_countable($value): bool
    {
        return is_array($value) || $value instanceof Countable;
    }
}

if (!function_exists('hrtime')) {
    function hrtime(bool $as_number = false)
    {
        $microtime = microtime(true);
        $seconds = (int) $microtime;
        $nanoseconds = (int) (($microtime - $seconds) * 1000000000);

        if ($as_number) {
            return ($seconds * 1000000000) + $nanoseconds;
        }

        return [$seconds, $nanoseconds];
    }
}

/**
 * Dynamically load a library by its basename.
 *
 * Relies on $GLOBALS['libs'] being an array of e.g:
 *   [ 'email' => 'C:\www\NoClass\lib\email.php', ... ]
 *
 * @param string $name  e.g. 'email'
 * @return bool         True on include; false if not found
 */
/*function lib(string $name): bool
{
    // 1) Fetch the map
    $map = $GLOBALS['libs'] ?? [];

    // 2) Check if that library name exists in the map
    if (!array_key_exists($name, $map)) {
        trigger_error("Library not found in map: {$name}", E_USER_WARNING);
        return false;
    }

    // 3) Include the actual file path
    $file = $map[$name];
    if (!is_readable($file)) {
        trigger_error("Library file not readable: {$file}", E_USER_WARNING);
        return false;
    }

    include_once $file;
    return true;
}*/

function lib(string $name): bool
{
    $name = trim($name, "/ \t\n\r\0\x0B");
    if ($name === '') return false;

    // map lookup first
    if (!empty($GLOBALS['libs'][$name]) && is_file($GLOBALS['libs'][$name])) {
        require_once $GLOBALS['libs'][$name];
        return true;
    }

    // fallback direct path
    $file = lib_path($name . '.php');
    if (is_file($file)) {
        require_once $file;
        return true;
    }

    return false;
}

/**
 * Dynamically load a model by its basename.
 *
 * Uses $GLOBALS['models'] which should map e.g:
 *   [ 'User' => 'C:\www\NoClass\model\User.php', ... ]
 *
 * @param string $name  e.g. 'User'
 * @return bool
 */
/*function model(string $name): bool
{
    $map = $GLOBALS['models'] ?? [];
    if (!array_key_exists($name, $map)) {
        trigger_error("Model not found: {$name}", E_USER_WARNING);
        return false;
    }
    $file = $map[$name];
    if (!is_readable($file)) {
        trigger_error("Model file not readable: {$file}", E_USER_WARNING);
        return false;
    }
    include_once $file;
    return true;
}*/
function model($name, $module = null): bool
{
    $name = trim($name, "/ \t\n\r\0\x0B");
    if ($name === '') return false;

    // Shortcut: model('blog::Post')
    if (strpos($name, '::') !== false) {
        [$module, $name] = explode('::', $name, 2);
        $module = trim($module, "/ \t\n\r\0\x0B");
        $name   = trim($name, "/ \t\n\r\0\x0B");
    }

    // If called inside a module controller, default to that module.
    // This allows model('Post') to resolve modules/blog/models/Post.php first.
    if (($module === null || $module === '') && !empty($GLOBALS['__noclass_current_module'])) {
        $module = (string)$GLOBALS['__noclass_current_module'];
    }

    if ($module !== null && $module !== '') {
        if (function_exists('sanitizeIdentifierFast')) {
            $module = sanitizeIdentifierFast($module);
            $name   = sanitizeIdentifierFast($name);
        }

        $moduleFile = module_path($module . '/models/' . $name . '.php');
        if (is_file($moduleFile)) {
            require_once $moduleFile;
            return true;
        }
    }

    // Root model fallback keeps old MVC behavior working.
    if (!empty($GLOBALS['models'][$name]) && is_file($GLOBALS['models'][$name])) {
        require_once $GLOBALS['models'][$name];
        return true;
    }

    $file = model_path($name . '.php');
    if (is_file($file)) {
        require_once $file;
        return true;
    }

    trigger_error("Model not found: " . ($module ? $module . '::' : '') . $name, E_USER_WARNING);
    return false;
}

function module_model($module, $name): bool
{
    return model($name, $module);
}


/*function load_middleware(string $name) {
    $map = $GLOBALS['middleware'] ?? [];
    if (!isset($map[$name])) {
        trigger_error("Middleware not found: {$name}", E_USER_ERROR);
    }
    require_once $map[$name];
    return $name;
}*/
function middleware($name): bool
{
    $name = trim($name, "/ \t\n\r\0\x0B");
    if ($name === '') return false;

    if (!empty($GLOBALS['middleware'][$name]) && is_file($GLOBALS['middleware'][$name])) {
        require_once $GLOBALS['middleware'][$name];
        return true;
    }

    $file = middleware_path($name . '.php');
    if (is_file($file)) {
        require_once $file;
        return true;
    }

    return false;
}

/**
 * data()
 *
 * Official NoClass view-data helper (Option A).
 *
 * Usage:
 *   data('title', 'Home');        // set single key
 *   data(['a' => 1, 'b' => 2]);   // set multiple keys
 *   data('title');                // get single key
 *   data();                       // get all (array)
 *
 * Notes:
 * - Request-scoped (does NOT persist between requests)
 * - NOT flash data (does NOT auto-clear)
 * - Values are auto-extracted into view scope before rendering
 */
function data($key = null, $value = null)
{
    // init store once per request
    if (!isset($GLOBALS['_noclass_view_data']) || !is_array($GLOBALS['_noclass_view_data'])) {
        $GLOBALS['_noclass_view_data'] = [];
    }

    // Setter: batch
    if (is_array($key)) {
        $GLOBALS['_noclass_view_data'] = array_merge($GLOBALS['_noclass_view_data'], $key);
        return null;
    }

    // Setter: single key
    if (is_string($key) && func_num_args() === 2) {
        $GLOBALS['_noclass_view_data'][$key] = $value;
        return null;
    }

    // Getter: single key
    if (is_string($key) && func_num_args() === 1) {
        return $GLOBALS['_noclass_view_data'][$key] ?? null;
    }

    // Getter: all
    if ($key === null) {
        return $GLOBALS['_noclass_view_data'];
    }

    return null;
}


/**
 * flash()
 *
 * Session-scoped, one-time data (auto-clears on read).
 *
 * Usage:
 *   flash('success', 'Saved'); // set
 *   flash('success');         // get once (then clears)
 *   flash_all();              // get all + clear
 */
function flash(string $key = null, $value = null)
{
    if (!isset($_SESSION['_noclass_flash']) || !is_array($_SESSION['_noclass_flash'])) {
        $_SESSION['_noclass_flash'] = [];
    }

    // Setter
    if ($key !== null && func_num_args() === 2) {
        $_SESSION['_noclass_flash'][$key] = $value;
        return null;
    }

    // Getter (single, clears)
    if ($key !== null && func_num_args() === 1) {
        if (!array_key_exists($key, $_SESSION['_noclass_flash'])) return null;
        $val = $_SESSION['_noclass_flash'][$key];
        unset($_SESSION['_noclass_flash'][$key]);
        return $val;
    }

    return null;
}

function flash_all(): array
{
    $all = $_SESSION['_noclass_flash'] ?? [];
    $_SESSION['_noclass_flash'] = [];
    return is_array($all) ? $all : [];
}


/**
 * Override the view filename within the current controller folder.
 * e.g. setControllerView('custom') will render views/ControllerName/custom.php
 */
function setControllerView(string $viewName): void {
    $_SESSION['controller_view'] = $viewName;
}

/**
 * Get and clear the controller-specific override.
 */
function getControllerView(){
    if (isset($_SESSION['controller_view'])) {
        $v = $_SESSION['controller_view'];
        unset($_SESSION['controller_view']);
        return $v;
    }
    return null;
}


/**
 * Override the view by full relative path under /views.
 * e.g. setViewPath('shared/header') will render views/shared/header.php
 */
function setViewPath(string $path): void {
    $_SESSION['view_path'] = $path;
}

/**
 * Get and clear the full view override.
 */
function getViewPath(){
    if (isset($_SESSION['view_path'])) {
        $v = $_SESSION['view_path'];
        unset($_SESSION['view_path']);
        return $v;
    }
    return null;
}

function disableView(){

}

function controller($controllerName = null){

    if($controllerName !== null){
        $_SESSION['controllerName'] = $controllerName;
    }

    return isset($_SESSION['controllerName'])? $_SESSION['controllerName'] : null;
}

function action($actionName = null){

    if($actionName !== null){
        $_SESSION['actionName'] = $actionName;
    }

    return isset($_SESSION['actionName'])? $_SESSION['actionName'] : null;
}

function setHeader($headerPath){
     $_SESSION['header_path'] = $headerPath;
}

function getHeader(){
     return isset($_SESSION['header_path']) ? $_SESSION['header_path'] : null;
}

function setFooter($footerPath){
     $_SESSION['footer_path'] = $footerPath;
}

function getFooter(){
     return isset($_SESSION['footer_path']) ? $_SESSION['footer_path'] : null;
}

/*function sanitizeInput($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}*/

function logEvent($eventMessage) {
    $logFile = __DIR__ . '/../logs/security.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $eventMessage . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function thirdPartyAutoloader($className) {
    // Assume third-party classes are in the "vendor/" directory
    //$file = __DIR__ . '/../vendor/' . str_replace('\\', '/', $className) . '.php'; // The normal Way

    // Replace underscores with directory separators for custom class loading
    $file = __DIR__ . '/../vendor/' . str_replace('_', '/', $className) . '.php';  // going by our user_getAll() method type 

    if (file_exists($file)) {
        require_once $file;
    } else {
        echo "Third-party class $className not found.";
    }
}

/*function vendor($library) {
    // Convert library name to match directory structure
    $file = __DIR__ . '/../vendor/' . str_replace('_', '/', $library) . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        echo "Vendor library $library not found.";
    }
}*/

/**
 * Escape a string for safe HTML output.
 *
 * Usage in views:
 *   <?= e($variable) ?>
 *
 * @param mixed $value  The value to escape (will be cast to string)
 * @return string       The escaped, safe HTML string
 */
function ev($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/**
 * Print an escaped string.
 */
function e($value): void {
    echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


// Usage:
//vendor('ThirdPartyLibrary_SomeClass'); // This will load vendor/ThirdPartyLibrary/SomeClass.php

// Then you can instantiate it:
//$lib = new ThirdPartyLibrary_SomeClass();


/*function test(){
    echo 'Echo Test...';  // Just a test to echo something
}*/


// ---------- Core Path Helpers (BASE_PATH-safe) ----------

function base_path(string $path = ''): string
{
    if (!defined('BASE_PATH')) {
        // Last resort fallback (legacy)
        define('BASE_PATH', realpath(__DIR__ . '/..'));
    }

    $base = rtrim(BASE_PATH, '/\\');
    $path = ltrim($path, '/\\');

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
}

function system_path(string $path = ''): string      { return base_path('system' . ($path ? '/' . $path : '')); }
function config_path(string $path = ''): string      { return base_path('config' . ($path ? '/' . $path : '')); }
function controller_path(string $path = ''): string  { return base_path('controllers' . ($path ? '/' . $path : '')); }
function model_path(string $path = ''): string       { return base_path('models' . ($path ? '/' . $path : '')); }
function module_path(string $path = ''): string      { return base_path('modules' . ($path ? '/' . $path : '')); }
function view_path(string $path = ''): string        { return base_path('views' . ($path ? '/' . $path : '')); }
function lib_path(string $path = ''): string         { return base_path('lib' . ($path ? '/' . $path : '')); }
function middleware_path(string $path = ''): string  { return base_path('middleware' . ($path ? '/' . $path : '')); }
function vendor_path(string $path = ''): string      { return base_path('vendor' . ($path ? '/' . $path : '')); }

// Optional: storage folder (logs/cache/uploads outside public)
function storage_path(string $path = ''): string     { return base_path('storage' . ($path ? '/' . $path : ''));}

/**
 * Web root (public) is not always BASE_PATH. For shared hosts:
 * - Option 1: public_html is outside BASE_PATH
 * - Option 2/legacy: public is inside BASE_PATH
 *
 * So we set PUBLIC_PATH explicitly (recommended) OR infer from index.php location.
 */
function public_path(string $path = ''): string
{
    if (defined('PUBLIC_PATH')) {
        $base = rtrim(PUBLIC_PATH, '/\\');
        $path = ltrim($path, '/\\');
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }

    // fallback: assume index.php is the public entry; set by index.php ideally
    $base = rtrim(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__), '/\\');
    $path = ltrim($path, '/\\');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
}

// ---------- URL Helpers ----------


// → internal / relative routes
function url(string $path = ''): string
{
    $path = trim($path, '/');
    $baseUri = defined('BASE_URI') ? trim(BASE_URI, '/') : '';

    $fullPath = trim($baseUri . '/' . $path, '/');

    return base_url($fullPath);
}

function has_scheme(string $url): bool
{
    $url = strtolower(trim($url));

    return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
}

// → absolute / external / canonical
/**
 * Generate an application URL.
 *
 * Examples:
 * base_url()
 * base_url('about')
 * base_url('assets/css/app.css')
 */
function base_url(string $path = ''): string
{
    $base = trim((string) env('BASE_URL', ''), '/');

    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $host;
    }

    if (!has_scheme($base)) {
        $scheme = is_https() ? 'https://' : 'http://';
        $base = $scheme . $base;
    }

    $base = rtrim($base, '/');
    $path = trim($path, '/');

    return $path === '' ? $base : $base . '/' . $path;
}



// ---------- Module Helpers (HMVC) ----------

/**
 * Build an internal URL to a module route using the standard NoClass HMVC prefix:
 *   /m/{module}/{key}/{action}/{params...}
 *
 * Examples:
 *   module_url('blog', 'post', 'view', [12]) => /m/blog/post/view/12
 *   module_url('shop', 'cart')              => /m/shop/cart/index
 */
function module_url(string $module, string $key = '', string $action = 'index', array $params = []): string
{
    $module = trim($module);
    if ($module === '') {
        return url('');
    }

    // Basic hardening (keeps it fast and avoids weird paths)
    if (function_exists('sanitizeIdentifierFast')) {
        $module = sanitizeIdentifierFast($module);
        $key    = $key !== '' ? sanitizeIdentifierFast($key) : '';
        $action = $action !== '' ? sanitizeIdentifierFast($action) : 'index';
    }

    $parts = ['m', $module];

    if ($key !== '') {
        $parts[] = trim($key, '/');
    }
    $parts[] = $action !== '' ? trim($action, '/') : 'index';

    foreach ($params as $p) {
        $parts[] = rawurlencode((string)$p);
    }

    return url(implode('/', $parts));
}

/**
 * module_asset(): Convenience wrapper for module-owned public assets, if you choose to ship assets per module.
 *
 * Convention:
 *   /{ASSET_PATH}/modules/{module}/{path}
 *
 * Example:
 *   module_asset('blog', 'css/blog.css') => https://site/assets/modules/blog/css/blog.css?v=...
 */
function module_asset(string $module, string $path = ''): string
{
    $module = trim($module);
    if ($module === '') {
        return asset(ltrim($path, '/'));
    }

    if (function_exists('sanitizeIdentifierFast')) {
        $module = sanitizeIdentifierFast($module);
    }

    $path = ltrim($path, '/');
    return asset('modules/' . $module . '/' . $path);
}

/**
 * hmvc(): Run a module controller action internally (no HTTP) and return its output.
 *
 * Target format:
 *   "{module}/{controller}/{action}"
 *
 * Examples:
 *   hmvc('blog/post/recent', ['limit' => 5])
 *   hmvc('shop/cart/index')
 *
 * Notes:
 * - Looks for: modules/{module}/controllers/{Controller}.php
 * - Calls function: strtolower("{controller}_{action}")
 * - Captures output buffer and returns HTML/text output.
 * - Recursion guard prevents accidental infinite loops.
 */
function hmvc(string $target, array $params = [])
{
    static $depth = 0;
    $depth++;

    if ($depth > 10) {
        $depth--;
        throw new Exception('HMVC recursion limit reached');
    }

    $target = trim($target, '/');
    $parts  = $target === '' ? [] : explode('/', $target);

    if (count($parts) < 2) {
        $depth--;
        throw new Exception('Invalid HMVC target. Use "{module}/{controller}/{action}".');
    }

    $module     = $parts[0];
    $controller = $parts[1];
    $action     = $parts[2] ?? 'index';

    if (function_exists('sanitizeIdentifierFast')) {
        $module     = sanitizeIdentifierFast($module);
        $controller = sanitizeIdentifierFast($controller);
        $action     = sanitizeIdentifierFast($action);
    }

    // Optional module allowlist (recommended). If config/modules.php exists, require module=true.
    $modulesAllowFile = base_path('config/modules.php');
    if (is_file($modulesAllowFile)) {
        $allowed = require $modulesAllowFile;
        if (!is_array($allowed) || empty($allowed[$module])) {
            $depth--;
            throw new Exception("Module is disabled or not allowed: {$module}");
        }
    }

    // If module routes config exists, optionally validate controller/action.
    $moduleRoutesFile = base_path("modules/{$module}/config/routes.php");
    $routes = null;
    if (is_file($moduleRoutesFile)) {
        $routes = require $moduleRoutesFile;
        if (!is_array($routes)) {
            $routes = null;
        }
    }

    // Resolve controller file
    $controllerFile = base_path("modules/{$module}/controllers/{$controller}.php");
    if (!is_file($controllerFile)) {
        $depth--;
        throw new Exception("HMVC controller not found: {$module}/{$controller}");
    }

    require_once $controllerFile;

    // NoClass convention: controller_action
    $fn = strtolower($controller . '_' . $action);

    // If routes exist, try to validate the key->controller mapping and action allowlist.
    if (is_array($routes)) {
        // Find a route key whose 'controller' matches this controller name (case-insensitive)
        $matched = null;
        foreach ($routes as $k => $def) {
            if (!is_array($def)) continue;
            $c = $def['controller'] ?? '';
            if ($c !== '' && strtolower($c) === strtolower($controller)) {
                $matched = $def;
                break;
            }
        }

        if ($matched) {
            $allowedActions = $matched['action'] ?? [];
            if (is_string($allowedActions)) {
                $allowedActions = [$allowedActions];
            }
            if (is_array($allowedActions) && !empty($allowedActions)) {
                $ok = false;
                foreach ($allowedActions as $a) {
                    if (strtolower((string)$a) === strtolower($action)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    $depth--;
                    throw new Exception("HMVC action not allowed by module routes: {$module}/{$controller}/{$action}");
                }
            }
        }
    }

    if (!function_exists($fn)) {
        $depth--;
        throw new Exception("HMVC action not found: {$fn}");
    }

    ob_start();
    // Pass params as the first argument (common NoClass pattern)
    call_user_func($fn, $params);
    $output = ob_get_clean();

    $depth--;
    return $output;
}

/**
 * module(): Friendly alias for hmvc().
 * Use module('blog/post/recent', ['limit'=>5]) for internal module rendering.
 */
function module(string $target, array $params = [])
{
    return hmvc($target, $params);
}


/**
 * module_view(): Render a view from a module.
 *
 * Examples:
 *   module_view('blog', 'post/recent', ['posts' => $posts]);
 *   $html = module_view('blog', 'post/recent', ['posts' => $posts], true);
 *   module_view('blog::post/recent', ['posts' => $posts]);
 */
function module_view($module, $view = '', $data = [], $return = false)
{
    // Shortcut form: module_view('blog::post/recent', ['posts' => $posts])
    if (strpos($module, '::') !== false) {
        [$module, $view] = explode('::', $module, 2);
    }

    $module = trim($module, "/ \t\n\r\0\x0B");
    $view   = trim($view, "/ \t\n\r\0\x0B");

    if ($module === '' || $view === '') {
        return $return ? '' : null;
    }

    if (function_exists('sanitizeIdentifierFast')) {
        $module = sanitizeIdentifierFast($module);
    }

    if (strpos($view, '..') !== false) {
        trigger_error("Invalid module view path: {$view}", E_USER_WARNING);
        return $return ? '' : null;
    }

    $file = module_path($module . '/views/' . $view . '.php');
    if (!is_file($file)) {
        return $return ? '' : null;
    }

    ob_start();

    $__data = $GLOBALS['_noclass_view_data'] ?? [];
    if (is_array($__data)) {
        extract($__data, EXTR_SKIP);
    }
    if (is_array($data)) {
        extract($data, EXTR_SKIP);
    }

    require $file;
    $html = ob_get_clean();

    if ($return) {
        return $html;
    }

    echo $html;
    return null;
}


/*function asset(string $path=''): string
{
    return base_url(ASSET_PATH . '/' . ltrim($path, '/'));
}*/


// Optional (recommended) if your assets live under /public
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', rtrim(BASE_PATH, '/\\') . '/public');
}

/**
 * Internal: build the base origin for assets (CDN if set, else BASE_URL + BASE_URI).
 */
function _asset_origin(bool $force_https = false): string
{
    // Prefer CDN
    if (defined('CDN_URL') && CDN_URL) {
        $origin = rtrim(CDN_URL, '/');
    } else {
        $base = rtrim(BASE_URL, '/');
        $uri  = (defined('BASE_URI') && BASE_URI) ? '/' . trim(BASE_URI, '/') : '';
        $origin = $base . $uri;
    }

    if ($force_https) {
        // Force scheme to https (handles http://example.com, //example.com, etc.)
        $origin = preg_replace('#^http://#i', 'https://', $origin);
        if (strpos($origin, '//') === 0) {
            $origin = 'https:' . $origin;
        }
    }

    return $origin;
}

/**
 * Internal: get a cache-busting version for an asset (filemtime) if file exists locally.
 * Returns null when file can't be resolved.
 */
function _asset_version(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');

    if ($path === '') {
        return '';
    }

    if (str_contains($path, '..')) {
        return '';
    }

    $file = rtrim(BASE_PATH, '/\\')
        . DIRECTORY_SEPARATOR
        . trim(ASSET_PATH, '/\\')
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $path);

    return is_file($file) ? (string) filemtime($file) : '';
}

function asset(string $path = '', string $version = ''): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    $assetPath = trim(ASSET_PATH, '/');

    if ($path === '') {
        return defined('CDN_URL') && CDN_URL
            ? rtrim(CDN_URL, '/') . '/' . $assetPath
            : base_url($assetPath);
    }

    $rel = $assetPath . '/' . $path;

    $url = defined('CDN_URL') && CDN_URL
        ? rtrim(CDN_URL, '/') . '/' . $rel
        : base_url($rel);

    if (defined('ASSET_CACHE_BUST') && ASSET_CACHE_BUST) {
        $ver = $version !== '' ? $version : _asset_version($path);

        if ($ver !== '') {
            $url .= (strpos($url, '?') === false ? '?v=' : '&v=') . rawurlencode($ver);
        }
    }

    return $url;
}


/**
 * asset_raw(): absolute URL to asset WITHOUT cache-busting
 */
function asset_raw(string $path = ''): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    $assetPath = trim(ASSET_PATH, '/');

    $rel = $path === ''
        ? $assetPath
        : $assetPath . '/' . $path;

    return defined('CDN_URL') && CDN_URL
        ? rtrim(CDN_URL, '/') . '/' . $rel
        : base_url($rel);
}

/**
 * secure_asset(): absolute URL to asset (FORCED HTTPS) + cache-busting (?v=filemtime)
 */
function secure_asset(string $path = '', string $version = ''): string
{
    $url = asset($path, $version);

    if (str_starts_with($url, 'http://')) {
        return 'https://' . substr($url, 7);
    }

    return $url;
}

/**
 * Filters a string to allow only alphanumeric, underscore, and hyphen characters
 * using ASCII range checking (fastest method)
 * 
 * @param string $input The input string to filter
 * @return string Filtered string containing only a-zA-Z0-9_-
 */
function sanitizeIdentifierFast(string $input): string {
    $result = '';
    $length = strlen($input);
    
    for ($i = 0; $i < $length; $i++) {
        $char = $input[$i];
        $ord = ord($char);
        
        // Check ASCII ranges: 0-9, A-Z, a-z, '_' (95), '-' (45)
        if (($ord >= 48 && $ord <= 57) ||    // 0-9
            ($ord >= 65 && $ord <= 90) ||    // A-Z
            ($ord >= 97 && $ord <= 122) ||   // a-z
            $ord === 95 || $ord === 45) {    // '_' or '-'
            $result .= $char;
        }
    }
    
    return $result;
}

// Usage:
//$stringSafe = sanitizeIdentifierFast($stringSafe);

/**
 * Advanced identifier sanitization with validation and options
 * 
 * @param string $input The input string to sanitize
 * @param array $options {
 *     @type string   $method    'fast' or 'lookup' (default: 'fast')
 *     @type bool     $allowUnderscore  Allow '_' character (default: true)
 *     @type bool     $allowHyphen      Allow '-' character (default: true)
 *     @type int|null $maxLength        Maximum length (default: null, no limit)
 *     @type bool     $throwException   Throw exception on empty result (default: false)
 * }
 * @return string Sanitized identifier
 * @throws InvalidArgumentException If validation fails and $throwException is true
 */
function sanitizeIdentifierAdvanced(string $input, array $options = []): string {
    $defaults = [
        'method' => 'fast',
        'allowUnderscore' => true,
        'allowHyphen' => true,
        'maxLength' => null,
        'throwException' => false,
    ];
    
    $options = array_merge($defaults, $options);
    
    // Apply max length if specified
    if ($options['maxLength'] !== null && strlen($input) > $options['maxLength']) {
        $input = substr($input, 0, $options['maxLength']);
    }
    
    $result = '';
    $length = strlen($input);
    
    if ($options['method'] === 'lookup') {
        // Build allowed characters based on options
        $allowed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($options['allowUnderscore']) $allowed .= '_';
        if ($options['allowHyphen']) $allowed .= '-';
        
        static $lookupCache = [];
        $cacheKey = ($options['allowUnderscore'] ? '1' : '0') . ($options['allowHyphen'] ? '1' : '0');
        
        if (!isset($lookupCache[$cacheKey])) {
            $lookupCache[$cacheKey] = array_flip(str_split($allowed));
        }
        
        $allowedChars = $lookupCache[$cacheKey];
        
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if (isset($allowedChars[$char])) {
                $result .= $char;
            }
        }
    } else {
        // Fast ASCII range method
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            $ord = ord($char);
            
            // Check alphanumeric ranges
            if (($ord >= 48 && $ord <= 57) ||    // 0-9
                ($ord >= 65 && $ord <= 90) ||    // A-Z
                ($ord >= 97 && $ord <= 122)) {   // a-z
                $result .= $char;
            }
            // Check special characters based on options
            elseif (($ord === 95 && $options['allowUnderscore']) ||   // '_'
                    ($ord === 45 && $options['allowHyphen'])) {       // '-'
                $result .= $char;
            }
        }
    }
    
    // Validation
    if ($result === '' && $options['throwException']) {
        throw new InvalidArgumentException(
            'Input contains no valid identifier characters after sanitization'
        );
    }
    
    return $result;
}

// Usage examples:
// $id1 = sanitizeIdentifierAdvanced('my-table_id123');
// $id2 = sanitizeIdentifierAdvanced('my-table_id123', ['maxLength' => 10]);
// $id3 = sanitizeIdentifierAdvanced('my-table_id123', ['allowHyphen' => false]);


function setup_logging(): void
{
    if (!APP_LOG) {
        return;
    }

    $path = BASE_PATH . '/logs';

    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    ini_set('log_errors', '1');
    ini_set('error_log', $path . '/app.log');
}