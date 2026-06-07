# `lib/` — Application Libraries

Files in this folder are **lazy-loaded on demand** via the `lib()` function. Nothing here is loaded automatically at boot.

This is the correct place for feature-specific code that is only needed in certain controllers — payment SDKs, PDF generators, CSV exporters, email wrappers, API clients.

---

## Loading a library

```php
// In a controller or model:
lib('stripe');          // loads lib/stripe.php
lib('pdf_generator');   // loads lib/pdf_generator.php
lib('csv_export');      // loads lib/csv_export.php
```

`lib()` is idempotent — calling it twice for the same file has no effect.

---

## What belongs here

- Third-party SDK wrappers (`stripe.php`, `mailchimp.php`)
- Feature-specific helpers used in only a few controllers
- Heavy utility classes or functions (PDF generation, image processing)
- Any code where loading it on every request would be wasteful

## What does NOT belong here

- Functions needed everywhere without an explicit load call
- Application-wide constants and helpers

Those belong in `init/` and are loaded automatically at boot.

---

## Difference from `init/`

| | `init/` | `lib/` |
|---|---|---|
| When loaded | Every request, at boot | On demand via `lib('name')` |
| Who loads it | Framework (setup.php) | The controller that needs it |
| Use case | Global helpers, constants | Feature-specific code |
| OPcache benefit | Critical in production | Helpful but not load-bearing |

---

## Subdirectories

`lib()` supports subdirectory paths:

```php
lib('payment/stripe');   // loads lib/payment/stripe.php
lib('export/csv');       // loads lib/export/csv.php
```

This lets you organise larger lib collections without polluting the root of `lib/`.
