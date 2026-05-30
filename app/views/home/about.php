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
    <title>About NoClass™</title>
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
            <h1>About NoClass™</h1>
            <p>NoClass keeps the structure of MVC while allowing developers to build using simple procedural PHP functions.</p>
            <p>This page is rendered explicitly by <code>render_view('home/about')</code>, but it does not require <code>data()</code>.</p>

            <h2>What this demo shows</h2>
            <ul>
                <li>A root controller file: <code>app/controllers/Home.php</code></li>
                <li>Plain action functions: <code>index()</code>, <code>about()</code>, and <code>starter()</code></li>
                <li>Views rendered from <code>app/views/home/</code></li>
                <li>A separate HMVC-style module under <code>app/modules/demo/</code></li>
            </ul>

            <p><a href="<?= base_url('home') ?>">Back to Home</a> | <a href="<?= base_url('home/starter') ?>">Automatic View Demo</a></p>
        </section>
    </main>
</body>
</html>
