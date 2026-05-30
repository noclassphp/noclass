<?php
/**
 * NoClass™ PHP Procedural Framework
 * Copyright 2024-2026 Danny Mbanginu.
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

/**
 * Home page demo.
 *
 * Demonstrates:
 * - passing values to the view with data()
 * - explicitly rendering a view with render_view()
 *
 * URL example:
 * /home/index
 */
function index()
{
    data('title', 'Welcome to NoClass™');
    data('tagline', 'A procedural PHP MVC framework built without user-defined application classes.');
    data('intro', 'This default demo is intentionally small: one home controller, one automatic view example, and one HMVC module demo. No database setup is required.Although you can use our database ORM functions.');

    render_view('home/index');
}

/**
 * About page demo.
 *
 * Demonstrates explicit rendering without needing data().
 *
 * URL example:
 * /home/about
 */
function about()
{
    render_view('home/about');
}

/**
 * Starter page demo.
 *
 * Demonstrates automatic view rendering.
 * This action intentionally does not call render_view().
 *
 * NoClass will automatically render:
 * app/views/home/starter.php
 *
 * URL example:
 * /home/starter
 */
function starter()
{
    // Intentionally empty.
}
