# Configuration

NoClass™ PHP Procedural Framework  
Copyright 2024-2026 Danny Mbanginu.

Licensed under the Apache License, Version 2.0.  
See the project `LICENSE` file for details.

## Purpose

This folder contains application configuration files.

Default files included in the starter demo:

- `config.php` — main application settings
- `database.php` — database placeholders, disabled by default through `USE_DB=false`
- `routes.php` — active route definitions plus commented routing examples
- `modules.php` — enabled/disabled modules
- `services.php` — external service placeholders

## Default Demo Note

The default NoClass™ demo is intentionally database-free.

This allows new users to run the framework immediately without creating a database, importing SQL, or editing credentials.

## Active Routes

The starter demo includes active route definitions for:

```text
/home          -> controllers/Home.php -> index()
/home/index    -> controllers/Home.php -> index()
/home/about    -> controllers/Home.php -> about()
/home/starter  -> controllers/Home.php -> starter() -> automatic view rendering
/demo          -> modules/demo/controllers/Demo.php -> index()
```

The canonical module route may also be available depending on your NoClass route setup:

```text
/m/demo/demo/index
```

## Routing Examples

`routes.php` also includes commented examples showing:

- controller aliases
- numeric parameters with `{num}`
- text parameters with `{alpha}`
- deeper parameter patterns
- alias routes with `overwrite_actions`
- POST-only routes
- middleware
- module routes with middleware
