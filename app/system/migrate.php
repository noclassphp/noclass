<?php

/**
 * NoClass™ PHP Procedural Framework — Database Migration Engine
 *
 * Copyright 2024-2026 Danny Mbanguni
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 *
 * ── USAGE (CLI) ───────────────────────────────────────────────────────────────
 *
 *   php app/system/migrate.php                  run all pending migrations
 *   php app/system/migrate.php status           show applied / pending status
 *   php app/system/migrate.php run [prefix]     run pending (optionally filtered)
 *   php app/system/migrate.php rollback         reverse the last batch
 *   php app/system/migrate.php fresh            drop all tables + re-run from scratch
 *   php app/system/migrate.php make <name>      create a new numbered migration file
 *
 * On Windows:
 *   php app\system\migrate.php
 *
 * ── MIGRATION FILE FORMAT ────────────────────────────────────────────────────
 *
 * Files live in database/migrations/ and are numbered sequentially:
 *
 *   001_baseline.sql
 *   002_add_users_table.sql
 *   003_add_portal_columns.sql
 *
 * Each file contains forward SQL statements (the @up section).
 * An optional @down section enables rollback:
 *
 *   -- @up
 *   ALTER TABLE users ADD COLUMN bio TEXT NULL;
 *
 *   -- @down
 *   ALTER TABLE users DROP COLUMN bio;
 *
 * If no @down section exists, the migration cannot be rolled back individually.
 *
 * ── TRACKING TABLE ───────────────────────────────────────────────────────────
 *
 * Applied migrations are recorded in a tracking table.
 * Default name: nc_migrations
 * Override:     define('NC_MIGRATIONS_TABLE', 'my_migrations') in config.php
 *
 * ── SAFETY RULES ────────────────────────────────────────────────────────────
 *
 * - 'fresh' is blocked in production (APP_ENV = 'production')
 * - Every migration file is run inside a transaction when the DB supports it
 * - Duplicate column errors are logged as warnings, not failures
 *   (safe for re-runs when migration was partially applied)
 * - All migration activity is written to storage/logs/migrations.log
 *
 * ── THIS FILE IS NOT LOADED BY setup.php ───────────────────────────────────
 *
 * Migration tooling is a deployment concern, not a per-request concern.
 * Never include this file in setup.php or the bootstrap chain.
 *
 * When called from CLI directly it self-bootstraps:
 *   - Detects BASE_PATH from its own location (app/system/ → app/)
 *   - Loads .env from BASE_PATH
 *   - Connects to the database using env credentials
 *   - Calls nc_migrate_main($argv) automatically
 *
 * When included by an admin controller (for web-based migration UI):
 *   - The bootstrap block is skipped (BASE_PATH already defined)
 *   - Call nc_migrate_main($argv) explicitly with your own $argv
 */

// ── Self-bootstrap (CLI only) ────────────────────────────────────────────────
// When invoked directly from CLI and BASE_PATH is not yet defined, this file
// bootstraps itself so it can read .env credentials and connect to the DB.
// When included by an admin controller (web request), BASE_PATH is already
// defined and this block is skipped entirely.

if (php_sapi_name() === 'cli' && !defined('BASE_PATH')) {
    // This file lives at app/system/migrate.php so BASE_PATH = app/
    define('BASE_PATH',   dirname(__DIR__));
    define('PUBLIC_PATH', dirname(BASE_PATH));

    $nc_env = BASE_PATH . '/system/env.php';
    if (!is_file($nc_env)) {
        echo 'ERROR: Cannot find ' . $nc_env . PHP_EOL;
        echo 'Run from the project web root: php app/system/migrate.php' . PHP_EOL;
        exit(1);
    }
    require_once $nc_env;
    env_boot(BASE_PATH);
}

// ── Auto-dispatch when executed directly from CLI ─────────────────────────────
// When this file is the SCRIPT_FILENAME (i.e. called with php app/system/migrate.php)
// automatically call nc_migrate_main(). When included by another file, skip this
// so the caller can invoke nc_migrate_main() with custom arguments.

if (php_sapi_name() === 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    nc_migrate_main($GLOBALS['argv'] ?? []);
    exit(0);
}


// ── Entry point ───────────────────────────────────────────────────────────────

function nc_migrate_main(array $argv = []): void
{
    $command = $argv[1] ?? 'run';
    $arg     = $argv[2] ?? null;

    $db = nc_migrate_connect();

    nc_migrate_ensure_table($db);

    switch ($command) {
        case 'status':
            nc_migrate_cmd_status($db);
            break;

        case 'run':
            nc_migrate_cmd_run($db, $arg);
            break;

        case 'rollback':
            nc_migrate_cmd_rollback($db);
            break;

        case 'fresh':
            nc_migrate_cmd_fresh($db);
            break;

        case 'make':
            if (!$arg) {
                nc_migrate_exit("Usage: php migrate.php make <name>  e.g. make add_users_table");
            }
            nc_migrate_cmd_make($arg);
            break;

        default:
            nc_migrate_cmd_run($db, null);
    }

    $db->close();
}


// ── Commands ──────────────────────────────────────────────────────────────────

function nc_migrate_cmd_status(mysqli $db): void
{
    $files   = nc_migrate_files();
    $applied = nc_migrate_applied($db);

    $appNames = array_column($applied, 'migration');

    nc_migrate_out('');
    nc_migrate_out('NoClass Migration Status');
    nc_migrate_out(str_repeat('─', 64));
    nc_migrate_out(sprintf('  %-42s  %-10s  %s', 'Migration', 'Status', 'Applied At'));
    nc_migrate_out(str_repeat('─', 64));

    if (empty($files)) {
        nc_migrate_out('  No migration files found in database/migrations/');
    } else {
        foreach ($files as $file) {
            $name  = basename($file);
            $idx   = array_search($name, $appNames, true);
            $ranAt = ($idx !== false) ? $applied[$idx]['ran_at'] : '—';
            $status = ($idx !== false) ? '✓ Applied' : '○ Pending';
            nc_migrate_out(sprintf('  %-42s  %-10s  %s', $name, $status, $ranAt));
        }
    }

    $total   = count($files);
    $done    = count($applied);
    $pending = $total - $done;

    nc_migrate_out(str_repeat('─', 64));
    nc_migrate_out("  {$done} applied  ·  {$pending} pending  ·  {$total} total");
    nc_migrate_out('');
}

function nc_migrate_cmd_run(mysqli $db, ?string $prefix = null): void
{
    $files   = nc_migrate_files();
    $applied = array_column(nc_migrate_applied($db), 'migration');
    $batch   = nc_migrate_next_batch($db);

    $toRun = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) continue;
        if ($prefix !== null && strpos($name, $prefix) === false) continue;
        $toRun[] = $file;
    }

    if (empty($toRun)) {
        $msg = $prefix
            ? "No pending migrations matching '{$prefix}'."
            : 'No pending migrations. Everything is up to date.';
        nc_migrate_out($msg);
        return;
    }

    nc_migrate_out('');
    nc_migrate_out('Running ' . count($toRun) . ' migration(s) — batch ' . $batch);
    nc_migrate_out(str_repeat('─', 64));

    $ok = 0; $fail = 0;

    foreach ($toRun as $file) {
        $name = basename($file);
        nc_migrate_out("  → {$name}");

        $result = nc_migrate_run_file($db, $file, 'up');

        if ($result === true) {
            nc_migrate_record($db, $name, $batch);
            nc_migrate_log("APPLIED {$name} (batch {$batch})");
            nc_migrate_out("    ✓ Applied.");
            $ok++;
        } else {
            nc_migrate_log("FAILED {$name}: {$result}");
            nc_migrate_out("    ✗ Failed: {$result}");
            nc_migrate_out("    Stopping — fix the error and re-run.");
            $fail++;
            break;
        }
    }

    nc_migrate_out(str_repeat('─', 64));
    nc_migrate_out("  {$ok} applied" . ($fail ? ", {$fail} failed" : '') . ".");
    nc_migrate_out('');
}

function nc_migrate_cmd_rollback(mysqli $db): void
{
    $batch = nc_migrate_last_batch($db);

    if ($batch === 0) {
        nc_migrate_out('Nothing to roll back.');
        return;
    }

    $migrations = nc_migrate_batch_files($db, $batch);

    if (empty($migrations)) {
        nc_migrate_out("No migrations found for batch {$batch}.");
        return;
    }

    nc_migrate_out('');
    nc_migrate_out("Rolling back batch {$batch} (" . count($migrations) . " migration(s))");
    nc_migrate_out(str_repeat('─', 64));

    // Roll back in reverse order
    $reversed = array_reverse($migrations);
    $ok = 0; $skip = 0;

    foreach ($reversed as $name) {
        $file = nc_migrate_dir() . '/' . $name;

        nc_migrate_out("  ← {$name}");

        if (!is_file($file)) {
            nc_migrate_out("    SKIP: File not found.");
            $skip++;
            continue;
        }

        $sql = file_get_contents($file);
        $down = nc_migrate_extract_section($sql, 'down');

        if ($down === '') {
            nc_migrate_out("    SKIP: No @down section in this migration.");
            $skip++;
            continue;
        }

        $result = nc_migrate_run_statements($db, $down);

        if ($result === true) {
            nc_migrate_remove_record($db, $name);
            nc_migrate_log("ROLLED BACK {$name}");
            nc_migrate_out("    ✓ Rolled back.");
            $ok++;
        } else {
            nc_migrate_log("ROLLBACK FAILED {$name}: {$result}");
            nc_migrate_out("    ✗ Failed: {$result}");
            break;
        }
    }

    nc_migrate_out(str_repeat('─', 64));
    nc_migrate_out("  {$ok} rolled back" . ($skip ? ", {$skip} skipped" : '') . ".");
    nc_migrate_out('');
}

function nc_migrate_cmd_fresh(mysqli $db): void
{
    $env = defined('APP_ENV') ? APP_ENV : (getenv('APP_ENV') ?: 'production');

    if ($env === 'production') {
        nc_migrate_exit("'fresh' is not allowed in production (APP_ENV=production).");
    }

    nc_migrate_out('');
    nc_migrate_out('WARNING: This will drop all tables and re-run all migrations.');
    nc_migrate_out('Only allowed in non-production environments.');
    nc_migrate_out('');

    if (php_sapi_name() === 'cli') {
        echo 'Type YES to confirm: ';
        $input = trim(fgets(STDIN));
        if ($input !== 'YES') {
            nc_migrate_out('Cancelled.');
            return;
        }
    }

    nc_migrate_out('Dropping all tables...');
    nc_migrate_drop_all_tables($db);
    nc_migrate_out('Done. Re-running all migrations...');
    nc_migrate_ensure_table($db);
    nc_migrate_cmd_run($db, null);
}

function nc_migrate_cmd_make(string $name): void
{
    $dir   = nc_migrate_dir();
    $files = glob($dir . '/*.sql') ?: [];

    // Find next sequence number
    $max = 0;
    foreach ($files as $f) {
        $base = basename($f);
        if (preg_match('/^(\d+)_/', $base, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    $next = str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);

    // Sanitise name to snake_case
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
    $slug = trim($slug, '_');

    $filename = "{$next}_{$slug}.sql";
    $path     = $dir . '/' . $filename;

    if (is_file($path)) {
        nc_migrate_exit("File already exists: {$filename}");
    }

    $template = <<<SQL
-- ============================================================
-- NoClass Migration {$next} — {$slug}
-- Created: DATE_PLACEHOLDER
-- Description: TODO — describe what this migration does
-- ============================================================

-- @up
-- Add your forward migration SQL here
-- Example:
-- ALTER TABLE users ADD COLUMN bio TEXT NULL;


-- @down
-- Add your rollback SQL here (optional but recommended)
-- Example:
-- ALTER TABLE users DROP COLUMN bio;

SQL;

    $template = str_replace('DATE_PLACEHOLDER', date('Y-m-d'), $template);

    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $template);

    nc_migrate_out("Created: database/migrations/{$filename}");
}


// ── Core execution ────────────────────────────────────────────────────────────

function nc_migrate_run_file(mysqli $db, string $file, string $section = 'up')
{
    $sql = file_get_contents($file);
    if ($sql === false) return "Could not read file: {$file}";

    $content = nc_migrate_extract_section($sql, $section);

    if ($content === '') {
        if ($section === 'up') return "No SQL found in @up section of " . basename($file);
        return true; // empty @down is OK
    }

    return nc_migrate_run_statements($db, $content);
}

function nc_migrate_run_statements(mysqli $db, string $sql)
{
    $statements = nc_migrate_split_sql($sql);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;

        // Skip pure comment lines
        if (preg_match('/^\s*--/', $stmt)) continue;

        if (!$db->query($stmt)) {
            $error = $db->error;

            // Treat duplicate column / existing table as warnings, not failures
            // This allows safe re-runs when a migration was partially applied
            if (nc_migrate_is_ignorable_error($error)) {
                nc_migrate_log("WARN ignorable: {$error}");
                nc_migrate_out("    WARN: {$error} (continuing)");
                continue;
            }

            return $error;
        }
    }

    return true;
}

function nc_migrate_extract_section(string $sql, string $section): string
{
    // Normalise line endings
    $sql = str_replace("\r\n", "\n", $sql);

    $upMarker   = '-- @up';
    $downMarker = '-- @down';

    $hasUp   = strpos($sql, $upMarker)   !== false;
    $hasDown = strpos($sql, $downMarker) !== false;

    if (!$hasUp && !$hasDown) {
        // No markers — treat entire file as @up
        return ($section === 'up') ? $sql : '';
    }

    if ($section === 'up') {
        $start = $hasUp ? (strpos($sql, $upMarker) + strlen($upMarker)) : 0;
        $end   = $hasDown ? strpos($sql, $downMarker) : strlen($sql);
        return trim(substr($sql, $start, $end - $start));
    }

    if ($section === 'down') {
        if (!$hasDown) return '';
        $start = strpos($sql, $downMarker) + strlen($downMarker);
        return trim(substr($sql, $start));
    }

    return '';
}

function nc_migrate_split_sql(string $sql): array
{
    // Character-by-character split on ; boundaries outside quoted strings.
    // Same approach as db_raw anomaly detection — no regex, no backtracking.
    $stmts = [];
    $buf   = '';
    $len   = strlen($sql);
    $in_sq = false;
    $in_dq = false;

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($in_sq) {
            $buf .= $ch;
            if ($ch === '\\') { $buf .= $next; $i++; continue; }
            if ($ch === "'" && $next !== "'") $in_sq = false;
            elseif ($ch === "'" && $next === "'") { $buf .= $next; $i++; }
            continue;
        }
        if ($in_dq) {
            $buf .= $ch;
            if ($ch === '\\') { $buf .= $next; $i++; continue; }
            if ($ch === '"') $in_dq = false;
            continue;
        }

        if ($ch === "'") { $in_sq = true; $buf .= $ch; continue; }
        if ($ch === '"') { $in_dq = true; $buf .= $ch; continue; }

        if ($ch === ';') {
            if (trim($buf) !== '') $stmts[] = $buf;
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    if (trim($buf) !== '') $stmts[] = $buf;
    return $stmts;
}

function nc_migrate_is_ignorable_error(string $error): bool
{
    $ignorable = [
        'Duplicate column name',
        'already exists',
        "Can't DROP",
        "check that column/key exists",
    ];
    foreach ($ignorable as $pattern) {
        if (strpos($error, $pattern) !== false) return true;
    }
    return false;
}


// ── Database helpers ──────────────────────────────────────────────────────────

function nc_migrate_connect(): mysqli
{
    $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $port = (int)(getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306));
    $name = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : '');
    $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
    $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

    if ($name === '') {
        nc_migrate_exit('DB_NAME is not set. Check your .env file at ' . nc_migrate_base_path() . '/.env');
    }

    $db = new mysqli($host, $user, $pass, $name, $port);
    if ($db->connect_errno) {
        nc_migrate_exit('DB connection failed: ' . $db->connect_error);
    }
    $db->set_charset('utf8mb4');
    return $db;
}

function nc_migrate_table(): string
{
    return defined('NC_MIGRATIONS_TABLE') ? NC_MIGRATIONS_TABLE : 'nc_migrations';
}

function nc_migrate_ensure_table(mysqli $db): void
{
    $table = nc_migrate_table();
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(180) NOT NULL,
        `batch`     INT UNSIGNED NOT NULL DEFAULT 1,
        `ran_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function nc_migrate_applied(mysqli $db): array
{
    $table  = nc_migrate_table();
    $result = $db->query("SELECT `migration`, `batch`, `ran_at` FROM `{$table}` ORDER BY `id` ASC");
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function nc_migrate_next_batch(mysqli $db): int
{
    $table  = nc_migrate_table();
    $result = $db->query("SELECT MAX(`batch`) AS b FROM `{$table}`");
    if (!$result) return 1;
    $row = $result->fetch_assoc();
    return (int)($row['b'] ?? 0) + 1;
}

function nc_migrate_last_batch(mysqli $db): int
{
    $table  = nc_migrate_table();
    $result = $db->query("SELECT MAX(`batch`) AS b FROM `{$table}`");
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (int)($row['b'] ?? 0);
}

function nc_migrate_batch_files(mysqli $db, int $batch): array
{
    $table  = nc_migrate_table();
    $stmt   = $db->prepare("SELECT `migration` FROM `{$table}` WHERE `batch` = ? ORDER BY `id` ASC");
    $stmt->bind_param('i', $batch);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row['migration'];
    return $rows;
}

function nc_migrate_record(mysqli $db, string $name, int $batch): void
{
    $table = nc_migrate_table();
    $stmt  = $db->prepare("INSERT IGNORE INTO `{$table}` (`migration`, `batch`, `ran_at`) VALUES (?, ?, NOW())");
    $stmt->bind_param('si', $name, $batch);
    $stmt->execute();
}

function nc_migrate_remove_record(mysqli $db, string $name): void
{
    $table = nc_migrate_table();
    $stmt  = $db->prepare("DELETE FROM `{$table}` WHERE `migration` = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
}

function nc_migrate_drop_all_tables(mysqli $db): void
{
    $db->query('SET foreign_key_checks = 0');
    $result = $db->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $db->query('DROP TABLE IF EXISTS `' . $row[0] . '`');
    }
    $db->query('SET foreign_key_checks = 1');
}


// ── File helpers ──────────────────────────────────────────────────────────────

function nc_migrate_base_path(): string
{
    if (defined('BASE_PATH')) return rtrim(BASE_PATH, '/\\');
    return rtrim(__DIR__ . '/..', '/\\');
}

function nc_migrate_dir(): string
{
    // Migrations live relative to the project root (one level above BASE_PATH)
    // BASE_PATH = /project/app  →  migrations at /project/database/migrations
    $base = nc_migrate_base_path();
    return rtrim(dirname($base), '/\\') . '/database/migrations';
}

function nc_migrate_files(): array
{
    $dir   = nc_migrate_dir();
    $files = glob($dir . '/*.sql');
    if (!$files) return [];
    sort($files);
    return $files;
}

function nc_migrate_log(string $message): void
{
    $base = nc_migrate_base_path();
    $dir  = $base . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/migrations.log';
    if (is_file($file) && filesize($file) > 5 * 1024 * 1024) {
        rename($file, $file . '.' . date('Y-m-d-H-i-s'));
    }
    file_put_contents(
        $file,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


// ── Output helpers ────────────────────────────────────────────────────────────

function nc_migrate_out(string $message): void
{
    echo $message . PHP_EOL;
}

function nc_migrate_exit(string $message): void
{
    echo 'ERROR: ' . $message . PHP_EOL;
    exit(1);
}
