<?php

// =====================================================
// NoClass route configuration
// =====================================================
// NoClass™ PHP Procedural Framework
// Copyright 2024-2026 Danny Mbanginu.
// Licensed under the Apache License, Version 2.0.
// See the LICENSE file for details.
// =====================================================

return [

    // =================================================
    // BASIC CONTROLLER ROUTE
    // =================================================
    // Route key:
    //   'home'
    //
    // Matches URLs such as:
    //   /home
    //   /home/index
    //   /home/about
    //   /home/starter
    //
    // Controller file loaded:
    //   app/controllers/Home.php
    //
    // Action functions allowed:
    //   index()
    //   about()
    //   starter()
    //
    // Notes:
    //   - index() demonstrates data() + render_view().
    //   - about() demonstrates explicit render_view() without data().
    //   - starter() is intentionally empty to demonstrate automatic view rendering.
    //
    'home' => [
        'controller' => 'Home',
        'action'     => ['index', 'about', 'starter'],
        'middleware' => []
    ],

    // =================================================
    // HMVC MODULE MOUNT
    // =================================================
    // Route key:
    //   'demo'
    //
    // Matches:
    //   /demo
    //
    // Module folder:
    //   app/modules/demo/
    //
    // Default target:
    //   app/modules/demo/controllers/Demo.php -> index()
    //
    // Depending on your NoClass module route setup, the canonical
    // module route may also be available, for example:
    //   /m/demo/demo/index
    //
    'demo' => [
        'module'     => 'demo',
        'middleware' => [],
        'default'    => ['demo', 'index']
    ],

    // =================================================
    // EXAMPLE: SIMPLE ALIAS TO A CONTROLLER
    // =================================================
    // Uncomment to make /start load the Home controller.
    // This is useful when you want a public URL that is different
    // from the real controller name.
    //
    // 'start' => [
    //     'controller' => 'Home',
    //     'action'     => ['index']
    // ],

    // =================================================
    // EXAMPLE: ACTION WITH NUMERIC PARAMETER
    // =================================================
    // Example URL:
    //   /user/show/25
    //
    // Example controller:
    //   app/controllers/User.php
    //
    // Example function:
    //   show($id)
    //
    // Pattern:
    //   {num} accepts numeric values.
    //
    // 'user' => [
    //     'controller' => 'User',
    //     'action'     => ['index', 'show/{num}'],
    //     'middleware' => []
    // ],

    // =================================================
    // EXAMPLE: ACTION WITH TEXT PARAMETER
    // =================================================
    // Example URL:
    //   /blog/post/my-first-post
    //
    // Example function:
    //   post($slug)
    //
    // Pattern:
    //   {alpha} accepts alphabetic/text-style values depending on
    //   your NoClass route pattern rules.
    //
    // 'blog' => [
    //     'controller' => 'Blog',
    //     'action'     => ['index', 'post/{alpha}'],
    //     'middleware' => []
    // ],

    // =================================================
    // EXAMPLE: DEEP PARAMETER PATTERN
    // =================================================
    // Example URL:
    //   /user/edit/25/name/john
    //
    // Example function:
    //   edit($id, $name)
    //
    // 'user-edit' => [
    //     'controller' => 'User',
    //     'action'     => ['edit/{num}/name/{alpha}'],
    //     'middleware' => []
    // ],

    // =================================================
    // EXAMPLE: ALIAS WITH EXTRA ACTIONS
    // =================================================
    // This lets /u act as an alias for the User controller.
    //
    // overwrite_actions:
    //   true  = only the actions listed in this alias are allowed.
    //   false = combine these alias actions with the original controller route actions.
    //
    // 'u' => [
    //     'controller' => 'User',
    //     'action' => [
    //         '{num}',
    //         'profile',
    //         'show/{num}',
    //         'edit/{num}/name',
    //         'edit/{num}/child/{alpha}'
    //     ],
    //     'overwrite_actions' => false,
    //     'middleware' => []
    // ],

    // =================================================
    // EXAMPLE: POST-ONLY ACTION
    // =================================================
    // This is useful for delete, save, update, login, logout,
    // and other state-changing actions.
    //
    // Example URL:
    //   POST /blog/delete
    //
    // 'blog-admin' => [
    //     'controller' => 'Blog',
    //     'action'     => ['delete', 'method' => 'POST'],
    //     'middleware' => []
    // ],

    // =================================================
    // EXAMPLE: MIDDLEWARE
    // =================================================
    // Middleware can protect routes before the controller action runs.
    //
    // Simple middleware:
    //   'middleware' => ['Auth']
    //
    // Middleware with arguments:
    //   'middleware' => ['Role:admin']
    //   'middleware' => [['Role', 'admin']]
    //
    // Multiple middleware:
    //   'middleware' => ['Auth', ['Role', 'admin'], ['Throttle', 10, 60]]
    //
    // 'admin' => [
    //     'controller' => 'Admin',
    //     'action'     => ['index', 'list', 'show/{num}'],
    //     'middleware' => [
    //         'Auth',
    //         ['Role', 'admin'],
    //         ['Throttle', 10, 60],
    //     ]
    // ],

    // =================================================
    // EXAMPLE: MODULE WITH PROTECTED ACCESS
    // =================================================
    // This demonstrates how a module could be mounted with middleware.
    //
    // 'admin-panel' => [
    //     'module'     => 'admin',
    //     'middleware' => ['Auth', ['Role', 'admin']],
    //     'default'    => ['dashboard', 'index']
    // ],
];
