# `init/` — Application Initialisation

Every `.php` file directly inside this folder is **automatically required at boot**, before any controller, middleware or model runs. Files are loaded in **alphabetical order**.

This is the correct place for anything that must be globally available on every request without an explicit `lib()` call.

---

## What belongs here

- Application-level helper functions (`fh_flash()`, `fh_audit()`, custom `url_*` helpers)
- Application-wide constants (`define('PLAN_PRO', 'pro')`)
- Permission matrices and role definitions
- Global macros and shorthand functions
- Application event listeners or hooks
- Anything a controller should be able to call without loading anything first

## What does NOT belong here

- Heavy feature-specific code (payment SDKs, PDF generators, CSV exporters)
- Anything only needed by one or two controllers
- Third-party library wrappers

Those belong in `lib/` and are loaded on demand via `lib('name')`.

---

## Load order

Files load alphabetically. When inter-file dependencies exist, control order with numeric prefixes:

```
init/
  01_constants.php      ← no dependencies, loads first
  02_helpers.php        ← may use constants defined above
  03_permissions.php    ← uses helper functions
  04_events.php         ← uses both constants and helpers
```

When order does not matter, name files descriptively without prefixes:

```
init/
  helpers.php
  constants.php
```

---

## OPcache and performance

In production with `opcache.validate_timestamps=0`, every file in this folder is compiled once and served from shared memory. The boot loop costs virtually nothing — there are no filesystem reads or parse operations after the first request.

In development with `opcache.validate_timestamps=1`, changes to init files take effect on the next request automatically.

See `php.ini` and `php-development.ini` at the project root for the correct OPcache configuration. Getting these right is important — see the comments in those files.

---

## This folder is optional

Projects that have no globally-required functions simply omit this folder. The framework skips the loader silently when `init/` does not exist. There is no error and no empty folder required.
