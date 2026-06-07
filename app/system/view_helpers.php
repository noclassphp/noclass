<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// ============================================================
// LAYOUT HELPERS
// ============================================================

/**
 * Suppress layout for the current view.
 *
 * Call at the top of any view that should render bare —
 * API responses, auth pages, AJAX fragments, etc.
 *
 * Usage in a view:
 *   <?php layout_off() ?>
 */
function layout_off(): void
{
    $GLOBALS['_noclass_layout'] = false;
}

/**
 * Override the layout for the current view.
 *
 * Call at the top of any view that needs a layout different
 * from the route default or global fallback.
 *
 * Usage in a view:
 *   <?php layout('installer') ?>
 */
function layout(string $name): void
{
    $name = trim($name);
    $GLOBALS['_noclass_layout'] = ($name !== '') ? $name : false;
}

/**
 * Resolve the layout file path.
 *
 * Resolution order:
 *   1. View called layout_off()              → false (no layout)
 *   2. View called layout('x')              → resolve 'x'
 *   3. Route declared 'layout' => false     → false (no layout)
 *   4. Route declared 'layout' => 'x'      → resolve 'x'
 *   5. DEFAULT_LAYOUT constant defined      → resolve it
 *   6. views/layouts/main.php exists        → use 'main' convention
 *   7. Nothing found                        → false (render bare)
 *
 * For each name, resolution checks:
 *   a. modules/{module}/views/layouts/{name}.php  (if module context)
 *   b. views/layouts/{name}.php                   (app-level)
 *
 * @param string|false|null $routeLayout   Layout declared in routes.php (false = off, null = absent)
 * @param string|null       $module        Current module name if HMVC context
 * @return string|false     Absolute path to layout file, or false
 */
function resolve_layout($routeLayout = null, ?string $module = null)
{
    // ── Step 1 & 2: view-level declaration (highest priority) ────────────────
    if (array_key_exists('_noclass_layout', $GLOBALS)) {
        $viewLayout = $GLOBALS['_noclass_layout'];
        if ($viewLayout === false) return false;
        return _find_layout_file((string)$viewLayout, $module);
    }

    // ── Step 3 & 4: route-level declaration ──────────────────────────────────
    if ($routeLayout !== null) {
        if ($routeLayout === false) return false;
        return _find_layout_file((string)$routeLayout, $module);
    }

    // ── Step 5: DEFAULT_LAYOUT constant ──────────────────────────────────────
    if (defined('DEFAULT_LAYOUT') && DEFAULT_LAYOUT !== '' && DEFAULT_LAYOUT !== false) {
        $file = _find_layout_file((string)DEFAULT_LAYOUT, $module);
        if ($file) return $file;
    }

    // ── Step 6: main.php convention ──────────────────────────────────────────
    $file = _find_layout_file('main', $module);
    if ($file) return $file;

    // ── Step 7: nothing found ─────────────────────────────────────────────────
    return false;
}

/**
 * Internal: find a layout file by name.
 * Checks module-local path first, then app-level.
 */
function _find_layout_file(string $name, ?string $module)
{
    $name = basename(trim(str_replace(['\\', '..'], ['/', ''], $name), '/'));
    if ($name === '') return false;

    // Module-local layout: modules/{module}/views/layouts/{name}.php
    if ($module !== null && $module !== '') {
        $moduleLayout = base_path(
            'modules/' . $module . '/views/layouts/' . $name . '.php'
        );
        if (is_file($moduleLayout)) return $moduleLayout;
    }

    // App-level layout: views/layouts/{name}.php
    // Check filemap first (fast path), then direct path
    $mapKey = 'layouts/' . $name;
    $mapped = $GLOBALS['views'][$mapKey] ?? null;
    if ($mapped && is_file($mapped)) return $mapped;

    $direct = base_path('views/layouts/' . $name . '.php');
    if (is_file($direct)) return $direct;

    return false;
}


// ============================================================
// PARTIAL HELPERS
// ============================================================

/**
 * Include a reusable partial view.
 *
 * Resolution order:
 *   1. modules/{module}/views/partials/{name}.php   (if module context)
 *   2. views/partials/{name}.php                    (app-level)
 *
 * The partial receives all current view data plus any $data passed.
 * Partials cannot declare a layout — they are always bare fragments.
 *
 * Usage in a view or layout:
 *   <?php partial('alert') ?>
 *   <?php partial('pager', ['pager' => $pager]) ?>
 *
 * Subdirectory partials:
 *   <?php partial('cards/stat') ?>
 */
function partial(string $name, array $data = []): void
{
    $name   = trim(str_replace(['\\', '..'], ['/', ''], $name), '/');
    if ($name === '') return;

    $module = $GLOBALS['__noclass_current_module'] ?? null;
    $file   = _find_partial_file($name, $module);

    if (!$file) {
        if (DEBUG) {
            trigger_error("NoClass partial not found: {$name}", E_USER_WARNING);
        }
        return;
    }

    // Merge global view data with locally passed data
    $globalData = $GLOBALS['_noclass_view_data'] ?? [];
    $merged     = array_merge(
        is_array($globalData) ? $globalData : [],
        $data
    );

    if (!empty($merged)) {
        extract($merged, EXTR_SKIP);
    }

    require $file;
}

/**
 * Internal: find a partial file by name.
 */
function _find_partial_file(string $name, ?string $module)
{
    // Module-local: modules/{module}/views/partials/{name}.php
    if ($module !== null && $module !== '') {
        $modulePartial = base_path(
            'modules/' . $module . '/views/partials/' . $name . '.php'
        );
        if (is_file($modulePartial)) return $modulePartial;
    }

    // App-level: check filemap first (fast path)
    $mapKey = 'partials/' . $name;
    $mapped = $GLOBALS['views'][$mapKey] ?? null;
    if ($mapped && is_file($mapped)) return $mapped;

    // Direct path fallback
    $direct = base_path('views/partials/' . $name . '.php');
    if (is_file($direct)) return $direct;

    return false;
}


// ============================================================
// SECTION HELPERS
// ============================================================

/**
 * Start capturing a named section.
 *
 * Output between section() and end_section() is stored and
 * NOT included in $content. The layout retrieves it with yield_section().
 *
 * Usage in a view:
 *   <?php section('scripts') ?>
 *   <script nonce="<?= csp_nonce() ?>">initChart()</script>
 *   <?php end_section() ?>
 *
 * Sections can be defined anywhere in the view, including after
 * normal markup. Multiple sections with the same name are appended.
 */
function section(string $name): void
{
    $name = trim($name);
    if ($name === '') return;

    // Push section name onto the stack (supports nested sections)
    if (!isset($GLOBALS['_noclass_section_stack'])) {
        $GLOBALS['_noclass_section_stack'] = [];
    }

    $GLOBALS['_noclass_section_stack'][] = $name;
    ob_start();
}

/**
 * End the current section and store its content.
 */
function end_section(): void
{
    if (empty($GLOBALS['_noclass_section_stack'])) {
        if (DEBUG) {
            trigger_error('NoClass: end_section() called without matching section()', E_USER_WARNING);
        }
        return;
    }

    $captured = ob_get_clean();
    $name     = array_pop($GLOBALS['_noclass_section_stack']);

    if (!isset($GLOBALS['_noclass_sections'])) {
        $GLOBALS['_noclass_sections'] = [];
    }

    // Append if the section was defined more than once (e.g. from partials)
    if (isset($GLOBALS['_noclass_sections'][$name])) {
        $GLOBALS['_noclass_sections'][$name] .= $captured;
    } else {
        $GLOBALS['_noclass_sections'][$name] = $captured;
    }
}

/**
 * Output a named section inside a layout.
 *
 * Usage in a layout:
 *   <?= yield_section('scripts') ?>
 *   <?= yield_section('sidebar', '<p>No sidebar content.</p>') ?>
 *
 * @param string $name     Section name
 * @param string $default  Output if section was never defined
 */
function yield_section(string $name, string $default = ''): string
{
    return $GLOBALS['_noclass_sections'][$name] ?? $default;
}

/**
 * Output the main view content inside a layout.
 *
 * Usage in a layout:
 *   <?= view_content() ?>
 *
 * Alias: $content is also extracted directly into layout scope,
 * so <?= $content ?> works too. This function is the explicit form.
 */
function view_content(): string
{
    return $GLOBALS['_noclass_view_content'] ?? '';
}


// ============================================================
// INTERNAL: reset per-request layout state
// ============================================================

/**
 * Clear layout/section state at the start of each request.
 * Called by route() before dispatch.
 */
function _noclass_reset_view_state(): void
{
    unset(
        $GLOBALS['_noclass_layout'],
        $GLOBALS['_noclass_view_content'],
        $GLOBALS['_noclass_sections'],
        $GLOBALS['_noclass_section_stack']
    );
}
