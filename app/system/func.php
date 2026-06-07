<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// ── PHP compatibility polyfills ───────────────────────────────────────────────

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, -strlen($needle)) === $needle;
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
        if ($array === []) return true;
        return array_keys($array) === range(0, count($array) - 1);
    }
}

if (!function_exists('get_debug_type')) {
    function get_debug_type($value): string
    {
        if (is_object($value)) return get_class($value);
        if (is_resource($value)) return 'resource (' . get_resource_type($value) . ')';
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
        $microtime   = microtime(true);
        $seconds     = (int)$microtime;
        $nanoseconds = (int)(($microtime - $seconds) * 1_000_000_000);
        if ($as_number) return ($seconds * 1_000_000_000) + $nanoseconds;
        return [$seconds, $nanoseconds];
    }
}

// ── Core loaders ──────────────────────────────────────────────────────────────

function lib(string $name): bool
{
    $name = trim($name, "/ \t\n\r\0\x0B");
    if ($name === '') return false;

    if (!empty($GLOBALS['libs'][$name]) && is_file($GLOBALS['libs'][$name])) {
        require_once $GLOBALS['libs'][$name];
        return true;
    }

    $file = lib_path($name . '.php');
    if (is_file($file)) {
        require_once $file;
        return true;
    }

    return false;
}

function model($name, $module = null): bool
{
    $name = trim($name, "/ \t\n\r\0\x0B");
    if ($name === '') return false;

    // Shortcut: model('blog::Post')
    if (strpos($name, '::') !== false) {
        [$module, $name] = explode('::', $name, 2);
        $module = trim($module, "/ \t\n\r\0\x0B");
        $name   = trim($name,   "/ \t\n\r\0\x0B");
    }

    // Inherit current module context if inside a module controller
    if (($module === null || $module === '') && !empty($GLOBALS['__noclass_current_module'])) {
        $module = (string)$GLOBALS['__noclass_current_module'];
    }

    if ($module !== null && $module !== '') {
        $module = sanitizeIdentifierFast($module);
        $name   = sanitizeIdentifierFast($name);

        $moduleFile = modules_base_path($module . '/models/' . $name . '.php');
        if (is_file($moduleFile)) {
            require_once $moduleFile;
            return true;
        }
    }

    if (!empty($GLOBALS['models'][$name]) && is_file($GLOBALS['models'][$name])) {
        require_once $GLOBALS['models'][$name];
        return true;
    }

    $file = model_path($name . '.php');
    if (is_file($file)) {
        require_once $file;
        return true;
    }

    trigger_error('Model not found: ' . ($module ? $module . '::' : '') . $name, E_USER_WARNING);
    return false;
}

// module_model(string $module, string $model) is defined in system/modules.php.
// It checks module_is_active() and uses module_model_path() for proper resolution.
// The thin wrapper that was here has been removed to avoid redeclaration.

function middleware(string $name): bool
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

// ── View data bag ─────────────────────────────────────────────────────────────

/**
 * data() — request-scoped view data store.
 *
 *   data('title', 'Home')        set single
 *   data(['a' => 1, 'b' => 2])   set batch
 *   data('title')                get single
 *   data()                       get all
 */
function data($key = null, $value = null)
{
    if (!isset($GLOBALS['_noclass_view_data']) || !is_array($GLOBALS['_noclass_view_data'])) {
        $GLOBALS['_noclass_view_data'] = [];
    }

    if (is_array($key)) {
        $GLOBALS['_noclass_view_data'] = array_merge($GLOBALS['_noclass_view_data'], $key);
        return null;
    }

    if (is_string($key) && func_num_args() === 2) {
        $GLOBALS['_noclass_view_data'][$key] = $value;
        return null;
    }

    if (is_string($key)) {
        return $GLOBALS['_noclass_view_data'][$key] ?? null;
    }

    return $GLOBALS['_noclass_view_data'];
}

// ── Flash data ────────────────────────────────────────────────────────────────

/**
 * flash() — session-scoped one-time data (auto-clears on read).
 *
 *   flash('success', 'Saved')   set
 *   flash('success')            get once (then clears)
 *   flash_all()                 get all + clear
 */
function flash(string $key = null, $value = null)
{
    if (!isset($_SESSION['_noclass_flash']) || !is_array($_SESSION['_noclass_flash'])) {
        $_SESSION['_noclass_flash'] = [];
    }

    if ($key !== null && func_num_args() === 2) {
        $_SESSION['_noclass_flash'][$key] = $value;
        return null;
    }

    if ($key !== null) {
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

// ── Session helpers ───────────────────────────────────────────────────────────

/**
 * session() — read or write a single $_SESSION key.
 *
 * Mirrors the data() / flash() call style so all three helpers
 * are consistent across the framework.
 *
 *   session('user_id')          get — returns value or null
 *   session('user_id', 42)      set
 *   session('user_id', null)    clear (unsets the key)
 *   session()                   get all — returns full $_SESSION array
 *
 * Never replaces secure_session_start() — the session must already
 * be started by the bootstrap before session() is called.
 *
 * Do NOT store sensitive data (passwords, tokens, raw credentials) in
 * the session beyond what is necessary for authentication state.
 */
function session(string $key = null, $value = null)
{
    if ($key === null) {
        return $_SESSION ?? [];
    }

    // Two arguments supplied → write (set or clear)
    if (func_num_args() >= 2) {
        if ($value === null) {
            unset($_SESSION[$key]);
        } else {
            $_SESSION[$key] = $value;
        }
        return null;
    }

    // One argument → read
    return $_SESSION[$key] ?? null;
}

// ── View override helpers ─────────────────────────────────────────────────────
// Fix: originals stored state in $_SESSION causing bleed across requests.
// Now use request-scoped $GLOBALS.

function setControllerView(string $viewName): void
{
    $GLOBALS['_noclass_controller_view'] = $viewName;
}

function getControllerView(): ?string
{
    if (!empty($GLOBALS['_noclass_controller_view'])) {
        $v = $GLOBALS['_noclass_controller_view'];
        unset($GLOBALS['_noclass_controller_view']);
        return $v;
    }
    return null;
}

function setViewPath(string $path): void
{
    $GLOBALS['_noclass_view_path'] = $path;
}

function getViewPath(): ?string
{
    if (!empty($GLOBALS['_noclass_view_path'])) {
        $v = $GLOBALS['_noclass_view_path'];
        unset($GLOBALS['_noclass_view_path']);
        return $v;
    }
    return null;
}

function disableView(): void
{
    $GLOBALS['_noclass_view_disabled'] = true;
}

// ── Controller/action context ─────────────────────────────────────────────────
// Fix: originals stored in $_SESSION causing bleed across requests.
// Now use request-scoped $GLOBALS.

function controller(?string $controllerName = null): ?string
{
    if ($controllerName !== null) {
        $GLOBALS['_noclass_current_controller'] = $controllerName;
    }
    return $GLOBALS['_noclass_current_controller'] ?? null;
}

function action(?string $actionName = null): ?string
{
    if ($actionName !== null) {
        $GLOBALS['_noclass_current_action'] = $actionName;
    }
    return $GLOBALS['_noclass_current_action'] ?? null;
}

// ── Output helpers ────────────────────────────────────────────────────────────

/**
 * e() — echo a value safely escaped for HTML output.
 * Note: this function ECHOES (void). Use ev() to return the escaped string.
 */
function e($value): void
{
    echo htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * ev() — return a safely escaped string (does not echo).
 * Use this in attribute values, variable assignments, etc.
 */
function ev($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Logging ───────────────────────────────────────────────────────────────────

/**
 * logEvent() — write a message to the security log.
 * Fix: original hardcoded __DIR__/../logs/ ignoring BASE_PATH and APP_LOG_PATH.
 */
function logEvent(string $eventMessage): void
{
    $logFile = defined('APP_LOG_PATH')
        ? APP_LOG_PATH
        : storage_path('logs/app.log');

    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $eventMessage . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function setup_logging(): void
{
    if (!defined('APP_LOG') || !APP_LOG) return;

    $path = defined('APP_LOG_PATH') ? dirname(APP_LOG_PATH) : storage_path('logs');

    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }

    ini_set('log_errors',  '1');
    ini_set('error_log',   $path . '/app.log');
}

function thirdPartyAutoloader(string $className): void
{
    $file = BASE_PATH . '/vendor/' . str_replace('_', '/', $className) . '.php';
    if (file_exists($file)) require_once $file;
}

// ── Path helpers ──────────────────────────────────────────────────────────────

function base_path(string $path = ''): string
{
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', realpath(__DIR__ . '/..'));
    }
    $base = rtrim(BASE_PATH, '/\\');
    $path = ltrim($path,     '/\\');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
}

function system_path(string $path = ''): string     { return base_path('system'      . ($path ? '/' . $path : '')); }
function config_path(string $path = ''): string     { return base_path('config'      . ($path ? '/' . $path : '')); }
function controller_path(string $path = ''): string { return base_path('controllers' . ($path ? '/' . $path : '')); }
function model_path(string $path = ''): string      { return base_path('models'      . ($path ? '/' . $path : '')); }
// module_path(string $module, string $path) is defined in system/modules.php
// with the full two-argument module-aware signature. That is the canonical version.
// This helper is kept as modules_base_path() for internal path building only.
function modules_base_path(string $path = ''): string { return base_path('modules' . ($path ? '/' . $path : '')); }
function view_path(string $path = ''): string       { return base_path('views'       . ($path ? '/' . $path : '')); }
function lib_path(string $path = ''): string        { return base_path('lib'         . ($path ? '/' . $path : '')); }
function middleware_path(string $path = ''): string { return base_path('middleware'  . ($path ? '/' . $path : '')); }
function vendor_path(string $path = ''): string     { return base_path('vendor'      . ($path ? '/' . $path : '')); }
function storage_path(string $path = ''): string    { return base_path('storage'     . ($path ? '/' . $path : '')); }

function public_path(string $path = ''): string
{
    if (defined('PUBLIC_PATH')) {
        $base = rtrim(PUBLIC_PATH, '/\\');
        $path = ltrim($path,       '/\\');
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__), '/\\');
    $path = ltrim($path, '/\\');
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
}

// ── URL helpers ───────────────────────────────────────────────────────────────

function has_scheme(string $url): bool
{
    $url = strtolower(trim($url));
    return strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0;
}

/**
 * base_url() — absolute URL using BASE_URL env/config only.
 * Does NOT prepend BASE_URI. Use url() for internal app links.
 *
 * Returns the scheme + host + port portion of BASE_URL only.
 * Any path component in BASE_URL is intentionally ignored here
 * because url() handles the full path via BASE_URI. This prevents
 * the double-path bug when BASE_URL includes the subfolder:
 *
 *   BASE_URL = http://localhost/myapp  ← developer includes subfolder
 *   BASE_URI = /myapp                  ← set by index.php from SCRIPT_NAME
 *
 *   Before fix: url('x') = http://localhost/myapp/myapp/x  ← doubled
 *   After fix:  url('x') = http://localhost/myapp/x        ← correct
 *
 * For raw absolute URLs that need the full BASE_URL path (e.g. API
 * callbacks, external references), use base_url_full() instead.
 */
function base_url(string $path = ''): string
{
    $configured = trim((string)env('BASE_URL', ''), '/');

    if ($configured === '') {
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $origin = (is_https() ? 'https://' : 'http://') . $host;
    } else {
        if (!has_scheme($configured)) {
            $configured = (is_https() ? 'https://' : 'http://') . $configured;
        }
        // Extract origin (scheme + host + optional port) only.
        // Strip any path component so BASE_URI is not doubled when
        // developers set BASE_URL=http://localhost/myapp.
        $parsed = parse_url($configured);
        $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost');
        if (!empty($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }
    }

    $origin = rtrim($origin, '/');
    $path   = trim($path, '/');

    return $path === '' ? $origin : $origin . '/' . $path;
}

/**
 * base_url_full() — absolute URL using the complete BASE_URL value including
 * any configured path component. Use this for external references, API
 * callbacks, or any URL that must exactly match what BASE_URL is set to.
 * Do NOT use for internal app links — use url() instead.
 */
function base_url_full(string $path = ''): string
{
    $base = trim((string)env('BASE_URL', ''), '/');

    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $host;
    }

    if (!has_scheme($base)) {
        $base = (is_https() ? 'https://' : 'http://') . $base;
    }

    $base = rtrim($base, '/');
    $path = trim($path, '/');

    return $path === '' ? $base : $base . '/' . $path;
}

/**
 * url() — internal app URL, correctly prepends BASE_URI for subfolder deploys.
 *
 * Always use url() for links within the application. It handles subfolder
 * deployments correctly regardless of whether BASE_URL includes the
 * subfolder path or not.
 *
 * Examples (BASE_URI = /myapp):
 *   url('')           → http://localhost/myapp
 *   url('products')   → http://localhost/myapp/products
 *   url('products?customer_id=1') → http://localhost/myapp/products?customer_id=1
 */
function url(string $path = ''): string
{
    // Preserve query string — split path from query before processing
    $query = '';
    if (strpos($path, '?') !== false) {
        [$path, $query] = explode('?', $path, 2);
    }

    $path    = trim($path, '/');
    $baseUri = defined('BASE_URI') ? trim(BASE_URI, '/') : '';
    $full    = trim($baseUri . '/' . $path, '/');
    $result  = base_url($full);

    return $query !== '' ? $result . '?' . $query : $result;
}

/**
 * _asset_origin() — build the base origin for assets (CDN or BASE_URL+BASE_URI).
 */
function _asset_origin(bool $force_https = false): string
{
    if (defined('CDN_URL') && CDN_URL) {
        $origin = rtrim(CDN_URL, '/');
    } else {
        $base   = rtrim(base_url(), '/');
        $uri    = (defined('BASE_URI') && BASE_URI) ? '/' . trim(BASE_URI, '/') : '';
        $origin = $base . $uri;
    }

    if ($force_https) {
        $origin = preg_replace('#^http://#i', 'https://', $origin);
        if (strpos($origin, '//') === 0) $origin = 'https:' . $origin;
    }

    return $origin;
}

function _asset_version(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..')) return '';

    $file = base_path(
        trim(ASSET_PATH, '/\\') . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $path)
    );

    return is_file($file) ? (string)filemtime($file) : '';
}

/**
 * asset() — URL to a public asset.
 *
 * Fix: original called base_url($rel) directly, bypassing BASE_URI.
 * This caused wrong asset URLs in subfolder deployments.
 * Now routes through url() so BASE_URI is always prepended.
 */
function asset(string $path = '', string $version = ''): string
{
    $path      = trim(str_replace('\\', '/', $path), '/');
    $assetPath = trim(ASSET_PATH, '/');

    $rel = $path === '' ? $assetPath : $assetPath . '/' . $path;

    // Route through url() not base_url() — url() prepends BASE_URI
    $url = (defined('CDN_URL') && CDN_URL)
        ? rtrim(CDN_URL, '/') . '/' . $rel
        : url($rel);

    if ($path !== '' && defined('ASSET_CACHE_BUST') && ASSET_CACHE_BUST) {
        $ver = $version !== '' ? $version : _asset_version($path);
        if ($ver !== '') {
            $url .= (str_contains($url, '?') ? '&v=' : '?v=') . rawurlencode($ver);
        }
    }

    return $url;
}

/**
 * asset_raw() — asset URL without cache-busting.
 * Fix: same BASE_URI issue as asset() — routes through url().
 */
function asset_raw(string $path = ''): string
{
    $path      = trim(str_replace('\\', '/', $path), '/');
    $assetPath = trim(ASSET_PATH, '/');
    $rel       = $path === '' ? $assetPath : $assetPath . '/' . $path;

    return (defined('CDN_URL') && CDN_URL)
        ? rtrim(CDN_URL, '/') . '/' . $rel
        : url($rel);
}

/**
 * secure_asset() — asset URL forced to HTTPS with cache-busting.
 */
function secure_asset(string $path = '', string $version = ''): string
{
    $url = asset($path, $version);
    return strpos($url, 'http://') === 0 ? 'https://' . substr($url, 7) : $url;
}

// ── HMVC helpers ──────────────────────────────────────────────────────────────

function module_url(string $module, string $key = '', string $action = 'index', array $params = []): string
{
    $module = trim($module);
    if ($module === '') return url('');

    $module = sanitizeIdentifierFast($module);
    $key    = $key    !== '' ? sanitizeIdentifierFast($key)    : '';
    $action = $action !== '' ? sanitizeIdentifierFast($action) : 'index';

    $parts = ['m', $module];
    if ($key !== '')    $parts[] = trim($key, '/');
    $parts[] = $action !== '' ? trim($action, '/') : 'index';

    foreach ($params as $p) $parts[] = rawurlencode((string)$p);

    return url(implode('/', $parts));
}

function module_asset(string $module, string $path = ''): string
{
    $module = trim($module);
    if ($module === '') return asset(ltrim($path, '/'));
    $module = sanitizeIdentifierFast($module);
    return asset('modules/' . $module . '/' . ltrim($path, '/'));
}

/**
 * hmvc() — run a module controller action internally and return its output.
 * Format: "{module}/{controller}/{action}"
 */
function hmvc(string $target, array $params = [])
{
    static $depth = 0;
    $depth++;

    if ($depth > 10) {
        $depth--;
        throw new RuntimeException('HMVC recursion limit reached');
    }

    $target = trim($target, '/');
    $parts  = $target === '' ? [] : explode('/', $target);

    if (count($parts) < 2) {
        $depth--;
        throw new RuntimeException('Invalid HMVC target. Use "{module}/{controller}/{action}".');
    }

    $module     = sanitizeIdentifierFast($parts[0]);
    $controller = sanitizeIdentifierFast($parts[1]);
    $action     = sanitizeIdentifierFast($parts[2] ?? 'index');

    // Module allowlist
    $modulesAllowFile = base_path('config/modules.php');
    if (is_file($modulesAllowFile)) {
        $allowed = require $modulesAllowFile;
        if (!is_array($allowed) || empty($allowed[$module])) {
            $depth--;
            throw new RuntimeException("Module disabled or not allowed: {$module}");
        }
    }

    // Validate against module routes if available
    $moduleRoutesFile = base_path("modules/{$module}/config/routes.php");
    if (is_file($moduleRoutesFile)) {
        $routes = require $moduleRoutesFile;
        if (is_array($routes)) {
            foreach ($routes as $def) {
                if (!is_array($def)) continue;
                if (strtolower($def['controller'] ?? '') === strtolower($controller)) {
                    $allowedActions = (array)($def['action'] ?? []);
                    $ok = false;
                    foreach ($allowedActions as $a) {
                        if (strtolower((string)$a) === strtolower($action)) { $ok = true; break; }
                    }
                    if (!$ok) {
                        $depth--;
                        throw new RuntimeException("HMVC action not allowed: {$module}/{$controller}/{$action}");
                    }
                    break;
                }
            }
        }
    }

    $controllerFile = base_path("modules/{$module}/controllers/{$controller}.php");
    if (!is_file($controllerFile)) {
        $depth--;
        throw new RuntimeException("HMVC controller not found: {$module}/{$controller}");
    }

    require_once $controllerFile;

    $fn = strtolower($controller . '_' . $action);
    if (!function_exists($fn)) {
        $depth--;
        throw new RuntimeException("HMVC action function not found: {$fn}");
    }

    ob_start();
    call_user_func($fn, $params);
    $output = ob_get_clean();

    $depth--;
    return $output;
}

function module(string $target, array $params = [])
{
    return hmvc($target, $params);
}

function module_view(string $module, string $view = '', array $data = [], bool $return = false)
{
    if (strpos($module, '::') !== false) {
        [$module, $view] = explode('::', $module, 2);
    }

    $module = trim($module, "/ \t\n\r\0\x0B");
    $view   = trim($view,   "/ \t\n\r\0\x0B");

    if ($module === '' || $view === '') return $return ? '' : null;

    $module = sanitizeIdentifierFast($module);

    if (str_contains($view, '..')) {
        trigger_error("Invalid module view path: {$view}", E_USER_WARNING);
        return $return ? '' : null;
    }

    $file = modules_base_path($module . '/views/' . $view . '.php');
    if (!is_file($file)) return $return ? '' : null;

    ob_start();
    $__data = $GLOBALS['_noclass_view_data'] ?? [];
    if (is_array($__data)) extract($__data, EXTR_SKIP);
    if (!empty($data)) extract($data, EXTR_SKIP);
    require $file;
    $html = ob_get_clean();

    if ($return) return $html;
    echo $html;
    return null;
}

// ── Identifier sanitization ───────────────────────────────────────────────────

function sanitizeIdentifierFast(string $input): string
{
    $result = '';
    $length = strlen($input);
    for ($i = 0; $i < $length; $i++) {
        $ord = ord($input[$i]);
        if (($ord >= 48 && $ord <= 57)  ||   // 0-9
            ($ord >= 65 && $ord <= 90)  ||   // A-Z
            ($ord >= 97 && $ord <= 122) ||   // a-z
            $ord === 95 || $ord === 45) {    // _ or -
            $result .= $input[$i];
        }
    }
    return $result;
}

function sanitizeIdentifierAdvanced(string $input, array $options = []): string
{
    $opts = array_merge([
        'allowUnderscore' => true,
        'allowHyphen'     => true,
        'maxLength'       => null,
        'throwException'  => false,
    ], $options);

    if ($opts['maxLength'] !== null && strlen($input) > $opts['maxLength']) {
        $input = substr($input, 0, $opts['maxLength']);
    }

    $result = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $ord = ord($input[$i]);
        if (($ord >= 48 && $ord <= 57) || ($ord >= 65 && $ord <= 90) || ($ord >= 97 && $ord <= 122)) {
            $result .= $input[$i];
        } elseif ($ord === 95 && $opts['allowUnderscore']) {
            $result .= $input[$i];
        } elseif ($ord === 45 && $opts['allowHyphen']) {
            $result .= $input[$i];
        }
    }

    if ($result === '' && $opts['throwException']) {
        throw new InvalidArgumentException('Input contains no valid identifier characters after sanitization');
    }

    return $result;
}
