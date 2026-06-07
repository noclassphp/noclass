<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

/**
 * Load module routes config (modules/<module>/config/routes.php) with the same caching strategy as root routes.
 * Returns an array in the same format as config/routes.php.
 */
// load_module_routes() — guarded to avoid redeclaration when modules.php
// is loaded before Route.php (which it is, via setup.php).
// If modules.php has already defined it, that version is used — it delegates
// to module_config() and checks module_is_active() correctly.
    function load_module_routes(string $module): array
    {
        $module = trim($module);
        if ($module === '') {
            return [];
        }

        $cfg = base_path('modules/' . $module . '/config/routes.php');
        if (!file_exists($cfg)) {
            return [];
        }

        static $cache = [];
        $mtime  = filemtime($cfg) ?: 0;
        $mapKey = "routes.module.{$module}.{$mtime}";

        // in-process cache
        if (isset($cache[$mapKey])) {
            return $cache[$mapKey];
        }

        $mapJson = (CACHING === CACHE_ENGINE)
            ? cache_get_versioned($mapKey)
            : (CACHING === CACHE_FILE ? fileCacheLoad($mapKey) : null);

        if ($mapJson !== null) {
            $routes = json_decode($mapJson, true);
            if (is_array($routes)) {
                $cache[$mapKey] = $routes;
                return $routes;
            }
        }

        $routes = include $cfg;
        if (!is_array($routes)) {
            $routes = [];
        }

        $mapJson = json_encode($routes);
        if (CACHING === CACHE_ENGINE) {
            cache_set_versioned($mapKey, $mapJson, 0);
        } elseif (CACHING === CACHE_FILE) {
            fileCacheSave($mapKey, $mapJson, 0);
        }

        $cache[$mapKey] = $routes;
        return $routes;
    }

/**
 * Optional module allowlist.
 * If config/modules.php exists, only modules explicitly enabled (truthy) are allowed.
 * If it doesn't exist, all modules are allowed (backward compatible).
 */
// is_module_enabled() — guarded to avoid redeclaration when modules.php
// is loaded before Route.php. modules.php provides the fuller version that
// delegates to module_is_active() and supports both registry formats
// (map and list). If already defined, that version takes precedence.
    function is_module_enabled(string $module): bool
    {
        $module = trim($module);
        if ($module === '') return false;

        $cfg = base_path('config/modules.php');
        if (!file_exists($cfg)) {
            return true; // no allowlist configured
        }

        static $enabled = null;
        if ($enabled === null) {
            $tmp = include $cfg;
            $enabled = is_array($tmp) ? $tmp : [];
        }

        // If present in map, obey it; if not present, treat as disabled
        // (secure-by-default when allowlist exists)
        if (array_key_exists($module, $enabled)) {
            return (bool)$enabled[$module];
        }

        return false;
    }

/**
 * Resolve middleware file path (supports module middleware + app middleware).
 */
function resolve_middleware_file(string $name, string $module = null): string
{
    $name = basename($name);
    if ($name === '') return null;

    if ($module) {
        $mf = base_path('modules/' . $module . '/middleware/' . $name . '.php');
        if (file_exists($mf)) return $mf;
    }

    // prefer map if present
    $mwMap = $GLOBALS['middleware'] ?? [];
    if (isset($mwMap[$name]) && file_exists($mwMap[$name])) {
        return $mwMap[$name];
    }

    $af = base_path('middleware/' . $name . '.php');
    if (file_exists($af)) return $af;

    return null;
}

function route() {
    // 1. Read and normalize the URL
    $requestUrl = isset($_GET['url']) ? trim($_GET['url'], '/') : '';

    // Fix: strip BASE_URI prefix for subfolder deployments.
    // When Apache rewrites /forgehub/login to index.php?url=forgehub/login,
    // $_GET['url'] contains the subfolder prefix. Without this strip,
    // $segments[0] becomes 'forgehub' instead of 'login' and all routes 404.
    if (defined('BASE_URI') && BASE_URI !== '') {
        $baseUriStrip = trim(BASE_URI, '/');
        if ($baseUriStrip !== '' && strpos($requestUrl, $baseUriStrip . '/') === 0) {
            $requestUrl = substr($requestUrl, strlen($baseUriStrip) + 1);
        } elseif ($requestUrl === $baseUriStrip) {
            $requestUrl = '';
        }
    }

    $segments = $requestUrl === '' ? [] : explode('/', $requestUrl);

     // ──────────────── Load & cache the routes map ────────────────
    static $routes = null;
    if ($routes === null) {
        //$cfg      = __DIR__ . '/../config/routes.php';
        $cfg      = base_path('config/routes.php');
        $mtime    = file_exists($cfg) ? filemtime($cfg) : 0;
        $mapKey   = "routes.map.{$mtime}";

        $mapJson = (CACHING === CACHE_ENGINE)
            ? cache_get_versioned($mapKey)
            : (CACHING === CACHE_FILE ? fileCacheLoad($mapKey) : null);

        if ($mapJson !== null) {
            $routes = json_decode($mapJson, true);
        } else {
            $routes = include $cfg;
            $mapJson = json_encode($routes);
            if (CACHING === CACHE_ENGINE) {
                cache_set_versioned($mapKey, $mapJson, 0);
            } elseif (CACHING === CACHE_FILE) {
                fileCacheSave($mapKey, $mapJson, 0);
            }
        }
    }
    //var_dump($routes);

    // Prepare cache key for this URL’s dispatch
    $urlKey   = '/' . implode('/', $segments);
    $cacheKey = 'route.dispatch.' . md5($urlKey);   

    // Try to load cached dispatch (controller/action/params/etc.)
    $cached = null;
    switch (CACHING) {
        case CACHE_ENGINE:
            $cached = cache_get($cacheKey);
            break;
        case CACHE_FILE:
            $cached = fileCacheLoad($cacheKey);
            break;
    }

    //echo '<br>CacheKey:'.$cacheKey;
    //echo '<br>Cached:'.$cached;
    //echo '</br>';

    if ($cached !== null) {
        // We have a cached dispatch; skip the routing logic
        $dispatch = json_decode($cached, true);

        // Include controller and middleware, then call action
        $module = $dispatch['module'] ?? null;

        // ── Restore route layout from cached dispatch ──────────────────────
        $GLOBALS['_noclass_route_layout'] = $dispatch['layout'] ?? null;

        // Reset per-request view state for cached path too
        if (function_exists('_noclass_reset_view_state')) {
            _noclass_reset_view_state();
        }

        // 1) Include controller file (module-aware)
        if (!empty($dispatch['controller_file']) && file_exists($dispatch['controller_file'])) {
            require_once $dispatch['controller_file'];
        } else {
            $ctrlMap = $GLOBALS['controllers'] ?? [];
            if (!isset($ctrlMap[$dispatch['controller']])) {
                notFoundController(); return;
            }
            require_once $ctrlMap[$dispatch['controller']];
        }

        // 2) Run middlewares (supports args + module middleware)
        if (!run_middlewares($dispatch['middleware'], $module)) {
            return;
        }
// ✅ Enforce allow-list even when dispatch is cached
        if (!in_array(strtolower($dispatch['action']), array_map('strtolower', $dispatch['actionNames']), true)) {
            notFoundAction();
            return;
        }

        if (function_exists($dispatch['action'])) {
            $previousModule = $GLOBALS['__noclass_current_module'] ?? null;
            if ($module) {
                $GLOBALS['__noclass_current_module'] = $module;
            } else {
                unset($GLOBALS['__noclass_current_module']);
            }

            $content = call_user_func_array(
                $dispatch['action'],
                $dispatch['params']
            );

            // AUTO JSON for fetch/AJAX
            if (function_exists('is_ajax') && is_ajax()) {
                    if (is_array($content)) {
                        $status = 200;
                        if (isset($content['_status'])) {
                            $status = (int)$content['_status'];
                            unset($content['_status']);
                        }
                        respond_json($content, $status);
                    }

                    if (is_string($content) && $content !== '') {
                        respond_json(['ok' => true, 'data' => $content], 200);
                    }
                    // if null, fall through to renderView
                }

                render_route_view(
                    $dispatch['controller'],
                    $dispatch['action'],
                    $content,
                    $dispatch['actionNames'],
                    $module
                );

                if ($previousModule !== null) {
                    $GLOBALS['__noclass_current_module'] = $previousModule;
                } else {
                    unset($GLOBALS['__noclass_current_module']);
                }
        } else {
            notFoundAction();
        }
        return;
    }

    // —————————————————————————————————————————————————————————————
    // 2. Load routes (whitelist keys) + HMVC module routes
    $module = null;
    $activeRoutes = $routes;
    $segmentsForMatch = $segments;
    //var_dump($segmentsForMatch);


    // A) Canonical module HTTP routing: /m/{module}/{key}/{action}/{...}
    if (isset($segments[0]) && strtolower($segments[0]) === 'm') {
        $module = $segments[1] ?? '';
        if ($module === '' || !is_module_enabled($module)) {
            notFoundPage(); return;
        }

        // Try find a mount key in root routes for this module (Option A)
        $mountKey  = null;
        $mountDecl = null;

        foreach ($routes as $k => $cfg) {
            if (is_array($cfg) && isset($cfg['module']) && strtolower((string)$cfg['module']) === strtolower((string)$module)) {
                $mountKey  = $k;      // e.g. 'blog'
                $mountDecl = $cfg;    // mount config
                break;
            }
        }

        // Prefer alias redirect ONLY if module is mounted in root routes
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $preferAlias = true;
        // Optional: allow turning it off per module mount
        if (is_array($mountDecl) && array_key_exists('prefer', $mountDecl)) {
            $preferAlias = ($mountDecl['prefer'] === 'alias' || $mountDecl['prefer'] === true);
        }

        if ($mountKey && $preferAlias && ($method === 'GET' || $method === 'HEAD')) {

            // /m/{module}/post/view/12  ->  /{mountKey}/post/view/12
            $aliasParts = array_merge([$mountKey], array_slice($segments, 2));

            $base = '';
            if (defined('BASE_URI') && BASE_URI !== '') {
                $base = '/' . trim(BASE_URI, '/');
            }

            $to = $base . '/' . implode('/', $aliasParts);
            $to = preg_replace('#/+#', '/', $to);

            // preserve query string EXCEPT the router param "url"
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if ($qs !== '') {
                parse_str($qs, $q);
                if (isset($q['url'])) {
                    unset($q['url']); // critical: prevent rewrite+QSA duplication loop
                }
                $qs2 = http_build_query($q);
                if ($qs2 !== '') {
                    $to .= '?' . $qs2;
                }
            }

            header('Location: ' . $to, true, 301);
            exit;
        }

        $activeRoutes = load_module_routes($module);


        // If module has no routes config (or empty), treat as 404 (secure)
        if (empty($activeRoutes)) {
            notFoundPage(); return;
        }

        // For matching we slice off: ['m', '{module}']
        $segmentsForMatch = array_slice($segments, 2);

        // No module-wide middleware in canonical /m/... mode unless you add it later
        $GLOBALS['__module_base_middleware'] = [];
    }
    // B) Optional pretty alias: /{moduleKey}/... where {moduleKey} is declared in root config/routes.php
    // Example:
    //  'blog' => ['module' => 'blog', 'middleware' => [...], 'default' => ['post','index']]
    elseif (!empty($segments[0]) && isset($routes[$segments[0]]) && is_array($routes[$segments[0]]) && isset($routes[$segments[0]]['module'])) {
        $moduleDeclKey = $segments[0];
        $decl = $routes[$moduleDeclKey];

        $module = $decl['module'] ?? '';
        if ($module === '' || !is_module_enabled($module)) {
            notFoundPage(); return;
        }

        $activeRoutes = load_module_routes($module);
        if (empty($activeRoutes)) {
            notFoundPage(); return;
        }

        // Remove the alias key from the path. Remaining segments match module routes.
        $segmentsForMatch = array_slice($segments, 1);

        // If user hits just /blog (no further segments), apply optional default key/action
        // default: ['key','action'] e.g. ['post','index']
        if (empty($segmentsForMatch)) {
            $defKey = 'home';
            $defAction = 'index';

            if (isset($decl['default']) && is_array($decl['default'])) {
                $defKey = $decl['default'][0] ?? $defKey;
                $defAction = $decl['default'][1] ?? $defAction;
            }

            $segmentsForMatch = [$defKey, $defAction];
        }

        // Module-wide middleware from the root declaration is merged later (after we load routeConfig)
        $GLOBALS['__module_base_middleware'] = $decl['middleware'] ?? [];
    } else {
        // Not a module route; normal root routing
        $GLOBALS['__module_base_middleware'] = [];
    }

    $validKeys = array_keys($activeRoutes);

    // 3. Determine controller key (first segment or default from DEFAULT_CONTROLLER)
    // Fix: original hardcoded 'home' — now reads DEFAULT_CONTROLLER constant.
    $defaultKey = defined('DEFAULT_CONTROLLER') ? strtolower(DEFAULT_CONTROLLER) : 'home';
    $key = $segmentsForMatch[0] ?? $defaultKey;

    // 4. Reject any segment with '.' or '/'
    if (strpos($key, '.') !== false || strpos($key, '/') !== false) {
        notFoundPage(); return;
    }

    // 5. Enforce whitelist (root or module)
    if (!in_array($key, $validKeys, true)) {
        notFoundPage(); return;
    }

    // 6. Dispatch daemon route if special (root only)
    if ($module === null && $key === 'start-daemon') {
        exec("php start-daemon.php start > /dev/null 2>&1 &");
        respond_json(['status' => 'Daemon started in the background']);
        return;
    }

    // 7. Load controller config// 7. Load controller config
    //var_dump($validKeys);
    $routeConfig      = $activeRoutes[$key];
    $controllerName   = $routeConfig['controller'];
    $actions          = [];
    $middlewares       = [];
    $overwriteActions = true;

    if (isset($routeConfig['overwrite_actions'])) {
        $overwriteActions = ($routeConfig['overwrite_actions'] == true);
    }

    //$actions  = (array)$routeConfig['action'] ?? [];
    $actions     = $routeConfig['action']     ?? [];
    $middlewares = $routeConfig['middleware'] ?? [];

    // ── Layout declaration ────────────────────────────────────────────────────
    // Read 'layout' from route config.
    //   null          = key absent  → resolution falls through to DEFAULT_LAYOUT / main.php convention
    //   false         = key present as false → suppress layout for this route
    //   'name'        = key present as string → use named layout
    // Stored in globals so render_route_view() can read it after dispatch.
    $routeLayout = array_key_exists('layout', $routeConfig) ? $routeConfig['layout'] : null;
    $GLOBALS['_noclass_route_layout'] = $routeLayout;

    // Reset per-request layout/section state (sections, view_content, view-level overrides).
    // Called here so state is clean for every dispatched request, including cached paths.
    if (function_exists('_noclass_reset_view_state')) {
        _noclass_reset_view_state();
    }



    // Merge module-wide middleware (declared in root routes.php module entry) with module route middleware
    $baseMw = $GLOBALS['__module_base_middleware'] ?? [];
    if (!empty($baseMw)) {
        $middlewares = array_values(array_unique(array_merge($baseMw, $middlewares), SORT_REGULAR));
    }

    // If we are dealing with an alias controller e.g. 'u' for 'user'
    if (strtolower($key) != strtolower($controllerName)) {
        if (in_array(strtolower($controllerName), $validKeys, true)) {
            if (empty($actions)) {
                $actions = $activeRoutes[strtolower($controllerName)]['action'];
            } elseif (! $overwriteActions) {
                $actions = array_unique(array_merge(
                    $activeRoutes[strtolower($controllerName)]['action'],
                    $actions
                ));
            }
            /*if (! empty($activeRoutes[strtolower($controllerName)]['middleware'])) {
                $middlewares = array_unique(array_merge(
                    $activeRoutes[strtolower($controllerName)]['middleware'],
                    $middlewares
                ));
            }*/
            if (!empty($activeRoutes[strtolower($controllerName)]['middleware'])) {
                $middlewares = middleware_merge(
                    $activeRoutes[strtolower($controllerName)]['middleware'],
                    $middlewares
                );
            }
        }
    }

    //echo $controllerName;
    //var_dump($actions);
    //var_dump($middlewares);

    /*// 8. Compute controller file path and include
    $controllerFile = __DIR__ . '/../controllers/' . basename($controllerName) . '.php';
    if (!file_exists($controllerFile)) {
        notFoundController();
        return;
    }
    require_once $controllerFile;

    // 9. Run middleware
    foreach ($middlewares as $m) {
        $mwFile = __DIR__ . '/../middleware/' . basename($m) . '.php';
        if (file_exists($mwFile)) {
            require_once $mwFile;
            if (function_exists($m) && call_user_func($m) === false) {
                unauthorizedAction();
                return;
            }
        }
    }*/

    // 8. Include controller file (module-aware)
    if ($module !== null) {
        $controllerFile = base_path('modules/' . $module . '/controllers/' . basename($controllerName) . '.php');
        if (!file_exists($controllerFile)) {
            notFoundController(); return;
        }
        require_once $controllerFile;
    } else {
        // Root controller via pre-built map
        $ctrlMap = $GLOBALS['controllers'] ?? [];
        if (!isset($ctrlMap[$controllerName])) {
            notFoundController(); return;
        }
        require_once $ctrlMap[$controllerName];
    }

    // Controller file path (for dispatch caching)
    $controllerFileForCache = ($module !== null)
        ? base_path('modules/' . $module . '/controllers/' . basename($controllerName) . '.php')
        : (($GLOBALS['controllers'][$controllerName] ?? null));


    // 9. Execute middleware in order
    /*foreach ($middlewares as $mwName) {
        $mwMap = $GLOBALS['middleware'] ?? [];
        if (!isset($mwMap[$mwName])) {
            trigger_error("Middleware not found: {$mwName}", E_USER_WARNING);
            //unauthorizedAction();
            continue;
        }
        require_once $mwMap[$mwName];
        if (function_exists($mwName) && call_user_func($mwName) === false) {
            unauthorizedAction(); return;
        }
    }*/

    // Expanded to handle arguments in middlewares
    if (!run_middlewares($middlewares ?? [], $module)) {
        return;
    }
    // continue to controller/action

    // 10. Determine action segment (default 'index')
    $actionSeg = $segmentsForMatch[1] ?? 'index';

    //TODO: Is this really needed because of the above line
    if (empty($actionSeg)) {
        $actionSeg = 'index';
    }


    // 11. Match action pattern
    $matched = matchAction($actions, $segmentsForMatch);
    /*if ($matched) {
        $actionName = $matched['action'];
        $params     = $matched['params'];
    } else {
        // fallback: raw action + remaining as params
        $actionName = $actionSeg;
        $params     = array_slice($segmentsForMatch, 2);
    }*/

    if ($matched) {
    $actionName = $matched['action'];
    $params     = $matched['params'];
    } else {
        // ✅ Hardened: only allow fallback if explicitly enabled AND action exists in controller
        if (defined('ALLOW_UNDECLARED_ACTIONS') && ALLOW_UNDECLARED_ACTIONS) {
            $actionName = $actionSeg;
            $params     = array_slice($segmentsForMatch, 2);
        } else {
            notFoundAction();
            return;
        }
    }


    //var_dump($actions);
    //var_dump($matched);

    // Build an array of plain action names:
    $actionNames = array_map(function($pattern) {
        // Extract everything before the first '/'
        return strtolower(strtok($pattern, '/'));
    }, $actions);
    //var_dump($actionNames);

    // ✅ Enforce allow-list: action MUST be declared in routes.php for this controller
    // Note: $actions are patterns, so we validate using $actionNames (derived above)
    if (!in_array(strtolower($actionName), $actionNames, true)) {
        notFoundAction();
        return;
    }

    // 12. Call action if exists
    /*if (function_exists($actionName)) {
        $content = call_user_func_array($actionName, $params);
        render_route_view($controllerName, $actionName, $content, $actionNames);
    } else {
        notFoundAction(); return;
    }*/

    if (function_exists($actionName)) {
    $previousModule = $GLOBALS['__noclass_current_module'] ?? null;
    if ($module) {
        $GLOBALS['__noclass_current_module'] = $module;
    } else {
        unset($GLOBALS['__noclass_current_module']);
    }

    $result = call_user_func_array($actionName, $params);

        // AUTO JSON for fetch/AJAX
        if (function_exists('is_ajax') && is_ajax()) {
            if (is_array($result)) {
                $status = 200;
                if (isset($result['_status'])) {
                    $status = (int)$result['_status'];
                    unset($result['_status']);
                }
                respond_json($result, $status);
            }

            if (is_string($result) && $result !== '') {
                respond_json(['ok' => true, 'data' => $result], 200);
            }
            // if null, fall through to renderView
        }

            render_route_view($controllerName, $actionName, $result, $actionNames, $module);

            if ($previousModule !== null) {
                $GLOBALS['__noclass_current_module'] = $previousModule;
            } else {
                unset($GLOBALS['__noclass_current_module']);
            }
    } else {
        notFoundAction(); return;
    }



    // ───── Cache the dispatch result ─────
    $dispatch = [
        'module'          => $module,
        'controller'      => $controllerName,
        'controller_file' => $controllerFileForCache,
        'action'          => $actionName,
        'params'          => $params,
        'middleware'       => $middlewares,
        'actionNames'     => $actionNames,
        'layout'          => $routeLayout,   // persisted so cached dispatch can restore it
    ];
    $ser = json_encode($dispatch);
    switch (CACHING) {
        case CACHE_ENGINE:
            cache_set($cacheKey, $ser, CACHE_TTL_ROUTE);
            break;
        case CACHE_FILE:
            fileCacheSave($cacheKey, $ser,  CACHE_TTL_ROUTE);
            break;
        // CACHE_NONE: no caching
    }
}


function middleware_key($mw): string
{
    // String form: "Auth" or "Role:admin"
    if (is_string($mw)) {
        return strtolower(trim($mw));
    }

    // Array form: ["Role","admin"] or ["Throttle",10,60]
    if (is_array($mw) && isset($mw[0])) {
        $name = strtolower(trim((string)$mw[0]));
        $args = array_slice($mw, 1);

        // convert args into a stable string
        // (serialize is fine here; json_encode also ok)
        return $name . ':' . md5(serialize($args));
    }

    return 'invalid:' . md5(serialize($mw));
}

function middleware_merge(array $a, array $b): array
{
    $out = [];
    foreach ($a as $mw) {
        $out[middleware_key($mw)] = $mw;
    }
    foreach ($b as $mw) {
        $out[middleware_key($mw)] = $mw;
    }
    return array_values($out);
}


function parse_middleware($mw): array
{
    $name = '';
    $args = [];

    // "Auth" or "Role:admin" or "Throttle:10,60"
    if (is_string($mw)) {
        $mw = trim($mw);
        if ($mw === '') return ['', []];

        $parts = explode(':', $mw, 2);
        $name  = trim($parts[0]);

        if (isset($parts[1]) && trim($parts[1]) !== '') {
            $args = array_map('trim', explode(',', $parts[1]));
        }
        return [$name, $args];
    }

    // ['Role','admin'] or ['Throttle',10,60]
    if (is_array($mw) && isset($mw[0])) {
        $name = trim((string)$mw[0]);
        $args = array_values(array_slice($mw, 1));
        return [$name, $args];
    }

    return ['', []];
}

function run_middlewares(array $middlewares, string $module = null): bool
{
    $mwMap = $GLOBALS['middleware'] ?? [];

    foreach ($middlewares as $mw) {

        [$name, $args] = parse_middleware($mw);

        if ($name === '') {
            trigger_error("Invalid middleware definition", E_USER_WARNING);
            unauthorizedAction();
            return false;
        }
        // 1) Resolve middleware file (module-first, then app)
        $mwFile = resolve_middleware_file($name, $module);
        if ($mwFile === null) {
            trigger_error("Middleware not found: {$name}", E_USER_WARNING);
            unauthorizedAction();
            return false; // stop pipeline (safer than continue)
        }

        require_once $mwFile;

        // 2) Resolve function name (NoClass convention)
        // If you still want to call Auth() exactly, change this to: $fn = $name;
        //$fn = lcfirst($name);
        
        // Support either Role() (legacy) or role() (NoClass preferred)
        if (function_exists($name)) {
            $fn = $name;
        } else {
            $fn = lcfirst($name);
        }

        if (!function_exists($fn)) {
            trigger_error("Middleware function not found: {$fn}", E_USER_WARNING);
            unauthorizedAction();
            return false;
        }

        // 3) Execute with args
        $ok = call_user_func_array($fn, $args);

        if ($ok === false) {
            unauthorizedAction();
            return false;
        }
    }

    return true;
}



function matchUriPattern($requestUrl, $routes) {
    $urlParts = explode('/',$requestUrl);
 //var_dump($urlParts);
    $controller = $urlParts[0];
    $actionName = isset($urlParts[1])? $urlParts[1] :'index';

    // TODO: instead of looping through all the routes we could just go strait to the index representing the controller.E.g
    // if(isset($route[$controller])){
    //      Now loop through the action or also use the index approach
    //      
    // }    
    foreach ($routes as $routeUrl => $route) {
        //var_dump($routeUrl);
        
        if($routeUrl == $controller){

            // Convert route URL to a regex pattern
            foreach ($route['action'] as $key => $action) {
                //echo $action;
                //$pattern = str_replace(['{name}', '{num}', '{any}'], ['([a-zA-Z]+)', '(\d+)', '(.+)'], $action);

                if( $actionName == $action){
                    controller($route['controller']);
                    action($route['action']);

                    if(array_key_exists('middleware',$route)){
                        return array(
                            'controller'=>$route['controller'],
                            'action'=>$actionName,
                            'middleware'=>$route['middleware']
                        );
                    }else{
                        return array(
                            'controller'=>$route['controller'],
                            'action'=>$actionName
                        );    
                    }
                }   
            }
        }
        
    }

    //return null;
}


/**
 * Match declared action patterns against URL segments.
 *
 * @param string[] $actions   Patterns like ['index', 'show/{num}', 'edit/{num}/child/{name}', ...]
 * @param string[] $segments  URL parts, e.g. ['user','show','1']
 * @return array|false        ['action'=>'show','params'=>[1]] or false if none match
 */
function matchAction222(array $actions, array $segments)
{
    // Remove controller segment for easier indexing
    $parts = array_slice($segments, 1);

    // Special case: if no action parts and we have 'index' in actions, return index
    /*if (empty($parts) && in_array('index', $actions, true)) {
        return [
            'action' => 'index',
            'params' => []
        ];
    }*/

    foreach ($actions as $pattern) {
        $patternParts = explode('/', trim($pattern, '/'));

        // Must match count exactly: patternParts vs. URL parts
        if (count($patternParts) !== count($parts)) {
            continue;
        }

        $params  = [];
        $matched = true;

        // Iterate each token
        foreach ($patternParts as $i => $pat) {
            $seg = $parts[$i];

            // Dynamic segment?
            if (strlen($pat) > 2 && $pat[0] === '{' && substr($pat, -1) === '}') {
                $type = substr($pat, 1, -1);
                switch ($type) {
                    case 'alpha':
                        $ok = ctype_alpha($seg);
                        break;
                    case 'num':
                        $ok = ctype_digit($seg);
                        break;
                    case 'alnum':
                        $ok = ctype_alnum($seg);
                        break;
                    case 'slug':
                        $ok = ($seg !== '' && $seg[0] !== '-' && $seg[strlen($seg)-1] !== '-')
                              && ctype_alnum(str_replace('-', '', $seg));
                        break;
                    case 'email':
                        $ok = filter_var($seg, FILTER_VALIDATE_EMAIL) !== false;
                        break;
                    case 'uuid':
                        $hex = str_replace('-', '', $seg);
                        $ok  = ctype_xdigit($hex) && strlen($seg) === 36;
                        break;
                    case 'any':
                        $ok = true;
                        break;
                    default:
                        $ok = false; // unknown type
                }
                if (!$ok) {
                    $matched = false;
                    break;
                }
                $params[] = $seg;
            }
            // Literal segment: must match exactly
            elseif ($seg !== $pat) {
                $matched = false;
                break;
            }
        }

        if ($matched) {
            // Action is the first literal part of the pattern
            $actionName = strtok($pattern, '/');
            return [
                'action' => $actionName,
                'params' => $params,
            ];
        }
    }

    return false;
}


/**
 * Match declared action patterns against URL segments.
 *
 * @param string[] $actions   Patterns like ['index', 'show/{num}', 'edit/{num}/child/{name}', ...]
 * @param string[] $segments  URL parts, e.g. ['user','show','1']
 * @return array|false        ['action'=>'show','params'=>[1]] or false if none match
 */
function matchAction(array $actions, array $segments)
{
    // Remove controller segment for easier indexing
    $parts = array_slice($segments, 1);
    
    // Case 1: No action parts - this handles /home/ and /
    if (empty($parts)) {
        // Check for exact 'index' pattern first
        if (in_array('index', $actions, true)) {
            return [
                'action' => 'index',
                'params' => []
            ];
        }
        
        // Check for parameterized index patterns with no parameters (shouldn't match empty parts)
        // Example: /home/ should not match 'index/{num}' 
        return false;
    }
    
    // Case 2: We have action parts - try to match all patterns
    foreach ($actions as $pattern) {
        $patternParts = explode('/', trim($pattern, '/'));
        
        // Skip empty patterns
        if (empty($patternParts) || empty($patternParts[0])) {
            continue;
        }
        
        $firstPatternPart = $patternParts[0];
        
        // Special handling: If pattern starts with a dynamic segment, 
        // it's an implicit index action with parameter(s)
        // Example: '{num}' in actions should match /home/5
        if (strlen($firstPatternPart) > 2 && $firstPatternPart[0] === '{' && substr($firstPatternPart, -1) === '}') {
            // This is a pattern like '{num}', '{name}', etc.
            // Check if the number of parts matches exactly
            if (count($patternParts) !== count($parts)) {
                continue;
            }
            
            $params = [];
            $matched = true;
            
            // Validate each segment against its pattern
            foreach ($patternParts as $i => $pat) {
                $seg = $parts[$i];
                
                // Dynamic segment validation
                if (strlen($pat) > 2 && $pat[0] === '{' && substr($pat, -1) === '}') {
                    $type = substr($pat, 1, -1);
                    if (!validateDynamicSegment($seg, $type)) {
                        $matched = false;
                        break;
                    }
                    $params[] = $seg;
                }
                // Literal segment: must match exactly
                elseif ($seg !== $pat) {
                    $matched = false;
                    break;
                }
            }
            
            if ($matched) {
                // Patterns starting with dynamic segments default to 'index' action
                return [
                    'action' => 'index',
                    'params' => $params
                ];
            }
            continue;
        }
        
        // Regular pattern matching (patterns that start with literal action names)
        // Must match count exactly: patternParts vs. URL parts
        if (count($patternParts) !== count($parts)) {
            continue;
        }

        $params = [];
        $matched = true;

        // Iterate each token
        foreach ($patternParts as $i => $pat) {
            $seg = $parts[$i];

            // Dynamic segment?
            if (strlen($pat) > 2 && $pat[0] === '{' && substr($pat, -1) === '}') {
                $type = substr($pat, 1, -1);
                if (!validateDynamicSegment($seg, $type)) {
                    $matched = false;
                    break;
                }
                $params[] = $seg;
            }
            // Literal segment: must match exactly
            elseif ($seg !== $pat) {
                $matched = false;
                break;
            }
        }

        if ($matched) {
            // Action is the first literal part of the pattern
            return [
                'action' => $firstPatternPart,
                'params' => $params,
            ];
        }
    }

    return false;
}

/**
 * Helper function to validate dynamic segments
 */
function validateDynamicSegment(string $segment, string $type): bool
{
    switch ($type) {
        case 'alpha':
        case 'name': // Allow 'name' as alias for alpha for backward compatibility
            return ctype_alpha($segment);
        case 'num':
            return ctype_digit($segment);
        case 'alnum':
            return ctype_alnum($segment);
        case 'slug':
            // Allows lowercase/uppercase letters, digits and hyphens.
            // Fix: ctype_alnum() returns bool, comparing bool === 1 always false.
            if ($segment === '' || $segment[0] === '-' || $segment[strlen($segment)-1] === '-') {
                return false;
            }
            return ctype_alnum(str_replace('-', '', $segment));
        case 'email':
            return filter_var($segment, FILTER_VALIDATE_EMAIL) !== false;
        case 'uuid':
            $hex = str_replace('-', '', $segment);
            return ctype_xdigit($hex) && strlen($segment) === 36;
        case 'any':
            return true;
        default:
            return false;
    }
}


/*function executeMiddleware($middlewares) {
    foreach ($middlewares as $middleware) {
        //$middlewareFile = __DIR__ . '/../middleware/' . $middleware . '.php';
        $middlewareFile = base_path('middleware/' . $middleware . '.php');

        if (file_exists($middlewareFile)) {
            require $middlewareFile;

            if (function_exists($middleware)) {
                $middlewareResult = call_user_func($middleware);

                if ($middlewareResult === false) {
                    return;
                }
            }
        }
    }
    return true;  // Middleware passed
}*/


/**
 * Render a view directly by path.
 *
 * Example:
 * render_view('home/index');
 * render_view('blog/post');
 *
 * Resolves to:
 * views/home/index.php
 * views/blog/post.php
 */
function render_view(string $view, array $data = []): void
{
    $GLOBALS['_noclass_view_rendered'] = true;

    $view = trim(str_replace('\\', '/', $view), "/ \t\n\r\0\x0B");

    if ($view === '') {
        notFoundAction();
        return;
    }

    $viewKey = strtolower($view);

    $viewFile = $GLOBALS['views'][$viewKey] ?? null;

    if (!$viewFile || !is_file($viewFile)) {
        $fallback = BASE_PATH . '/views/' . $view . '.php';

        if (is_file($fallback)) {
            $viewFile = $fallback;
        }
    }

    if (!$viewFile || !is_file($viewFile)) {
        notFoundAction();
        return;
    }

    $globalData = $GLOBALS['_noclass_view_data'] ?? [];

    if (is_array($globalData) && !empty($globalData)) {
        $data = array_merge($globalData, $data);
    }

    if (!empty($data)) {
        extract($data, EXTR_SKIP);
    }

    require $viewFile;
}

/**
 * Internal router view renderer.
 *
 * Buffers the resolved view file, applies the layout system, then outputs.
 *
 * Layout resolution order:
 *   1. layout_off() called in view          → no layout
 *   2. layout('x') called in view           → named override
 *   3. 'layout' => false in routes.php      → no layout for this route
 *   4. 'layout' => 'x' in routes.php        → named route default
 *   5. DEFAULT_LAYOUT constant              → named global default
 *   6. views/layouts/main.php exists        → main convention
 *   7. Nothing found                        → render bare
 *
 * For HMVC modules, layout and partial resolution checks the module-local
 * path first, then falls back to the app-level path.
 *
 * Sections defined in the view via section()/end_section() are captured
 * separately from $content and output via yield_section() in the layout.
 */
function render_route_view(
    string $controllerName,
    string $actionName,
    $content = null,
    array $declaredActions = [],
    string $module = null
): void {

    // Already rendered manually via render_view() in the action
    if (!empty($GLOBALS['_noclass_view_rendered'])) {
        return;
    }

    $controllerName = trim(str_replace('\\', '/', $controllerName), "/ \t\n\r\0\x0B");
    $actionName     = trim(str_replace('\\', '/', $actionName),     "/ \t\n\r\0\x0B");

    // ── If the action returned a string directly, echo and exit ──────────────
    // Kept for backward compatibility with actions that return HTML strings.
    // Returned content bypasses the layout system intentionally — the action
    // took explicit ownership of the output.
    if ($content !== null && $content !== '') {
        echo $content;
        return;
    }

    // ── Resolve the view file ─────────────────────────────────────────────────
    $viewFile = null;

    if ($module !== null && $module !== '') {
        // Module view: modules/{module}/views/{Controller}/{action}.php
        $module = trim(str_replace('\\', '/', $module), "/ \t\n\r\0\x0B");

        $moduleViewKey  = strtolower($module . '/views/' . $controllerName . '/' . $actionName);
        $viewFile       = $GLOBALS['modules'][$moduleViewKey] ?? null;

        if (!$viewFile || !is_file($viewFile)) {
            $fallback = BASE_PATH . '/modules/' . $module . '/views/' . $actionName . '.php';
            if (is_file($fallback)) {
                $viewFile = $fallback;
            }
        }
    }

    if (!$viewFile) {
        // Standard app view: views/{Controller}/{action}.php
        $viewKey  = strtolower($controllerName . '/' . $actionName);
        $viewFile = $GLOBALS['views'][$viewKey] ?? null;

        if (!$viewFile || !is_file($viewFile)) {
            $fallback = BASE_PATH . '/views/' . $controllerName . '/' . $actionName . '.php';
            if (is_file($fallback)) {
                $viewFile = $fallback;
            }
        }
    }

    if (!$viewFile || !is_file($viewFile)) {
        if (in_array($actionName, $declaredActions, true)) {
            notFoundAction();
        } else {
            notFoundAction();
        }
        return;
    }

    // ── Buffer the view ───────────────────────────────────────────────────────
    // Always buffer so that:
    //   a) layout() / layout_off() calls inside the view take effect before output
    //   b) section() captures are extracted out of $content cleanly
    //   c) layout wrapping happens after the complete view has run
    $globalData = $GLOBALS['_noclass_view_data'] ?? [];

    ob_start();

    if (is_array($globalData) && !empty($globalData)) {
        extract($globalData, EXTR_SKIP);
    }

    require $viewFile;

    $viewOutput = ob_get_clean();

    // ── Resolve layout ────────────────────────────────────────────────────────
    // $GLOBALS['_noclass_route_layout'] was set by route() before dispatch.
    // resolve_layout() is defined in system/view_helpers.php.
    $routeLayout = $GLOBALS['_noclass_route_layout'] ?? null;

    if (!function_exists('resolve_layout')) {
        // view_helpers.php not loaded — render bare (graceful degradation)
        echo $viewOutput;
        return;
    }

    $layoutFile = resolve_layout($routeLayout, $module);

    // ── No layout — output view directly ─────────────────────────────────────
    if (!$layoutFile) {
        echo $viewOutput;
        return;
    }

    // ── Wrap in layout ────────────────────────────────────────────────────────
    // Make the view output available to the layout in two ways:
    //   $content        — direct variable (familiar, readable in layouts)
    //   view_content()  — function call (explicit, avoids variable collision)
    $GLOBALS['_noclass_view_content'] = $viewOutput;
    $content = $viewOutput;

    if (is_array($globalData) && !empty($globalData)) {
        extract($globalData, EXTR_SKIP);
    }

    require $layoutFile;
}


function view($actionName){
    if (controller() !== null) {
        render_view(controller() . '/' . $actionName);
        exit;
    }

    return null;
}

function validateMatches($matches, $pattern) {
    $patternParts = explode('/', $pattern);

    foreach ($matches as $key => $value) {
        $patternPart = $patternParts[$key + 1]; // Skip the first part (empty string)

        if ($patternPart === '{name}' && !ctype_alpha($value)) {
            return false; // {name} should be alphabetic only
        } elseif ($patternPart === '{any}' && !ctype_alnum($value)) {
            return false; // {any} can be alphanumeric
        } elseif ($patternPart === '{num}' && !ctype_digit($value)) {
            return false; // {num} should be numeric only
        }
    }

    return true;
}

/**
 * Check whether a view exists for the given controller/action.
 */
function viewExists(string $controllerName, string $actionName): bool
{
    $key     = strtolower($controllerName) . '/' . strtolower($actionName);
    $viewMap = $GLOBALS['views'] ?? [];
    return isset($viewMap[$key]);
}

