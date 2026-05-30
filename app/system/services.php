<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

function services_config(): array {
    static $cfg = null;
    if ($cfg === null) $cfg = require BASE_PATH . '/config/services.php';
    return $cfg;
}

function service(string $name, callable $factory = null) {
    static $services = [];

    if (isset($services[$name])) return $services[$name];

    if ($factory) {
        $services[$name] = $factory();
        return $services[$name];
    }

    return null;
}
