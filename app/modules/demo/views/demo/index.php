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
        .wrap { max-width: 860px; margin: 0 auto; padding: 48px 24px; }
        .panel { background: #ffffff; border-radius: 18px; padding: 38px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        h1 { margin-top: 0; font-size: 36px; }
        p, li { line-height: 1.7; font-size: 17px; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
        a { color: #111827; font-weight: bold; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="panel">
            <h1><?= e(data('title')) ?></h1>
            <p><?= e(data('message')) ?></p>

            <h2>Module structure</h2>
            <ul>
                <li>Module folder: <code>app/modules/<?= e(data('module_name')) ?>/</code></li>
                <li>Controller: <code>controllers/Demo.php</code></li>
                <li>View: <code>views/demo/index.php</code></li>
            </ul>

            <p>This allows larger applications to group related controllers, views, models, middleware, and libraries by feature area.</p>
            <p><a href="<?= url('home') ?>">Back to Home</a></p>
        </section>
    </main>
</body>
</html>
