<?php

/**
 * NoClass™ PHP Procedural Framework — Database Layer
 *
 * Copyright 2024-2026 Danny Mbanguni
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 *
 * ── PUBLIC API ────────────────────────────────────────────────────────────────
 *
 * Structured (table-based):
 *   db_select()         multi-row SELECT with WHERE / JOIN / ORDER / LIMIT
 *   db_find()           single row by primary key
 *   db_find_by()        single row by any column
 *   db_count()          integer COUNT with optional WHERE
 *   db_exists()         boolean existence check
 *   db_pluck()          flat array of one column's values
 *   db_aggregate()      SUM / AVG / MIN / MAX
 *   db_paginate()       rows + total + pagination meta
 *   db_search()         multi-column LIKE search
 *   db_insert()         INSERT single row → inserted ID
 *   db_batch_insert()   INSERT multiple rows
 *   db_update()         UPDATE with WHERE
 *   db_batch_update()   UPDATE multiple rows by key
 *   db_upsert()         INSERT … ON DUPLICATE KEY UPDATE
 *   db_delete()         DELETE with WHERE
 *   db_increment()      atomic numeric increment / decrement
 *
 * Raw SQL:
 *   db_fetch_all()      all rows  → array
 *   db_fetch_one()      first row → ?array
 *   db_fetch_value()    single value (COUNT, MAX …)
 *   db_fetch_column()   flat array of first-column values
 *   db_fetch_map()      key-value map keyed by first column
 *   db_exec()           raw SQL with no result (DDL, multi-statement)
 *
 * Streaming:
 *   db_chunk()          process large sets in memory-safe chunks
 *
 * Transactions:
 *   db_tx()             callable with automatic commit / rollback
 *   db_transaction()    alias for db_tx()
 *
 * ── INTERNAL (never call directly in application code) ───────────────────────
 *   db_raw()            all public functions route through here
 *                       anomaly detection is always on — security is not opt-in
 */

// ── Connection globals ────────────────────────────────────────────────────────
if (!isset($GLOBALS['__noclass_db_write']))      $GLOBALS['__noclass_db_write']      = null;
if (!isset($GLOBALS['__noclass_db_read']))       $GLOBALS['__noclass_db_read']       = null;
if (!isset($GLOBALS['__noclass_db_tx_depth']))   $GLOBALS['__noclass_db_tx_depth']   = 0;
if (!isset($GLOBALS['__noclass_db_bump_queue'])) $GLOBALS['__noclass_db_bump_queue'] = [];


// =============================================================================
// CONNECTION
// =============================================================================

function db_read_hosts(): array
{
    $csv = defined('DB_READ_HOSTS') ? trim((string)DB_READ_HOSTS) : '';
    if ($csv === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
}

function db_read_rr_index(int $n): int
{
    if ($n <= 1) return 0;
    $base    = defined('BASE_PATH') ? (string)BASE_PATH : '';
    $name    = defined('DB_NAME')   ? (string)DB_NAME   : 'db';
    $storage = ($base !== '' && is_dir($base . '/storage'))
        ? $base . '/storage' : sys_get_temp_dir();
    $file = rtrim($storage, '/\\') . '/.noclass_db_rr_' . md5($base . '|' . $name);

    $fh = @fopen($file, 'c+');
    if (!$fh) return mt_rand(0, $n - 1);

    $idx = 0;
    if (@flock($fh, LOCK_EX)) {
        $cur = (int)trim((string)stream_get_contents($fh));
        $idx = $cur % $n;
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, (string)($cur + 1));
        fflush($fh); flock($fh, LOCK_UN);
    } else {
        $idx = mt_rand(0, $n - 1);
    }
    fclose($fh);
    return $idx;
}

function db_read_pick_host(): array
{
    if (defined('DB_READ_HOST') && trim((string)DB_READ_HOST) !== '') {
        $h = trim((string)DB_READ_HOST);
        if (strpos($h, ':') !== false) {
            [$host, $port] = explode(':', $h, 2);
            return [trim($host), (int)$port];
        }
        return [$h, defined('DB_READ_PORT') ? (int)DB_READ_PORT : null];
    }
    $hosts = db_read_hosts();
    $n     = count($hosts);
    if ($n === 0) {
        return [
            defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1',
            defined('DB_PORT') ? (int)DB_PORT    : 3306,
        ];
    }
    $start = db_read_rr_index($n);
    for ($i = 0; $i < $n; $i++) {
        $h = $hosts[($start + $i) % $n];
        if (strpos($h, ':') !== false) {
            [$host, $port] = explode(':', $h, 2);
            if (trim($host) !== '') return [trim($host), (int)$port];
        } elseif (trim($h) !== '') {
            return [trim($h), defined('DB_READ_PORT') ? (int)DB_READ_PORT : (defined('DB_PORT') ? (int)DB_PORT : 3306)];
        }
    }
    return [defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1', defined('DB_PORT') ? (int)DB_PORT : 3306];
}

function db_in_tx(): bool
{
    return !empty($GLOBALS['__noclass_db_tx_depth']) && (int)$GLOBALS['__noclass_db_tx_depth'] > 0;
}

function db_pick_conn(string $sql): mysqli
{
    if (db_in_tx()) return db_write();
    $verb = strtoupper(strtok(ltrim($sql), " \t\n"));
    return in_array($verb, ['SELECT','SHOW','DESCRIBE','DESC','EXPLAIN'], true)
        ? db_read() : db_write();
}

function db_connect(string $role = 'write'): mysqli
{
    $role = (strtolower($role) === 'read') ? 'read' : 'write';
    if (db_in_tx()) $role = 'write';

    if ($role === 'read') {
        if (isset($GLOBALS['__noclass_db_read']) && $GLOBALS['__noclass_db_read'] instanceof mysqli) {
            return $GLOBALS['__noclass_db_read'];
        }
        [$host, $port] = db_read_pick_host();
        $user = defined('DB_READ_USER') ? (string)DB_READ_USER : (string)DB_USER;
        $pass = defined('DB_READ_PASS') ? (string)DB_READ_PASS : (string)DB_PASS;
        $name = defined('DB_READ_NAME') ? (string)DB_READ_NAME : (string)DB_NAME;
        $conn = mysqli_init();
        if (!mysqli_real_connect($conn, $host, $user, $pass, $name, (int)($port ?? DB_PORT))) {
            return db_connect('write');
        }
        mysqli_set_charset($conn, 'utf8mb4');
        $GLOBALS['__noclass_db_read'] = $conn;
        return $conn;
    }

    if (isset($GLOBALS['__noclass_db_write']) && $GLOBALS['__noclass_db_write'] instanceof mysqli) {
        global $mysqli;
        $mysqli = $GLOBALS['__noclass_db_write'];
        return $GLOBALS['__noclass_db_write'];
    }
    $conn = mysqli_init();
    if (!mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
        error_log('DB Connect Error: ' . mysqli_connect_error());
        if (defined('DEBUG') && DEBUG) die('DB Connect Error: ' . mysqli_connect_error());
        http_response_code(500);
        exit('Internal Server Error');
    }
    mysqli_set_charset($conn, 'utf8mb4');
    $GLOBALS['__noclass_db_write'] = $conn;
    global $mysqli;
    $mysqli = $conn;
    return $conn;
}

function db_write(): mysqli { return db_connect('write'); }
function db_read():  mysqli { return db_connect('read');  }


// =============================================================================
// INTERNAL — db_raw()
//
// Every public function routes through here.
// Security is always on — not opt-in.
// Never call this directly in application code.
// =============================================================================

function db_raw(string $sql, ...$params): mysqli_stmt
{
    global $mysqli;

    // Lazy-connect if db_connect() was skipped at boot
    if (!($mysqli instanceof mysqli)) {
        $mysqli = db_connect('write');
    }

    // ── SQL template anomaly detection ───────────────────────────────────────
    // Single O(n) pass, no regex. Inspects the template only — params are
    // bound via MySQLi and are never interpolated, so they need no inspection.
    $len = strlen($sql); $i = 0; $in_sq = false; $in_dq = false;

    while ($i < $len) {
        $ch   = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($in_sq) {
            if ($ch === '\\') { $i += 2; continue; }
            if ($ch === "'" && $next === "'") { $i += 2; continue; }
            if ($ch === "'") $in_sq = false;
            $i++; continue;
        }
        if ($in_dq) {
            if ($ch === '\\') { $i += 2; continue; }
            if ($ch === '"' && $next === '"') { $i += 2; continue; }
            if ($ch === '"') $in_dq = false;
            $i++; continue;
        }

        if ($ch === '-' && $next === '-') {
            $after = ($i + 2 < $len) ? $sql[$i + 2] : ' ';
            if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r" || $i + 2 === $len) {
                db_raw_reject($sql, $params, 'line_comment');
            }
        }
        if ($ch === '/' && $next === '*')                               db_raw_reject($sql, $params, 'block_comment');
        if ($ch === ';' && ltrim(substr($sql, $i + 1)) !== '')         db_raw_reject($sql, $params, 'stacked_statement');
        if ($ch === "'") { $in_sq = true;  $i++; continue; }
        if ($ch === '"') { $in_dq = true;  $i++; continue; }
        $i++;
    }
    if ($in_sq || $in_dq) db_raw_reject($sql, $params, 'unbalanced_string_literal');
    // ── end anomaly detection ─────────────────────────────────────────────────

    $conn = db_pick_conn($sql);
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new RuntimeException('Prepare failed: ' . mysqli_error($conn));

    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_raw_reject(string $sql, array $params, string $reason): void
{
    log_security_event('sql_anomaly_detected', [
        'reason'      => $reason,
        'sql'         => $sql,
        'param_count' => count($params),
        'ip'          => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
        'ua'          => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ]);
    throw new RuntimeException(
        (defined('DEBUG') && DEBUG)
            ? "db: SQL anomaly ({$reason}): {$sql}"
            : 'Database query error'
    );
}

function log_security_event(string $type, array $data): void
{
    $base = defined('BASE_PATH') ? BASE_PATH : sys_get_temp_dir();
    $file = $base . '/storage/logs/security.log';
    $dir  = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_file($file) && filesize($file) > 10 * 1024 * 1024) {
        rename($file, $file . '.' . date('Y-m-d-H-i-s'));
    }
    file_put_contents(
        $file,
        date('Y-m-d H:i:s') . " [{$type}] " . json_encode($data) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}


// =============================================================================
// RAW SQL PUBLIC API
// Each function accepts ($sql, array $params = []) — no spread operator needed.
// =============================================================================

/**
 * Fetch all rows from a raw SQL query.
 *
 *   $rows = db_fetch_all("SELECT * FROM products WHERE active = ?", [1]);
 *   $rows = db_fetch_all($sql, $params);
 *   $rows = db_fetch_all("SELECT * FROM settings");
 */
function db_fetch_all(string $sql, array $params = []): array
{
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * Fetch the first row. Returns null if no match.
 *
 *   $user = db_fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
 */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return null;
    $row = mysqli_fetch_assoc($result);
    return $row ?: null;
}

/**
 * Fetch a single column value from the first row.
 * Returns $default when no row found.
 *
 *   $count = db_fetch_value("SELECT COUNT(*) FROM orders WHERE status = ?", ['pending']);
 *   $name  = db_fetch_value("SELECT name FROM products WHERE id = ?", [$id]);
 *   $total = db_fetch_value("SELECT SUM(amount) FROM invoices WHERE paid = ?", [1], 0);
 */
function db_fetch_value(string $sql, array $params = [], $default = null)
{
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return $default;
    $row = mysqli_fetch_row($result);
    return ($row !== false && $row !== null) ? $row[0] : $default;
}

/**
 * Fetch a flat array of the first column's values from all rows.
 *
 *   $ids   = db_fetch_column("SELECT id FROM users WHERE role = ?", ['admin']);
 *   $slugs = db_fetch_column("SELECT slug FROM products WHERE active = ?", [1]);
 */
function db_fetch_column(string $sql, array $params = []): array
{
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return [];
    $out = [];
    while ($row = mysqli_fetch_row($result)) $out[] = $row[0];
    return $out;
}

/**
 * Fetch rows as a key-value map keyed by the first column.
 * $valueCol = null returns full rows as values.
 *
 *   $map  = db_fetch_map("SELECT id, name FROM products", []);
 *   // → [1 => 'ProductA', 2 => 'ProductB']
 *
 *   $byId = db_fetch_map("SELECT id, name, slug FROM products", [], null);
 *   // → [1 => ['id'=>1, 'name'=>'ProductA', 'slug'=>'...'], ...]
 */
function db_fetch_map(string $sql, array $params = [], ?string $valueCol = null): array
{
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return [];
    $map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $keys      = array_keys($row);
        $map[$row[$keys[0]]] = ($valueCol !== null) ? ($row[$valueCol] ?? null) : $row;
    }
    return $map;
}

/**
 * Execute raw SQL with no result — DDL, TRUNCATE, multi-statement.
 * Auto-invalidates cache versions for any tables modified.
 *
 *   db_exec("ALTER TABLE users ADD COLUMN bio TEXT");
 *   db_exec("TRUNCATE TABLE sessions");
 */
function db_exec(string $sql): bool
{
    $conn = db_write();
    if (!mysqli_multi_query($conn, $sql)) {
        throw new RuntimeException('SQL error: ' . mysqli_error($conn));
    }
    $result = mysqli_store_result($conn);
    while (mysqli_more_results($conn) && mysqli_next_result($conn)) {
        $extra = mysqli_store_result($conn);
        if ($extra instanceof mysqli_result) mysqli_free_result($extra);
    }
    foreach (db_extract_tables($sql) as $tbl) bumpVersion($tbl);
    return true;
}


// =============================================================================
// STRUCTURED PUBLIC API
// =============================================================================

/**
 * SELECT rows with optional WHERE, JOINs, ORDER, LIMIT.
 *
 * WHERE operators via column key: '=', LIKE, IN, BETWEEN, >, <, >=, <=
 *   db_select('users', '*', ['status' => 'active'], 'name ASC', '25')
 *   db_select('users', '*', ['role IN' => ['admin','mod']])
 *   db_select('orders', '*', ['amount >=' => 100, 'status' => 'paid'])
 *   db_select('posts', ['p.id','p.title','u.name'],
 *             ['p.status' => 'published'], 'p.created_at DESC', '10',
 *             [['LEFT', 'users u', 'u.id = p.author_id']])
 */
function db_select(
    string $table,
    $cols  = '*',
    array  $conds = [],
    string $order = '',
    string $limit = '',
    array  $joins = []
): array {
    [$sql, $params] = db_build_select($table, $cols, $conds, $order, $limit, $joins);

    $cacheKey = "{$table}.v" . getVersion($table) . '.' . md5($sql . json_encode($params));
    if (CACHING === CACHE_ENGINE) {
        $json = cache_get_versioned($cacheKey);
        if ($json !== null) return (array)json_decode($json, true);
    } elseif (CACHING === CACHE_FILE) {
        $json = fileCacheLoad($cacheKey);
        if ($json !== null) return (array)json_decode($json, true);
    }

    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    $rows   = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

    $encoded = json_encode($rows);
    if (CACHING === CACHE_ENGINE)      cache_set_versioned($cacheKey, $encoded, CACHE_TTL_DB);
    elseif (CACHING === CACHE_FILE)    fileCacheSave($cacheKey, $encoded, CACHE_TTL_DB);

    return $rows;
}

/**
 * Fetch a single row by primary key. Returns null if not found.
 *
 *   $user    = db_find('users', 42);
 *   $product = db_find('products', 'my-slug', 'slug');
 */
function db_find(string $table, $id, string $keyCol = 'id'): ?array
{
    $rows = db_select($table, '*', [$keyCol => $id], '', '1');
    return $rows[0] ?? null;
}

/**
 * Fetch a single row by any column value. Returns null if not found.
 *
 *   $user    = db_find_by('users', 'email', $email);
 *   $license = db_find_by('licenses', 'license_key', $key);
 */
function db_find_by(string $table, string $col, $value): ?array
{
    $rows = db_select($table, '*', [$col => $value], '', '1');
    return $rows[0] ?? null;
}

/**
 * COUNT rows matching optional WHERE conditions.
 *
 *   $total  = db_count('orders');
 *   $active = db_count('users', ['status' => 'active']);
 */
function db_count(string $table, array $conds = []): int
{
    [$sql, $params] = db_build_select($table, 'COUNT(*) AS n', $conds);
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return (int)($row['n'] ?? 0);
}

/**
 * Boolean existence check.
 *
 *   if (db_exists('users', ['email' => $email])) { ... }
 */
function db_exists(string $table, array $conds): bool
{
    return db_count($table, $conds) > 0;
}

/**
 * Fetch a flat array of a single column's values with optional WHERE.
 *
 *   $emails = db_pluck('users', 'email', ['status' => 'active']);
 *   $ids    = db_pluck('products', 'id');
 */
function db_pluck(string $table, string $col, array $conds = [], string $order = ''): array
{
    [$sql, $params] = db_build_select($table, "`{$col}`", $conds, $order);
    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    if (!$result) return [];
    $out = [];
    while ($row = mysqli_fetch_row($result)) $out[] = $row[0];
    return $out;
}

/**
 * Aggregate function: COUNT, SUM, AVG, MIN, MAX.
 *
 *   $revenue = db_aggregate('orders', 'SUM', 'amount', ['status' => 'paid']);
 *   $oldest  = db_aggregate('users', 'MIN', 'created_at');
 */
function db_aggregate(string $table, string $func, string $col, array $conds = [], array $joins = [])
{
    $func     = strtoupper(preg_replace('/[^A-Za-z_]/', '', $func));
    [$sql, $params] = db_build_select($table, "{$func}(`{$col}`) AS agg_value", $conds, '', '', $joins);

    $cacheKey = "agg.{$table}.{$func}.{$col}.v" . getVersion($table) . '.' . md5($sql . json_encode($params));
    if (CACHING === CACHE_ENGINE) {
        $cached = cache_get_versioned($cacheKey);
        if ($cached !== null) return is_numeric($cached) ? $cached + 0 : $cached;
    } elseif (CACHING === CACHE_FILE) {
        $cached = fileCacheLoad($cacheKey);
        if ($cached !== null) return is_numeric($cached) ? $cached + 0 : $cached;
    }

    $result = mysqli_stmt_get_result(db_raw($sql, ...$params));
    $value  = ($result && ($row = mysqli_fetch_assoc($result))) ? $row['agg_value'] : null;

    if (CACHING === CACHE_ENGINE)      cache_set_versioned($cacheKey, (string)$value, CACHE_TTL_DB);
    elseif (CACHING === CACHE_FILE)    fileCacheSave($cacheKey, (string)$value, CACHE_TTL_DB);

    return $value;
}

/**
 * Paginate results. Returns rows + pagination metadata.
 *
 *   $result = db_paginate('orders', '*', ['status' => 'active'], 1, 25, 'created_at DESC');
 *   foreach ($result['data'] as $row) { ... }
 *   $total = $result['pagination']['total'];
 *   $pages = $result['pagination']['pages'];
 */
function db_paginate(
    string $table,
    $cols    = '*',
    array  $conds   = [],
    int    $page    = 1,
    int    $perPage = 20,
    string $order   = '',
    array  $joins   = []
): array {
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;
    $rows    = db_select($table, $cols, $conds, $order, "{$perPage} OFFSET {$offset}", $joins);
    $total   = db_count($table, $conds);
    return [
        'data'       => $rows,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int)ceil($total / max(1, $perPage)),
            'offset'   => $offset,
        ],
    ];
}

/**
 * Multi-column LIKE search with optional structured WHERE conditions.
 *
 *   $results = db_search('customers', ['name','email','company'], 'acme');
 *   $results = db_search('licenses', ['license_key'], $q, ['status' => 'active']);
 */
function db_search(
    string $table,
    array  $searchCols,
    string $term,
    array  $conds  = [],
    string $order  = '',
    string $limit  = '',
    array  $joins  = []
): array {
    if ($term === '' || empty($searchCols)) {
        return db_select($table, '*', $conds, $order, $limit, $joins);
    }

    $sql    = "SELECT * FROM `{$table}`";
    $params = [];

    foreach ($joins as [$type, $t, $on]) {
        $sql .= " {$type} JOIN `{$t}` ON {$on}";
    }

    $andClauses  = [];
    $likeClauses = [];
    $pattern     = '%' . $term . '%';

    foreach ($conds as $colOp => $val) {
        $andClauses[] = "`{$colOp}` = ?";
        $params[]     = $val;
    }
    foreach ($searchCols as $col) {
        $likeClauses[] = "`{$col}` LIKE ?";
        $params[]      = $pattern;
    }

    if ($andClauses && $likeClauses) {
        $sql .= ' WHERE (' . implode(' AND ', $andClauses) . ') AND (' . implode(' OR ', $likeClauses) . ')';
    } elseif ($andClauses) {
        $sql .= ' WHERE ' . implode(' AND ', $andClauses);
    } elseif ($likeClauses) {
        $sql .= ' WHERE ' . implode(' OR ', $likeClauses);
    }

    if ($order) $sql .= " ORDER BY {$order}";
    if ($limit) $sql .= " LIMIT {$limit}";

    return db_fetch_all($sql, $params);
}

/**
 * INSERT a single row. Returns the inserted ID.
 *
 *   $id = db_insert('users', ['name' => 'Danny', 'email' => 'danny@example.com']);
 */
function db_insert(string $table, array $data): int
{
    if (empty($data)) throw new InvalidArgumentException('db_insert: data array is empty');
    $cols = array_keys($data);
    $sql  = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ("
          . implode(',', array_fill(0, count($cols), '?')) . ")";
    db_raw($sql, ...array_values($data));
    $id = (int)mysqli_insert_id(db_write());
    bumpVersion($table);
    return $id;
}

/**
 * INSERT multiple rows in a single query. Returns affected rows.
 *
 *   db_batch_insert('logs', [
 *       ['user_id' => 1, 'action' => 'login'],
 *       ['user_id' => 2, 'action' => 'logout'],
 *   ]);
 */
function db_batch_insert(string $table, array $rows): int
{
    if (empty($rows)) return 0;
    $cols   = array_keys($rows[0]);
    $phRow  = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $params = [];
    foreach ($rows as $r) {
        foreach ($cols as $c) $params[] = $r[$c] ?? null;
    }
    $sql  = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES "
          . implode(',', array_fill(0, count($rows), $phRow));
    $stmt = db_raw($sql, ...$params);
    $aff  = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * UPDATE rows matching $conds. Returns affected rows.
 * Refuses an empty $conds to prevent unbounded UPDATE.
 *
 *   db_update('users', ['status' => 'suspended'], ['id' => $id]);
 *   db_update('licenses', ['expires_at' => $date], ['customer_id' => $cid]);
 */
function db_update(string $table, array $data, array $conds): int
{
    if (empty($data))  throw new InvalidArgumentException('db_update: data array is empty');
    if (empty($conds)) throw new InvalidArgumentException('db_update: conds is empty — refusing unbounded UPDATE');

    $sets   = []; $params = [];
    foreach ($data as $col => $val)  { $sets[]   = "`{$col}` = ?"; $params[] = $val; }
    $wheres = [];
    foreach ($conds as $col => $val) { $wheres[] = "`{$col}` = ?"; $params[] = $val; }

    $stmt = db_raw("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres), ...$params);
    $aff  = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * UPDATE multiple rows with different values per row (CASE-based single query).
 * Returns total affected rows.
 *
 *   db_batch_update('products', 'id', [
 *       ['id' => 1, 'price' => 19.99, 'stock' => 50],
 *       ['id' => 2, 'price' => 24.50, 'stock' => 30],
 *   ]);
 */
function db_batch_update(string $table, string $keyCol, array $rows): int
{
    if (empty($rows)) return 0;
    $cases  = []; $params = [];
    foreach ($rows as $r) {
        $id = $r[$keyCol];
        foreach ($r as $col => $val) {
            if ($col === $keyCol) continue;
            $cases[$col][] = 'WHEN ? THEN ?';
            $params[]      = $id;
            $params[]      = $val;
        }
    }
    $setClauses = [];
    foreach ($cases as $col => $whens) {
        $setClauses[] = "`{$col}` = CASE `{$keyCol}` " . implode(' ', $whens) . " ELSE `{$col}` END";
    }
    $ids    = array_column($rows, $keyCol);
    $idPh   = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($params, $ids);
    $stmt   = db_raw("UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `{$keyCol}` IN ({$idPh})", ...$params);
    $aff    = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * INSERT … ON DUPLICATE KEY UPDATE.
 * Returns: 1 = inserted, 2 = updated, 0 = no change.
 *
 *   db_upsert('settings', ['key' => 'theme', 'value' => 'dark'], ['value']);
 */
function db_upsert(string $table, array $data, array $updateCols): int
{
    if (empty($data))       throw new InvalidArgumentException('db_upsert: data array is empty');
    if (empty($updateCols)) throw new InvalidArgumentException('db_upsert: updateCols is empty');

    $cols = array_keys($data);
    $upd  = array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $updateCols);
    $sql  = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ("
          . implode(',', array_fill(0, count($cols), '?')) . ")"
          . " ON DUPLICATE KEY UPDATE " . implode(', ', $upd);

    $stmt = db_raw($sql, ...array_values($data));
    $aff  = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * DELETE rows matching $conds. Returns affected rows.
 * Supports operators in column keys: 'created_at <' => $date
 * Refuses an empty $conds to prevent accidental full-table delete.
 *
 *   db_delete('sessions', ['user_id' => $id]);
 *   db_delete('logs', ['created_at <' => date('Y-m-d', strtotime('-90 days'))]);
 */
function db_delete(string $table, array $conds): int
{
    if (empty($conds)) throw new InvalidArgumentException('db_delete: conds is empty — refusing unbounded DELETE');

    $wheres = []; $params = [];
    foreach ($conds as $colOp => $val) {
        if (strpos((string)$colOp, ' ') !== false) {
            [$col, $op] = explode(' ', $colOp, 2);
            $wheres[] = "`{$col}` " . strtoupper($op) . " ?";
        } else {
            $wheres[] = "`{$colOp}` = ?";
        }
        $params[] = $val;
    }

    $stmt = db_raw("DELETE FROM `{$table}` WHERE " . implode(' AND ', $wheres), ...$params);
    $aff  = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * Atomically increment (positive) or decrement (negative) a numeric column.
 * Returns affected rows.
 *
 *   db_increment('products', 'download_count', 1,   ['id' => $id]);
 *   db_increment('accounts', 'balance',       -50,  ['id' => $id]);
 */
function db_increment(string $table, string $col, int $by, array $conds): int
{
    if (empty($conds)) throw new InvalidArgumentException('db_increment: conds is empty');
    $wheres = []; $params = [$by];
    foreach ($conds as $c => $v) { $wheres[] = "`{$c}` = ?"; $params[] = $v; }
    $stmt = db_raw("UPDATE `{$table}` SET `{$col}` = `{$col}` + ? WHERE " . implode(' AND ', $wheres), ...$params);
    $aff  = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * Process a large result set in memory-safe chunks.
 * The callback receives an array of rows per chunk.
 * Return false from the callback to stop early.
 *
 *   db_chunk("SELECT * FROM activations WHERE status = ?", ['active'], 500,
 *       function(array $rows): void {
 *           foreach ($rows as $row) { process($row); }
 *       }
 *   );
 */
function db_chunk(string $sql, array $params, int $chunkSize, callable $callback): void
{
    $chunkSize = max(1, $chunkSize);
    $offset    = 0;
    $base      = rtrim(trim($sql), ';');

    while (true) {
        $rows = db_fetch_all("{$base} LIMIT {$chunkSize} OFFSET {$offset}", $params);
        if (empty($rows)) break;
        if ($callback($rows) === false) break;
        if (count($rows) < $chunkSize) break;
        $offset += $chunkSize;
    }
}


// =============================================================================
// TRANSACTIONS
// =============================================================================

function db_tx_begin(): void
{
    $conn  = db_write();
    $depth = (int)($GLOBALS['__noclass_db_tx_depth'] ?? 0);
    if ($depth === 0) {
        if (!mysqli_begin_transaction($conn)) {
            throw new RuntimeException('DB begin_transaction error: ' . mysqli_error($conn));
        }
        $GLOBALS['__noclass_db_bump_queue'] = [];
    } else {
        @mysqli_query($conn, 'SAVEPOINT SP_' . $depth);
    }
    $GLOBALS['__noclass_db_tx_depth'] = $depth + 1;
}

function db_tx_commit(): void
{
    $conn  = db_write();
    $depth = max(0, (int)($GLOBALS['__noclass_db_tx_depth'] ?? 0));
    if ($depth === 0) return;
    if (--$depth === 0) {
        if (!mysqli_commit($conn)) throw new RuntimeException('DB commit error: ' . mysqli_error($conn));
        foreach (array_keys((array)($GLOBALS['__noclass_db_bump_queue'] ?? [])) as $tbl) bumpVersion_immediate($tbl);
        $GLOBALS['__noclass_db_bump_queue'] = [];
    }
    $GLOBALS['__noclass_db_tx_depth'] = $depth;
}

function db_tx_rollback(): void
{
    $conn  = db_write();
    $depth = max(0, (int)($GLOBALS['__noclass_db_tx_depth'] ?? 0));
    if ($depth === 0) return;
    if (--$depth === 0) {
        if (!mysqli_rollback($conn)) throw new RuntimeException('DB rollback error: ' . mysqli_error($conn));
        $GLOBALS['__noclass_db_bump_queue'] = [];
    } else {
        @mysqli_query($conn, 'ROLLBACK TO SAVEPOINT SP_' . $depth);
    }
    $GLOBALS['__noclass_db_tx_depth'] = $depth;
}

/**
 * Execute a callable inside a transaction. Auto-commits on success,
 * auto-rolls back on any exception. Supports nested transactions via savepoints.
 *
 *   $id = db_tx(function() use ($data) {
 *       $id = db_insert('licenses', $data['license']);
 *       db_insert('activations', ['license_id' => $id]);
 *       return $id;
 *   });
 */
function db_tx(callable $fn)
{
    db_tx_begin();
    try {
        $result = $fn();
        db_tx_commit();
        return $result;
    } catch (Throwable $e) {
        db_tx_rollback();
        throw $e;
    }
}

/** Alias for db_tx(). */
function db_transaction(callable $fn) { return db_tx($fn); }


// =============================================================================
// INTERNAL HELPERS
// =============================================================================

/**
 * Build a SELECT SQL string and bound params array.
 * Used by db_select, db_count, db_aggregate, db_pluck, db_paginate.
 */
function db_build_select(
    string $table,
    $cols  = '*',
    array  $conds = [],
    string $order = '',
    string $limit = '',
    array  $joins = []
): array {
    $colsList = is_array($cols) ? implode(', ', $cols) : (string)$cols;
    $sql      = "SELECT {$colsList} FROM `{$table}`";
    $params   = [];

    foreach ($joins as [$type, $t, $on]) {
        $sql .= " {$type} JOIN `{$t}` ON {$on}";
    }

    if ($conds) {
        $clauses = [];
        foreach ($conds as $colOp => $val) {
            if (strpos((string)$colOp, ' ') !== false) {
                [$col, $op] = explode(' ', $colOp, 2);
                $op = strtoupper(trim($op));
                switch ($op) {
                    case 'IN':
                        $clauses[] = "`{$col}` IN (" . implode(',', array_fill(0, count($val), '?')) . ")";
                        $params    = array_merge($params, $val);
                        continue 2;
                    case 'BETWEEN':
                        $clauses[] = "`{$col}` BETWEEN ? AND ?";
                        $params[]  = $val[0]; $params[] = $val[1];
                        continue 2;
                    case 'LIKE':
                        $clauses[] = "`{$col}` LIKE ?";
                        $params[]  = $val;
                        continue 2;
                    default:
                        $clauses[] = "`{$col}` {$op} ?";
                        $params[]  = $val;
                        continue 2;
                }
            }
            if ($val === null) {
                $clauses[] = "`{$colOp}` IS NULL";
            } else {
                $clauses[] = "`{$colOp}` = ?";
                $params[]  = $val;
            }
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    if ($order) $sql .= " ORDER BY {$order}";
    if ($limit) $sql .= " LIMIT {$limit}";

    return [$sql, $params];
}

/**
 * Extract table names touched by DML statements.
 * Used by db_exec() to invalidate cache versions.
 */
function db_extract_tables(string $sql): array
{
    $stmts = []; $buf = ''; $inS = $inD = $inBt = $inBkt = false;
    for ($i = 0, $L = strlen($sql); $i < $L; $i++) {
        $c = $sql[$i];
        if      (!$inD && !$inBkt && !$inBt && $c === "'")  $inS   = !$inS;
        elseif  (!$inS && !$inBkt && !$inBt && $c === '"')  $inD   = !$inD;
        elseif  (!$inS && !$inD   && !$inBt && $c === '[')  $inBkt = true;
        elseif  ($inBkt && $c === ']')                       $inBkt = false;
        elseif  (!$inS && !$inD   && !$inBkt && $c === '`') $inBt  = !$inBt;
        if ($c === ';' && !$inS && !$inD && !$inBkt && !$inBt) {
            $stmts[] = $buf; $buf = '';
        } else {
            $buf .= $c;
        }
    }
    if (trim($buf) !== '') $stmts[] = $buf;

    $seen = [];
    $dmls = ['insert into ','update ','delete from ','merge into ','truncate table ','replace into '];

    foreach ($stmts as $stmt) {
        $t = trim($stmt); if (!$t) continue;
        $low = strtolower($t); $found = null; $off = 0;
        if (strpos($low, 'with ') === 0) {
            $best = PHP_INT_MAX;
            foreach ($dmls as $kw) {
                $p = stripos($t, $kw);
                if ($p !== false && $p < $best) { $best = $p; $found = $kw; }
            }
            if (!$found) continue;
            $off = $best + strlen($found);
        } else {
            foreach ($dmls as $kw) {
                if (strpos($low, $kw) === 0) { $found = $kw; $off = strlen($kw); break; }
            }
            if (!$found) continue;
        }
        $rest = ltrim(substr($t, $off)); $c0 = $rest[0] ?? ''; $tbl = '';
        if ($c0 === '`' || $c0 === '"' || $c0 === '[') {
            $close = $c0 === '[' ? ']' : $c0;
            $end   = strpos($rest, $close, 1);
            if ($end !== false) $tbl = substr($rest, 1, $end - 1);
        } else {
            $tbl = substr($rest, 0, strcspn($rest, " \t\n\r\0\x0B`\"[,(."));
        }
        if (strpos($tbl, '.') !== false) { $parts = explode('.', $tbl); $tbl = end($parts); }
        $tbl = strtolower($tbl);
        if ($tbl !== '') $seen[$tbl] = true;
    }
    return array_keys($seen);
}

/** File cache helpers */
function fileCacheLoad(string $key): ?string
{
    $fn = BASE_PATH . '/cache/' . md5($key) . '.cache';
    if (!is_file($fn)) return null;
    $blob  = @file_get_contents($fn);
    if (!$blob) return null;
    $parts = explode("\n", $blob, 3);
    if (count($parts) < 3) { @unlink($fn); return null; }
    [$ts, $ttl, $data] = $parts;
    if ((int)$ttl !== 0 && (time() - (int)$ts) >= (int)$ttl) { @unlink($fn); return null; }
    return $data;
}

function fileCacheSave(string $key, string $data, int $ttl = 0): void
{
    $dir = BASE_PATH . '/cache';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . '/' . md5($key) . '.cache', time() . "\n" . $ttl . "\n" . $data, LOCK_EX);
}

/** Version helpers */
function getVersion(string $table): int
{
    if (CACHING === CACHE_ENGINE) {
        return (int)cache_command("GETVER {$table}");
    }
    if (CACHING === CACHE_FILE) {
        $f = BASE_PATH . "/cache/{$table}.ver";
        return is_file($f) ? (int)file_get_contents($f) : 1;
    }
    return 1;
}

function bumpVersion_immediate(string $table): void
{
    switch (CACHING) {
        case CACHE_ENGINE:
            cache_tx_begin(); cache_command("BUMPVER {$table}"); cache_tx_commit();
            break;
        case CACHE_FILE:
            $f = BASE_PATH . "/cache/{$table}.ver";
            file_put_contents($f, (string)((int)(is_file($f) ? file_get_contents($f) : 1) + 1));
            foreach (glob(BASE_PATH . "/cache/{$table}.*.cache") ?: [] as $fn) @unlink($fn);
            break;
    }
}

function bumpVersion(string $table): void
{
    $table = strtolower(trim($table));
    if ($table === '') return;
    if (db_in_tx()) { $GLOBALS['__noclass_db_bump_queue'][$table] = true; return; }
    bumpVersion_immediate($table);
}
