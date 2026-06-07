# Database Migrations

This folder contains numbered SQL migration files for NoClass applications.

---

## File naming convention

```
001_baseline.sql
002_add_users_table.sql
003_add_portal_columns.sql
```

Files are run in alphabetical (numeric) order. The number prefix determines execution sequence. Zero-pad to three digits so sorting works correctly up to 999 migrations.

---

## Creating a migration

**Via CLI (recommended):**

```bash
php app/system/migrate.php make add_portal_columns
# Creates: database/migrations/004_add_portal_columns.sql
```

**Manually:** copy the template below and increment the number prefix.

---

## Migration file format

Each file contains a `@up` section (required) and an optional `@down` section for rollback.

```sql
-- @up
-- Forward migration SQL
ALTER TABLE users ADD COLUMN bio TEXT NULL;

-- @down
-- Rollback SQL (optional but recommended)
ALTER TABLE users DROP COLUMN bio;
```

If no `@up` / `@down` markers are present, the entire file is treated as `@up`.

---

## Running migrations

```bash
php app/system/migrate.php              # run all pending
php app/system/migrate.php status       # show applied / pending status
php app/system/migrate.php run 003      # run specific migration matching prefix '003'
php app/system/migrate.php rollback     # reverse the last batch
php app/system/migrate.php fresh        # drop all tables + re-run (non-production only)
```

Run from the project web root (where `index.php` lives).

---

## Tracking table

Applied migrations are recorded in `nc_migrations`:

| Column | Description |
|---|---|
| `migration` | Filename e.g. `002_add_portal_columns.sql` |
| `batch` | Batch number — all migrations in one `run` share a batch |
| `ran_at` | Timestamp |

Override the table name in `config/config.php`:
```php
define('NC_MIGRATIONS_TABLE', 'my_migrations');
```

---

## Safety rules

- `fresh` is blocked when `APP_ENV=production`
- Duplicate column / already-exists errors are treated as warnings, not failures — safe for re-runs when a migration was partially applied
- All activity is logged to `storage/logs/migrations.log`
- Rollback requires a `-- @down` section in the migration file; migrations without one are skipped during rollback

---

## Application-specific baseline

Your application's `database/migrations/001_baseline.sql` should contain the complete initial schema for a fresh installation. This file is what `migrate.php fresh` re-runs after dropping all tables.
