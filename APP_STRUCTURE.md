# NoClass™ Application Structure

This document explains the NoClass application folder structure, what goes where,
and the load contract for each directory. Read this first when joining a NoClass project.

---

## Directory map

```
BASE_PATH/                          ← app root (see deployment layouts below)
  index.php                         ← front controller — do not edit
  .htaccess                         ← Apache rewrite rules
  .env                              ← environment variables (not committed to git)
  .env.local                        ← local developer overrides (not committed)
  php.ini                           ← production OPcache settings
  php-development.ini               ← development OPcache settings

  config/
    config.php                      ← application constants (USE_DB, DEBUG, etc.)
    database.php                    ← database connection constants
    routes.php                      ← URL route definitions
    modules.php                     ← module allowlist (optional)

  system/                           ← NoClass framework files — do not edit
    setup.php, Route.php, db.php,
    func.php, security.php, ...

  init/                             ← app-level globals, loaded at boot (optional)
    README.md
    01_helpers.php
    02_constants.php
    ...

  lib/                              ← feature libraries, lazy-loaded on demand
    README.md
    stripe.php
    pdf_generator.php
    ...

  controllers/                      ← procedural controller files
  models/                           ← procedural model files
  middleware/                       ← middleware files
  views/                            ← view files
    layouts/                        ← layout wrappers (main.php etc.)
    partials/                       ← reusable view fragments
    {controller}/                   ← views per controller
  modules/                          ← HMVC modules (optional)
  storage/                          ← runtime files (logs, cache, uploads)
  cache/                            ← filemap and route cache
  vendor/                           ← Composer packages (optional)
```

---

## The two loading strategies

NoClass has two distinct loading strategies. Understanding them is the key to
knowing where to put code.

### 1. Boot-time loading — `init/`

Everything in `init/` is required automatically before the first request is
dispatched. Files load in alphabetical order. Nothing needs to call them —
they are simply available everywhere.

**Use `init/` for:**
- Helper functions used across the whole application
- Application-wide constants
- Permission matrices and role definitions
- Event listeners and application hooks

**Practical rule:** if a controller would be surprised not to find a function,
it belongs in `init/`.

### 2. Lazy loading — `lib()`

Everything in `lib/` is loaded explicitly on demand.

```php
lib('stripe');          // loads lib/stripe.php when needed
lib('pdf_generator');   // loads lib/pdf_generator.php when needed
```

**Use `lib/` for:**
- Payment SDKs, email clients, third-party API wrappers
- PDF, CSV, image processing utilities
- Anything only a subset of controllers need

**Practical rule:** if loading it on every request when most requests don't
need it would be wasteful, it belongs in `lib/`.

---

## Load order — full boot sequence

When a request arrives, NoClass loads in this order:

```
1.  index.php defines BASE_PATH, BASE_URI
2.  system/setup.php runs:
      env.php          → reads .env, .env.local
      config/config.php
      config/database.php
      system files     → func.php, db.php, security.php, etc.
      filemap          → indexes all app files
      modules          → boots active HMVC modules
      init/*.php       → YOUR app globals, alphabetical order   ← here
      system/Route.php
3.  send_security_headers()
4.  db_connect()
5.  secure_session_start()
6.  route() dispatches to controller
7.  Controller runs — lib() calls load feature libs as needed
8.  View renders — layout wraps output
```

Everything in `init/` is available from step 7 onward without any loading call.
Everything in `lib/` is available after the controller's `lib()` call.

---

## Controllers

Controllers are plain PHP files containing functions. One file per controller,
one function per action. The filename determines the route key.

```php
// controllers/Products.php

function index()
{
    model('Product');
    $products = product_list();
    data('products', $products);
}

function show($id)
{
    model('Product');
    $product = product_find($id);
    data('product', $product);
}
```

**No classes. No base controller to extend. No `$this`.**

---

## Models

Models are plain PHP files containing functions. The filemap indexes them;
`model('Name')` loads the file on demand.

```php
// models/Product.php

function product_list(array $filters = [], int $limit = 25, int $offset = 0): array
{
    return db_select('products', '*', $filters, 'name ASC', (string)$limit);
}

function product_find(int $id): ?array
{
    $rows = db_select('products', '*', ['id' => $id], '', '1');
    return $rows[0] ?? null;
}
```

---

## Views and layouts

Views are plain PHP files. The routing system renders them automatically after
the controller action runs.

```
views/
  layouts/
    main.php        ← default layout (DEFAULT_LAYOUT in config.php)
  partials/
    alert.php       ← reusable fragment
    pager.php
  products/
    index.php       ← rendered for Products::index()
    show.php        ← rendered for Products::show()
```

### Layout functions (available in all views)

```php
<?php layout_off() ?>          // suppress layout for this view
<?php layout('installer') ?>   // use a different layout
<?php partial('alert') ?>      // include a partial
<?php section('scripts') ?>    // start a named section
<script>...</script>
<?php end_section() ?>
```

### In layouts

```php
<?= view_content() ?>          // output the view
<?= yield_section('scripts') ?> // output a section
<?php partial('navbar') ?>     // include a partial
```

---

## Routes

```php
// config/routes.php
return [
    'products' => [
        'controller' => 'Products',
        'action'     => ['index', 'show/{num}', 'create', 'store'],
        'middleware' => ['Auth'],
        // 'layout'  => false    suppress layout
        // 'layout'  => 'admin'  named layout
        // absent               use DEFAULT_LAYOUT
    ],
];
```

### Layout declaration (in order of priority)

| Where | How | Effect |
|---|---|---|
| View | `layout_off()` | No layout for this view |
| View | `layout('x')` | Override to named layout |
| routes.php | `'layout' => false` | No layout for all actions in route |
| routes.php | `'layout' => 'x'` | Named layout for all actions in route |
| config.php | `DEFAULT_LAYOUT` | App-wide default |
| Convention | `views/layouts/main.php` exists | Used as fallback |

---

## Middleware

```php
// middleware/Auth.php
function Auth(): bool
{
    if (empty($_SESSION['user_id'])) {
        redirect(url('login'));
        return false;
    }
    return true;
}
```

Middleware functions return `true` to allow the request or `false` to block it.
Returning `false` stops dispatch — no controller action runs.

---

## HMVC Modules

Optional. A module is a self-contained sub-application with its own controllers,
models, views, middleware and config.

```
modules/
  blog/
    module.php          ← module metadata and requirements
    config/
      routes.php
      permissions.php
    controllers/
    models/
    views/
    init/               ← module-level init (optional, same contract as app init/)
    lib/                ← module-level libs
```

Enable modules in `config/modules.php`:

```php
return [
    'blog'     => true,
    'shop'     => true,
    'analytics'=> false,  // disabled
];
```

---

## Environment variables

```
.env                    ← base configuration, committed structure only (values gitignored)
.env.local              ← local developer overrides, never committed
.env.production         ← production overrides (deployed separately)
```

Load order: `.env` → `.env.<APP_ENV>` → `.env.local`. Later files override earlier ones.

**Important:** `.env` files must live at `BASE_PATH` — the app root directory.

| Deployment | `index.php` location | `.env` location |
|---|---|---|
| Option 1 (recommended) | `/public_html/index.php` | `/noclass_app/.env` |
| Option 2 (app inside web root) | `/public_html/index.php` | `/public_html/app/.env` |
| Legacy (flat) | `/public_html/index.php` | `/public_html/.env` |

In legacy/flat deployments `BASE_PATH` resolves to the same folder as `index.php` so `.env`
beside `index.php` still works. In all other layouts `.env` belongs at `BASE_PATH`, not beside
`index.php`.

**`BASE_URL` must be the origin only — do not include the subfolder path:**

```
# Correct
BASE_URL=http://localhost
BASE_URL=https://mysite.example.com
BASE_URL=http://localhost:8080

# Wrong — causes url() to produce doubled paths like /myapp/myapp/route
BASE_URL=http://localhost/myapp
```

`BASE_URI` (the subfolder) is derived automatically from `SCRIPT_NAME` in `index.php` and
prepended by `url()`. Setting it in `BASE_URL` as well doubles the subfolder in every link.

---

## Deployment layouts

NoClass supports four deployment layouts. `noclass_base_path()` in `index.php`
detects them automatically.

```
Option 1 (recommended — app outside web root):
  /home/user/myapp/         ← BASE_PATH (app root, not web-accessible)
    config/, controllers/, models/, views/, init/, lib/ ...
  /home/user/public_html/   ← web root
    index.php, .htaccess, assets/

Option 2 (app inside web root):
  /public_html/app/         ← BASE_PATH
    config/, controllers/, models/, views/, init/, lib/ ...
  /public_html/
    index.php, .htaccess, assets/
```

Option 1 is recommended because `config/`, `init/`, `lib/`, `.env` are outside
the web root and cannot be accessed directly via HTTP.

---

## Database Migrations

NoClass includes a lightweight migration system for managing schema changes across deployments. It is a deployment tool — it is never loaded during normal request handling.

### Structure

```
database/
  migrations/
    001_baseline.sql     ← complete initial schema
    002_add_portal.sql   ← incremental change
    README.md            ← migration conventions
migrate.php              ← CLI runner (project root)
app/system/
  migrate.php            ← migration engine (loaded by CLI shim only)
```

### CLI usage

```bash
php app/system/migrate.php              # run all pending
php app/system/migrate.php status       # show applied / pending
php app/system/migrate.php run 002      # run matching prefix
php app/system/migrate.php rollback     # reverse last batch
php app/system/migrate.php fresh        # drop all + re-run (non-production only)
php app/system/migrate.php make name    # create new numbered file
```

### Migration file format

```sql
-- @up
ALTER TABLE users ADD COLUMN bio TEXT NULL;

-- @down
ALTER TABLE users DROP COLUMN bio;
```

The `@down` section is optional. Without it the migration cannot be individually rolled back. The entire file is treated as `@up` if no section markers are present.

### Tracking

Applied migrations are recorded in `nc_migrations`. Override the table name with `define('NC_MIGRATIONS_TABLE', 'my_migrations')` in `config/config.php`.

### Safety

`fresh` is blocked when `APP_ENV=production`. All activity is logged to `storage/logs/migrations.log`. Duplicate column errors are treated as warnings (safe for partial re-runs).

---

## noclass.js — HTTP Client

NoClass ships a lightweight JavaScript HTTP client at `app/assets/js/noclass.js`.
Zero dependencies. Vanilla ES6. ~3kb unminified.

### Setup

Add to your layout `<head>`:

```html
<meta name="base-url"   content="<?= url('') ?>">
<meta name="csrf-token" content="<?= csrf_token() ?>">
```

Include the script:

```html
<script src="<?= asset('js/noclass.js') ?>"></script>
```

### Usage

```js
// GET
nc.get('dashboard/stats')
  .then(data => console.log(data))
  .catch(err => nc.flash(err.message, 'danger'));

// POST (plain object — CSRF injected automatically)
nc.post('licenses/store', { name: 'Acme', email: 'a@b.com' })
  .then(data => nc.redirect('licenses'))
  .catch(err => nc.flash(err.message, 'danger'));

// POST with file upload (FormData)
nc.post('releases/upload', new FormData(form))
  .then(() => nc.flash('Uploaded.', 'success'))
  .catch(err => nc.flash(err.message, 'danger'));

// DELETE
nc.delete('apikeys/revoke/3')
  .then(() => nc.reload())
  .catch(err => nc.flash(err.message, 'danger'));
```

### Response shape

`nc` understands the NoClass API response shape from `api.php`:

| Server response | Promise result |
|---|---|
| `api_ok($data)` → `{ok:true, data:...}` | Resolves with `data` |
| `api_err($msg)` → `{ok:false, error:...}` | Rejects with `Error(message)` |
| Network failure | Rejects with `Error('Network error...')` |

### Helpers

```js
nc.url('licenses/show/1')   // full URL respecting BASE_URI
nc.flash('Saved', 'success') // show flash message without reload
nc.redirect('dashboard')     // redirect to a route
nc.reload()                  // reload current page
nc.confirm('Delete?', fn)    // confirm dialog then call fn
nc.serialize(formElement)    // form → plain object for nc.post()
```

### CSRF

CSRF tokens are read from `<meta name="csrf-token">` and injected automatically
into every POST/PUT/PATCH/DELETE request — both as `X-CSRF-Token` header and
as `csrf_token` in the request body. Developers never handle CSRF manually.

When the server rotates the token, the response header `X-CSRF-Token` is read
and the meta tag is updated for the next request.

---

## OPcache — why it matters

NoClass's `init/` auto-loader requires every file in `init/` on every request.
In production this is efficient **only** because OPcache compiles files once
and serves them from shared memory.

**Always verify OPcache is active in production:**

```php
<?php var_dump(opcache_get_status()['opcache_enabled']); ?>
```

Configure OPcache using the `php.ini` (production) and `php-development.ini`
(development) files provided at the project root. The comments in those files
explain each setting and the deploy checklist.

**After every production deployment, reload PHP-FPM:**

```bash
systemctl reload php8.x-fpm        # Linux
iisreset                            # Windows IIS
```

With `opcache.validate_timestamps=0`, new code is invisible until the process restarts.
