<?php
/**
 * NoClass™ PHP Procedural Framework
 * Copyright 2024-2026 Danny Mbanginu.
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

/**
 * HMVC module demo.
 *
 * Example URL pattern may depend on the active NoClass route configuration.
 * A common module-style route may look like:
 * /demo
 *
 * This should resolve to:
 * app/modules/demo/controllers/Demo.php
 * and run:
 * index()
 */
function index()
{
    data('title', 'HMVC Module Demo');
    data('module_name', 'demo');
    data('message', 'This page is rendered from inside the demo module. It demonstrates modular organisation without requiring a database.');

    /*
    |--------------------------------------------------------------------------
    | HMVC Examples
    |--------------------------------------------------------------------------
    |
    | Current module view:
    |
    | module_view('index');
    |
    | View from another module:
    |
    | module_view('blog/index');
    | module_view('admin/dashboard');
    |
    */
}
