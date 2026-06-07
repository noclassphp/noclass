<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 *
 * Purpose:
 * - Provide procedural helpers for discovering, validating, enabling,
 *   reading, and loading NoClass HMVC modules.
 * - Keep modules self-contained and drop-in friendly.
 * - Keep central config/routes.php stable; root route aliases remain optional.
 *
 * This file does NOT define application modules.
 * Modules live under:
 *
 *   modules/{module}/
 *
 * Recommended module structure:
 *
 *   modules/blog/
 *       module.php
 *       config/
 *           routes.php
 *           permissions.php
 *           menus.php
 *       migrations/
 *       controllers/
 *       models/
 *       middleware/
 *       lib/
 *       views/
 *       assets/
 *
 * Optional active module registry:
 *
 *   config/modules.php
 *
 * Supported registry formats:
 *
 *   return [
 *       'linkforge' => true,
 *       'blog'      => false,
 *   ];
 *
 * or:
 *
 *   return [
 *       'linkforge',
 *       'userforge',
 *   ];
 *
 * Important behaviour:
 * - If config/modules.php exists, only listed/enabled modules are active.
 * - If config/modules.php does not exist, discovered modules are considered
 *   active by default for backward compatibility and drop-in use.
 * - Module names are restricted to safe slugs: a-z, 0-9, underscore, dash.
 * - No classes are used.
 */

if (!defined('NOCLASS_MODULE_SYSTEM_LOADED')) {
    define('NOCLASS_MODULE_SYSTEM_LOADED', true);
}

/**
 * Resolve project base path in a way that works with existing NoClass helpers
 * but still remains safe if this file is loaded early.
 */
function module_base_path(string $path = ''): string
{
    if (function_exists('base_path')) {
        return base_path($path);
    }

    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $path = ltrim($path, '/\\');

    return $path === '' ? rtrim($base, '/\\') : rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $path;
}

/**
 * Absolute path to the modules directory or a path inside it.
 */
function modules_path(string $path = ''): string
{
    $path = ltrim($path, '/\\');
    return module_base_path('modules' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
}

/**
 * Validate a module slug.
 *
 * Allowed examples:
 * - linkforge
 * - user_forge
 * - pay-forge
 * - blog2
 */
function module_valid_name(string $module): bool
{
    $module = trim($module);

    if ($module === '') {
        return false;
    }

    if ($module === '.' || $module === '..') {
        return false;
    }

    if (strpos($module, '/') !== false || strpos($module, '\\') !== false) {
        return false;
    }

    return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $module);
}

/**
 * Sanitise a module name into a safe slug.
 */
function module_sanitize_name(string $module): string
{
    $module = trim($module);
    $module = str_replace(['/', '\\', '..'], '', $module);
    $module = preg_replace('/[^a-zA-Z0-9_-]/', '', $module);

    return (string) $module;
}

/**
 * Return the absolute path to a module root.
 */
function module_path(string $module, string $path = ''): ?string
{
    $module = module_sanitize_name($module);

    if (!module_valid_name($module)) {
        return null;
    }

    $path = ltrim($path, '/\\');
    $base = modules_path($module);

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
}

/**
 * Check whether a module folder exists.
 */
function module_exists(string $module): bool
{
    $path = module_path($module);

    return $path !== null && is_dir($path);
}

/**
 * Discover available module folders.
 *
 * This only scans first-level directories under modules/ and ignores invalid
 * names and dot folders.
 */
function module_discover_all(bool $withInactive = true): array
{
    $dir = modules_path();

    if (!is_dir($dir)) {
        return [];
    }

    static $cache = [];
    $key = $withInactive ? 'all' : 'active';

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $modules = [];
    $items = scandir($dir);

    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (!module_valid_name($item)) {
            continue;
        }

        if (!is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
            continue;
        }

        if (!$withInactive && !module_is_active($item)) {
            continue;
        }

        $modules[] = $item;
    }

    sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
    $cache[$key] = $modules;

    return $modules;
}

/**
 * Load config/modules.php if present.
 *
 * Returns null when the registry file does not exist.
 * Returns an array when the file exists.
 */
function module_registry(): ?array
{
    static $registryLoaded = false;
    static $registry = null;

    if ($registryLoaded) {
        return $registry;
    }

    $registryLoaded = true;
    $file = module_base_path('config/modules.php');

    if (!file_exists($file)) {
        $registry = null;
        return null;
    }

    $data = include $file;
    $registry = is_array($data) ? $data : [];

    return $registry;
}

/**
 * Determine whether the application has an explicit module registry.
 */
function module_registry_exists(): bool
{
    return module_registry() !== null;
}

/**
 * Check whether a module is active.
 *
 * Behaviour:
 * - If config/modules.php does not exist: existing module folders are active.
 * - If config/modules.php exists: only enabled/listed modules are active.
 */
function module_is_active(string $module): bool
{
    $module = module_sanitize_name($module);

    if (!module_valid_name($module) || !module_exists($module)) {
        return false;
    }

    $registry = module_registry();

    if ($registry === null) {
        return true;
    }

    if (array_key_exists($module, $registry)) {
        return (bool) $registry[$module];
    }

    if (in_array($module, $registry, true)) {
        return true;
    }

    return false;
}

/**
 * Backward-compatible alias for existing router code.
 *
 * Current Route.php may call is_module_enabled($module). This wrapper lets the
 * router use the fuller module system without requiring immediate route edits.
 */
if (!function_exists('is_module_enabled')) {
    function is_module_enabled(string $module): bool
    {
        return module_is_active($module);
    }
}

/**
 * Return all active modules.
 */
function module_active_all(): array
{
    return module_discover_all(false);
}

/**
 * Read module metadata from modules/{module}/module.php.
 */
function module_meta(string $module): array
{
    $module = module_sanitize_name($module);

    if (!module_valid_name($module) || !module_exists($module)) {
        return [];
    }

    static $cache = [];

    if (isset($cache[$module])) {
        return $cache[$module];
    }

    $file = module_path($module, 'module.php');

    if ($file === null || !file_exists($file)) {
        $cache[$module] = [
            'slug' => $module,
            'name' => ucfirst(str_replace(['-', '_'], ' ', $module)),
            'version' => '',
            'description' => '',
            'author' => '',
        ];

        return $cache[$module];
    }

    $data = include $file;

    if (!is_array($data)) {
        $data = [];
    }

    $data['slug'] = isset($data['slug']) && module_valid_name((string) $data['slug'])
        ? (string) $data['slug']
        : $module;

    $data['name'] = isset($data['name']) && trim((string) $data['name']) !== ''
        ? (string) $data['name']
        : ucfirst(str_replace(['-', '_'], ' ', $module));

    $data += [
        'version' => '',
        'description' => '',
        'author' => '',
        'requires' => [],
    ];

    $cache[$module] = $data;

    return $cache[$module];
}

/**
 * Return metadata for all discovered modules.
 */
function module_meta_all(bool $activeOnly = true): array
{
    $modules = $activeOnly ? module_active_all() : module_discover_all(true);
    $out = [];

    foreach ($modules as $module) {
        $out[$module] = module_meta($module);
        $out[$module]['active'] = module_is_active($module);
    }

    return $out;
}

/**
 * Generic loader for module config files.
 *
 * Example:
 *   module_config('linkforge', 'permissions')
 * loads:
 *   modules/linkforge/config/permissions.php
 */
function module_config(string $module, string $name, array $default = []): array
{
    $module = module_sanitize_name($module);
    $name = module_sanitize_name($name);

    if (!module_valid_name($module) || !module_exists($module) || !module_valid_name($name)) {
        return $default;
    }

    static $cache = [];
    $key = $module . ':' . $name;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $file = module_path($module, 'config/' . $name . '.php');

    if ($file === null || !file_exists($file)) {
        $cache[$key] = $default;
        return $cache[$key];
    }

    $data = include $file;
    $cache[$key] = is_array($data) ? $data : $default;

    return $cache[$key];
}

/**
 * Load module routes.
 *
 * This mirrors the current NoClass route format and is intentionally simple.
 * If Route.php already defines load_module_routes(), this function will not be
 * redeclared because of the function_exists guard.
 */
if (!function_exists('load_module_routes')) {
    function load_module_routes(string $module): array
    {
        if (!module_is_active($module)) {
            return [];
        }

        return module_config($module, 'routes', []);
    }
}

/**
 * Preferred naming wrapper for loading module routes.
 */
function module_load_routes(string $module): array
{
    if (function_exists('load_module_routes')) {
        return load_module_routes($module);
    }

    return module_config($module, 'routes', []);
}

/**
 * Load module permissions from modules/{module}/config/permissions.php.
 */
function module_load_permissions(string $module): array
{
    if (!module_is_active($module)) {
        return [];
    }

    return module_config($module, 'permissions', []);
}

/**
 * Load module menus from modules/{module}/config/menus.php.
 */
function module_load_menus(string $module): array
{
    if (!module_is_active($module)) {
        return [];
    }

    return module_config($module, 'menus', []);
}

/**
 * Aggregate permissions from all active modules.
 */
function module_permissions_all(): array
{
    $out = [];

    foreach (module_active_all() as $module) {
        $permissions = module_load_permissions($module);

        if (!empty($permissions)) {
            $out[$module] = $permissions;
        }
    }

    return $out;
}

/**
 * Aggregate menus from all active modules.
 */
function module_menus_all(): array
{
    $out = [];

    foreach (module_active_all() as $module) {
        $menus = module_load_menus($module);

        if (!empty($menus)) {
            $out[$module] = $menus;
        }
    }

    return $out;
}

/**
 * Return module controller path.
 */
function module_controller_path(string $module, string $controller = ''): ?string
{
    $controller = trim($controller);
    $path = $controller === '' ? 'controllers' : 'controllers/' . basename($controller) . '.php';

    return module_path($module, $path);
}

/**
 * Return module model path.
 */
function module_model_path(string $module, string $model = ''): ?string
{
    $model = trim($model);
    $path = $model === '' ? 'models' : 'models/' . basename($model) . '.php';

    return module_path($module, $path);
}

/**
 * Return module middleware path.
 */
function module_middleware_path(string $module, string $middleware = ''): ?string
{
    $middleware = trim($middleware);
    $path = $middleware === '' ? 'middleware' : 'middleware/' . basename($middleware) . '.php';

    return module_path($module, $path);
}

/**
 * Return module library path.
 */
function module_lib_path(string $module, string $lib = ''): ?string
{
    $lib = trim($lib);
    $path = $lib === '' ? 'lib' : 'lib/' . basename($lib) . '.php';

    return module_path($module, $path);
}

/**
 * Return module view path.
 */
function module_view_path(string $module, string $view = ''): ?string
{
    $view = trim($view, '/\\');
    $path = $view === '' ? 'views' : 'views/' . $view;

    if ($view !== '' && substr($path, -4) !== '.php') {
        $path .= '.php';
    }

    return module_path($module, $path);
}

/**
 * Return module asset path.
 */
function module_asset_path(string $module, string $asset = ''): ?string
{
    $asset = ltrim($asset, '/\\');
    $path = $asset === '' ? 'assets' : 'assets/' . $asset;

    return module_path($module, $path);
}

/**
 * Load a module model file.
 */
function module_model(string $module, string $model): bool
{
    if (!module_is_active($module)) {
        return false;
    }

    $file = module_model_path($module, $model);

    if ($file !== null && file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
}

/**
 * Load a module library file.
 */
function module_lib(string $module, string $lib): bool
{
    if (!module_is_active($module)) {
        return false;
    }

    $file = module_lib_path($module, $lib);

    if ($file !== null && file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
}

/**
 * Load a module middleware file.
 */
function module_middleware(string $module, string $middleware): bool
{
    if (!module_is_active($module)) {
        return false;
    }

    $file = module_middleware_path($module, $middleware);

    if ($file !== null && file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
}

/**
 * Get/set current module context.
 *
 * Route.php already uses $GLOBALS['__noclass_current_module']; this helper gives
 * controllers/views a clean procedural accessor.
 */
function module_current(?string $module = null): ?string
{
    if ($module !== null) {
        $module = module_sanitize_name($module);

        if (module_valid_name($module)) {
            $GLOBALS['__noclass_current_module'] = $module;
        }
    }

    return $GLOBALS['__noclass_current_module'] ?? null;
}

/**
 * Clear current module context.
 */
function module_clear_current(): void
{
    unset($GLOBALS['__noclass_current_module']);
}

/**
 * Build canonical module URL path.
 *
 * Example:
 *   module_canonical_url('linkforge', 'dashboard/index')
 * returns:
 *   http://localhost/m/linkforge/dashboard/index
 *
 * Note: module_url() in system/func.php provides a richer version with
 * key/action/params arguments for full HMVC dispatch URL building.
 * Use this simpler helper when you just need a path string.
 */
function module_canonical_url(string $module, string $path = ''): string
{
    $module = module_sanitize_name($module);
    $path = trim($path, '/');
    $uri = 'm/' . $module . ($path !== '' ? '/' . $path : '');

    if (function_exists('url')) {
        return url($uri);
    }

    $base = defined('BASE_URI') && BASE_URI !== '' ? '/' . trim(BASE_URI, '/') : '';

    return $base . '/' . $uri;
}

/**
 * Check simple module requirements declared in module.php.
 *
 * Supported currently:
 * - requires.php, e.g. ['php' => '>=8.1']
 * - requires.modules, e.g. ['modules' => ['userforge']]
 *
 * Returns:
 *   ['ok' => true, 'errors' => []]
 *   ['ok' => false, 'errors' => [...]]
 */
function module_check_requirements(string $module): array
{
    $meta = module_meta($module);
    $requires = $meta['requires'] ?? [];
    $errors = [];

    if (!is_array($requires)) {
        return ['ok' => true, 'errors' => []];
    }

    if (isset($requires['php']) && is_string($requires['php'])) {
        $constraint = trim($requires['php']);

        if ($constraint !== '') {
            $operator = '>=';
            $version = $constraint;

            foreach (['>=', '<=', '>', '<', '==', '='] as $op) {
                if (strpos($constraint, $op) === 0) {
                    $operator = $op === '=' ? '==' : $op;
                    $version = trim(substr($constraint, strlen($op)));
                    break;
                }
            }

            if ($version !== '' && !version_compare(PHP_VERSION, $version, $operator)) {
                $errors[] = 'Requires PHP ' . $constraint . '; current version is ' . PHP_VERSION . '.';
            }
        }
    }

    if (isset($requires['modules']) && is_array($requires['modules'])) {
        foreach ($requires['modules'] as $requiredModule) {
            $requiredModule = module_sanitize_name((string) $requiredModule);

            if (!module_is_active($requiredModule)) {
                $errors[] = 'Requires active module: ' . $requiredModule . '.';
            }
        }
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Return active modules with requirement status.
 */
function module_status_all(): array
{
    $out = [];

    foreach (module_discover_all(true) as $module) {
        $out[$module] = [
            'active' => module_is_active($module),
            'meta' => module_meta($module),
            'requirements' => module_check_requirements($module),
            'paths' => [
                'root' => module_path($module),
                'routes' => module_path($module, 'config/routes.php'),
                'permissions' => module_path($module, 'config/permissions.php'),
                'menus' => module_path($module, 'config/menus.php'),
            ],
        ];
    }

    return $out;
}

/**
 * Runtime-only enable/disable helpers.
 *
 * These helpers intentionally do not rewrite config/modules.php because NoClass
 * should not silently modify configuration files at runtime. They are useful for
 * admin panels that store module states in DB later, or for request-scoped tests.
 */
function module_runtime_enable(string $module): bool
{
    $module = module_sanitize_name($module);

    if (!module_valid_name($module) || !module_exists($module)) {
        return false;
    }

    $GLOBALS['__noclass_runtime_modules'][$module] = true;

    return true;
}

function module_runtime_disable(string $module): bool
{
    $module = module_sanitize_name($module);

    if (!module_valid_name($module)) {
        return false;
    }

    $GLOBALS['__noclass_runtime_modules'][$module] = false;

    return true;
}

/**
 * Optional runtime-aware active check.
 *
 * Use this only if you later want DB/admin-panel driven module state. The main
 * module_is_active() remains config-driven for predictability.
 */
function module_is_runtime_active(string $module): bool
{
    $module = module_sanitize_name($module);

    if (isset($GLOBALS['__noclass_runtime_modules'][$module])) {
        return (bool) $GLOBALS['__noclass_runtime_modules'][$module];
    }

    return module_is_active($module);
}

/**
 * Preload lightweight module data for all active modules.
 *
 * This is useful from setup.php if you want permissions and menus available
 * globally without touching routes.php.
 */
function module_boot_active(): array
{
    $booted = [];

    foreach (module_active_all() as $module) {
        $requirements = module_check_requirements($module);

        $booted[$module] = [
            'meta' => module_meta($module),
            'requirements' => $requirements,
            'permissions' => $requirements['ok'] ? module_load_permissions($module) : [],
            'menus' => $requirements['ok'] ? module_load_menus($module) : [],
        ];
    }

    $GLOBALS['__noclass_modules'] = $booted;

    return $booted;
}

/**
 * Read booted module data.
 */
function module_booted(?string $module = null)
{
    $booted = $GLOBALS['__noclass_modules'] ?? [];

    if ($module === null) {
        return $booted;
    }

    $module = module_sanitize_name($module);

    return $booted[$module] ?? null;
}
