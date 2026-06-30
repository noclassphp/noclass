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
    <title><?= e(data('title')) ?></title>
    <style nonce="<?= csp_nonce() ?>">
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 48px 24px; }
        .hero { background: #ffffff; border-radius: 18px; padding: 42px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #eef2ff; font-size: 14px; margin-bottom: 18px; }
        h1 { margin: 0 0 14px; font-size: 42px; }
        p { line-height: 1.7; font-size: 17px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-top: 28px; }
        .card { background: #fff; padding: 22px; border-radius: 14px; border: 1px solid #e5e7eb; }
        .links { margin-top: 28px; display: flex; gap: 12px; flex-wrap: wrap; }
        a.button { background: #111827; color: #fff; padding: 12px 16px; border-radius: 10px; text-decoration: none; }
        a.secondary { background: #e5e7eb; color: #111827; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="hero">
            <span class="badge">NoClass™ Demo</span>
            <h1><?= e(data('title')) ?></h1>
            <p><strong><?= e(data('tagline')) ?></strong></p>
            <p><?= e(data('intro')) ?></p>
            <p>For more info visit <a href="https://noclass.org">https://noclass.org</a></p>
            <div class="links">
                <a class="button" href="<?= url('home/about') ?>">About NoClass</a>
                <a class="button secondary" href="<?= url('home/starter') ?>">Automatic View Demo</a>
                <a class="button secondary" href="<?= url('demo') ?>">HMVC Module Demo</a>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <h2>Controller Routing</h2>
                <p><code>/home/index</code> loads <code>controllers/Home.php</code> and runs <code>index()</code>.</p>
            </div>
            <div class="card">
                <h2>Optional Data Passing</h2>
                <p>This page uses <code>data()</code> to pass values from the controller to the view.</p>
            </div>
            <div class="card">
                <h2>No Database Required</h2>
                <p>This starter demo works immediately without configuring MySQL, MariaDB, or another database.</p>
            </div>
        </section>
    </main>
</body>
</html>
