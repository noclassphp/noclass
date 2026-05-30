<?php
/**
 * NoClass™ PHP Procedural Framework
 * Copyright 2024-2026 Danny Mbanginu.
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Starter Page - NoClass™</title>
    <style nonce="<?= csp_nonce() ?>">
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1f2937; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 48px 24px; }
        .panel { background: #ffffff; border-radius: 18px; padding: 38px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        h1 { margin-top: 0; font-size: 36px; }
        p, li { line-height: 1.7; font-size: 17px; }
        a { color: #111827; font-weight: bold; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="panel">
            <h1>Starter Page</h1>
            <p>This page demonstrates NoClass automatic view rendering.</p>
            <p>The controller action <code>starter()</code> is intentionally empty and does not call <code>render_view()</code>.</p>
            <p>NoClass automatically locates and renders <code>app/views/home/starter.php</code> based on the current controller and action.</p>

            <h2>Why this is useful</h2>
            <ul>
                <li>Simple pages can stay very small.</li>
                <li>You can still call <code>render_view()</code> when you want explicit control.</li>
                <li><code>data()</code> is optional and only needed when passing values to a view.</li>
            </ul>

            <p><a href="<?= base_url('home') ?>">Back to Home</a> | <a href="<?= base_url('home/about') ?>">About</a> | <a href="<?= base_url('demo') ?>">HMVC Demo Module</a></p>
        </section>
    </main>
</body>
</html>
