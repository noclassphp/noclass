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
 * Register or retrieve a service in the container.
 *
 * @param string  $key              Unique service identifier
 * @param callable|null $factory    When provided, stores the factory for later
 * @return mixed|null               The service instance (after factory runs), or current definition when registering
 */
function container(string $key, callable $factory = null)
{
    static $services = [];    // Holds instantiated services
    static $factories = [];   // Holds factory callables

    // Registration: store the factory
    if ($factory !== null) {
        $factories[$key] = $factory;
        return;
    }

    // Retrieval: if already instantiated, return it
    if (isset($services[$key])) {
        return $services[$key];
    }

    // If we have a factory, call it to create the service
    if (isset($factories[$key])) {
        $services[$key] = $factories[$key]();
        return $services[$key];
    }

    // No factory registered
    trigger_error("Service not found in container: {$key}", E_USER_WARNING);
    return null;
}
