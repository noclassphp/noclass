<?php

/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

declare(strict_types=1);


// --------------------------------------------------
// Globals: read/write split + transactions
// --------------------------------------------------
if (!isset($GLOBALS['__noclass_db_write'])) $GLOBALS['__noclass_db_write'] = null;
if (!isset($GLOBALS['__noclass_db_read']))  $GLOBALS['__noclass_db_read']  = null;
if (!isset($GLOBALS['__noclass_db_tx_depth'])) $GLOBALS['__noclass_db_tx_depth'] = 0;
if (!isset($GLOBALS['__noclass_db_bump_queue'])) $GLOBALS['__noclass_db_bump_queue'] = array();

// --------------------------------------------------
// Connection
// --------------------------------------------------
/**
 * Get the MySQLi connection (singleton).
 *
 * @return mysqli
 */

// Global mysqli connection
//global $mysqli; // initialized elsewhere


// --------------------------------------------------
// Read replica selection (round-robin)
// --------------------------------------------------

function db_read_hosts()
{
    $csv = defined('DB_READ_HOSTS') ? trim((string)DB_READ_HOSTS) : '';
    if ($csv === '') return array();
    $parts = array_filter(array_map('trim', explode(',', $csv)));
    return array_values($parts);
}

function db_read_rr_index($n)
{
    $n = (int)$n;
    if ($n <= 1) return 0;

    $file = defined('DB_READ_RR_FILE') ? (string)DB_READ_RR_FILE : '';
    if ($file === '') {
        $base = defined('BASE_PATH') ? (string)BASE_PATH : '';
        $name = defined('DB_NAME') ? (string)DB_NAME : 'db';
        $hash = md5($base . '|' . $name);

        $storage = ($base !== '' && is_dir($base . '/storage')) ? ($base . '/storage') : sys_get_temp_dir();
        $file = rtrim($storage, "/\\") . '/.noclass_db_read_rr_' . $hash;
    }

    $fh = @fopen($file, 'c+');
    if (!$fh) return mt_rand(0, $n - 1);

    $idx = 0;
    if (@flock($fh, LOCK_EX)) {
        $raw = stream_get_contents($fh);
        $cur = (is_string($raw) && trim($raw) !== '') ? (int)trim($raw) : 0;

        $idx  = $cur % $n;
        $next = $cur + 1;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string)$next);

        fflush($fh);
        flock($fh, LOCK_UN);
    } else {
        $idx = mt_rand(0, $n - 1);
    }

    fclose($fh);
    return $idx;
}

function db_read_pick_host()
{
    // Single host mode
    if (defined('DB_READ_HOST') && trim((string)DB_READ_HOST) !== '') {
        $h = trim((string)DB_READ_HOST);
        if (strpos($h, ':') !== false) {
            $parts = explode(':', $h, 2);
            return array(trim($parts[0]), (int)trim($parts[1]));
        }
        $port = defined('DB_READ_PORT') ? (int)DB_READ_PORT : null;
        return array($h, $port);
    }

    $hosts = db_read_hosts();
    $n = count($hosts);

    if ($n === 0) {
        $host = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        return array($host, $port);
    }

    $start = db_read_rr_index($n);

    for ($i = 0; $i < $n; $i++) {
        $idx = ($start + $i) % $n;
        $h = $hosts[$idx];

        if (strpos($h, ':') !== false) {
            $parts = explode(':', $h, 2);
            $host = trim($parts[0]);
            $port = (int)trim($parts[1]);
            if ($host !== '') return array($host, $port);
        } else {
            $host = trim($h);
            if ($host !== '') {
                $port = defined('DB_READ_PORT') ? (int)DB_READ_PORT : (defined('DB_PORT') ? (int)DB_PORT : 3306);
                return array($host, $port);
            }
        }
    }

    $host = defined('DB_HOST') ? (string)DB_HOST : '127.0.0.1';
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    return array($host, $port);
}

function db_in_tx()
{
    return !empty($GLOBALS['__noclass_db_tx_depth']) && (int)$GLOBALS['__noclass_db_tx_depth'] > 0;
}

function db_pick_conn($sql)
{
    if (db_in_tx()) return db_write();

    $s = ltrim((string)$sql);
    $verb = strtoupper(strtok($s, " 	
"));
    switch ($verb) {
        case 'SELECT':
        case 'SHOW':
        case 'DESCRIBE':
        case 'DESC':
        case 'EXPLAIN':
            return db_read();
        default:
            return db_write();
    }
}

function db_connect($role = 'write')
{
    $role = (strtolower((string)$role) === 'read') ? 'read' : 'write';

    // During DB transactions, force reads to write (read-your-writes)
    if (db_in_tx()) $role = 'write';

    if ($role === 'read') {
        if (isset($GLOBALS['__noclass_db_read']) && $GLOBALS['__noclass_db_read'] instanceof mysqli) {
            return $GLOBALS['__noclass_db_read'];
        }

        $picked = db_read_pick_host();
        $host = $picked[0];
        $pickedPort = isset($picked[1]) ? $picked[1] : null;

        $user = defined('DB_READ_USER') ? (string)DB_READ_USER : (defined('DB_USER') ? (string)DB_USER : DB_USER);
        $pass = defined('DB_READ_PASS') ? (string)DB_READ_PASS : (defined('DB_PASS') ? (string)DB_PASS : DB_PASS);
        $name = defined('DB_READ_NAME') ? (string)DB_READ_NAME : (defined('DB_NAME') ? (string)DB_NAME : DB_NAME);
        $port = ($pickedPort !== null) ? (int)$pickedPort : (defined('DB_READ_PORT') ? (int)DB_READ_PORT : DB_PORT);

        $conn = mysqli_init();
        if (!mysqli_real_connect($conn, $host, $user, $pass, $name, $port)) {
            // Safe fallback
            return db_connect('write');
        }
        mysqli_set_charset($conn, 'utf8mb4');

        $GLOBALS['__noclass_db_read'] = $conn;
        return $conn;
    }

    // write
    if (isset($GLOBALS['__noclass_db_write']) && $GLOBALS['__noclass_db_write'] instanceof mysqli) {
        global $mysqli;
        $mysqli = $GLOBALS['__noclass_db_write'];
        return $GLOBALS['__noclass_db_write'];
    }

    $conn = mysqli_init();
    if (!mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
        error_log("DB Connect Error: " . mysqli_connect_error());
        if (defined('DEBUG') && DEBUG) {
            die("DB Connect Error: " . mysqli_connect_error());
        }
        http_response_code(500);
        exit("Internal Server Error");
    }
    mysqli_set_charset($conn, 'utf8mb4');

    $GLOBALS['__noclass_db_write'] = $conn;

    global $mysqli;
    $mysqli = $conn;

    return $conn;
}

function db_write() { return db_connect('write'); }
function db_read()  { return db_connect('read'); }


/**
 * Execute raw SQL (multi‐statement), auto‐bump any tables it touches,
 * and return mysqli_result or true.
 */
function db_exec($sql)
{
    $mysqli = db_write();


    // 1) Run the SQL
    if (! mysqli_multi_query($mysqli, $sql)) {
        throw new RuntimeException('SQL error: ' . mysqli_error($mysqli));
    }
    $result = mysqli_store_result($mysqli);
    while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli)) {
        $extra = mysqli_store_result($mysqli);
        if ($extra) mysqli_free_result($extra);
    }

    // 2) Version invalidation
    $tables = extractTablesFromSql($sql);

    if (CACHING === CACHE_ENGINE) {
        // start atomic bump
        cache_tx_begin();
        foreach ($tables as $tbl) {
            cache_command("BUMPVER {$tbl}");
        }
        cache_tx_commit();
    } else {
        // file or none
        foreach ($tables as $tbl) {
            bumpVersion($tbl);
        }
    }

    // 3) Return
    return $result !== false ? $result : true;
}



/**
 * Execute parameterized query and return mysqli_stmt.
 */
function db_raw($sql, ...$params)
{
    global $mysqli;
    $stmt = mysqli_prepare($mysqli, $sql);
    if (! $stmt) {
        throw new RuntimeException('Prepare failed: ' . mysqli_error($mysqli));
    }
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p))      $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else                  $types .= 's';
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

// Advance SQL Injection protection
function db_raw_secure($sql, ...$params) {
    // Check for dangerous patterns in SQL
    $dangerous_patterns = [
        '/\b(union|select|insert|update|delete|drop|alter|create|truncate)\b.*\b(union|select|insert|update|delete|drop|alter|create|truncate)\b/is',
        '/\b(exec|execute|sp_executesql)\b/is',
        '/--/', // SQL comments
        '/\/\*.*\*\//s', // Multi-line comments
        '/\b(load_file|outfile|dumpfile)\b/is',
        '/\b(benchmark|sleep|waitfor)\b.*\(/is'
    ];
    
    $clean_sql = preg_replace('/\?/', '**PARAM**', $sql);
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $clean_sql)) {
            log_security_event('sql_injection_attempt', [
                'sql' => $sql,
                'params' => $params,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // In production, throw generic error
            if (!DEBUG) {
                throw new RuntimeException('Database query error');
            }
            
            throw new RuntimeException('Potential SQL injection detected in query');
        }
    }
    
    return db_raw($sql, ...$params);
}

function log_security_event($event_type, $data) {
    $log_entry = date('Y-m-d H:i:s') . " - {$event_type} - " . json_encode($data) . PHP_EOL;
    $log_file = storage_path('logs/security.log');
    
    // Rotate log if too large
    if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) { // 10MB
        rename($log_file, $log_file . '.' . date('Y-m-d-H-i-s'));
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}



/**
 * SELECT helper with WHERE, JOIN, ORDER, LIMIT.
 * Now with optional caching.
 */

function db_select(
    string $table,
    $cols = '*',
    array $conds = [],
    string $order = '',
    string $limit = '',
    array $joins = []
): array {
    // Build SQL & params
    $colsList = is_array($cols) ? implode(',', $cols) : $cols;
    $sql = "SELECT {$colsList} FROM `{$table}`";
    $params = [];
    foreach ($joins as [$type, $t, $on]) {
        $sql .= " {$type} JOIN `{$t}` ON {$on}";
    }
    if ($conds) {
        $clauses = [];
        foreach ($conds as $colOp => $val) {
            if (strpos($colOp, ' ') !== false) {
                list($col, $op) = explode(' ', $colOp, 2);
                switch (strtoupper($op)) {
                    case 'IN':
                        $ph = implode(',', array_fill(0, count($val), '?'));
                        $clauses[] = "`{$col}` IN ({$ph})";
                        $params = array_merge($params, $val);
                        continue 2;
                    case 'BETWEEN':
                        $clauses[] = "`{$col}` BETWEEN ? AND ?";
                        $params[] = $val[0];
                        $params[] = $val[1];
                        continue 2;
                    case 'LIKE':
                        $clauses[] = "`{$col}` LIKE ?";
                        $params[] = $val;
                        continue 2;
                }
            }
            $clauses[] = "`{$colOp}` = ?";
            $params[]  = $val;
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }
    if ($order) $sql .= " ORDER BY {$order}";
    if ($limit) $sql .= " LIMIT {$limit}";

        // Caching
    $version  = getVersion($table);
    $cacheKey = "{$table}.v{$version}." . md5($sql . json_encode($params));

    if (CACHING === CACHE_ENGINE) {
        // VERSIONED GET
        $json = cache_get_versioned($cacheKey);
        if ($json !== null) {
            return json_decode($json, true);
        }
    } elseif (CACHING === CACHE_FILE) {
        if ($data = fileCacheLoad($cacheKey)) {
            return json_decode($data, true);
        }
    }


    // Execute and fetch
    $stmt = db_raw($sql, ...$params);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];


    // Save to cache
    $json = json_encode($rows);
    if (CACHING === CACHE_ENGINE) {
        // VERSIONED SET
        cache_set_versioned($cacheKey, $json, CACHE_TTL_DB);
    } elseif (CACHING === CACHE_FILE) {
        fileCacheSave($cacheKey, $json, CACHE_TTL_DB);
    }

    return $rows;
}

/**
 * INSERT single row. Returns inserted ID.
 */
function db_insert(string $table, array $data): int
{
    global $mysqli;
    $cols = array_keys($data);
    $vals = array_values($data);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $sql  = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES ({$ph})";
    db_raw($sql, ...$vals);
    
    $id= mysqli_insert_id($mysqli);
    bumpVersion($table);
    
    return $id;
}

/**
 * Batch insert. Returns number of rows inserted.
 */
function db_batchInsert(string $table, array $rows): int{
    if (!$rows) return 0;
    $cols  = array_keys($rows[0]);
    $phRow = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $phAll = implode(',', array_fill(0, count($rows), $phRow));
    $params = [];
    foreach ($rows as $r) {
        foreach ($cols as $c) {
            $params[] = $r[$c];
        }
    }
    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES {$phAll}";
    $stmt = db_raw($sql, ...$params);

    $aff = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}

/**
 * UPDATE rows. Returns affected rows.
 */
/*function db_update(string $table, array $data, array $conds): int
{
    

    $sets   = [];
    $params = [];
    foreach ($data as $c => $v) {
        $sets[]   = "`{$c}` = ?";
        $params[] = $v;
    }
    $wheres = [];
    foreach ($conds as $c => $v) {
        $wheres[] = "`{$c}` = ?";
        $params[] = $v;
    }
    $sql = "UPDATE `{$table}` SET " . implode(',', $sets)
         . " WHERE " . implode(' AND ', $wheres);
    $stmt    = db_raw($sql, ...$params);

    $aff = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);
    return $aff;
}*/



/**
 * Update rows with optimistic locking and row‐level lock fallback.
 *
 * @param string $table   Table name
 * @param array  $data    Column=>value pairs to set (must include your version column if using optimistic)
 * @param array  $conds   Column=>value pairs for the WHERE clause (must include the primary key)
 * @param bool   $optimistic  Whether to use the version‐check fast path
 * @param string $keyCol       Primary key column name (used for locking)
 * @param string $versionCol  Name of your version or timestamp column
 * @return int             Number of affected rows
 */

 /*
* Add a tiny INT unsigned version NOT NULL DEFAULT 1 column (or use a timestamp) to each table you’ll update this way.
* Now db_update()’s “fast path” will work: it updates only if your code’s version matches the row’s, then bumps it.
* Use this optimistic only if we explicitly need its low-latency, no-lock fast path
*/
function db_update1(
    string $table,
    array  $data,
    array  $conds,
    bool   $optimistic  = false,
    string $keyCol       = 'id',
    string $versionCol  = 'version'
 ): int {
    // 1) Build SET list and params
    $sets   = [];
    $params = [];
    foreach ($data as $col => $val) {
        $sets[]   = "`{$col}` = ?";
        $params[] = $val;
    }
    // If optimistic, append the version bump
    if ($optimistic) {
        $sets[] = "`{$versionCol}` = `{$versionCol}` + 1";
    }

    // 2) Build WHERE clauses for optimistic path
    $whereClauses = [];
    if ($optimistic) {
        // version check
        if (! isset($data[$versionCol])) {
            throw new InvalidArgumentException("Missing {$versionCol} in data for optimistic locking");
        }
    }
    foreach ($conds as $col => $val) {
        $whereClauses[] = "`{$col}` = ?";
        $params[]       = $val;
        if ($optimistic) {
            $whereClauses[] = "`{$versionCol}` = ?";
            $params[]       = $data[$versionCol];
        }
    }
   echo  $whereSql = implode(' AND ', $whereClauses);

    // 3) Try optimistic fast‐path
    if ($optimistic) {
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets)
             . " WHERE {$whereSql}";
        $stmt = db_raw($sql, ...$params);
        $aff  = mysqli_stmt_affected_rows($stmt);
        if ($aff > 0) {
            bumpVersion($table);
            return $aff;
        }
        // else fall through to locking
    }

    // 4) Fallback: row‐level lock
    // assume single‐key cond
    $keyCol   = key($conds);
    $id       = $conds[$keyCol];
    $clientId = uniqid(getmypid() . '-', true);

    if (! lockRow($table, $id, $clientId)) {
        throw new RuntimeException("Could not acquire lock on {$table}:{$id}");
    }

    try {
        // Build SET list without version bump
        $setsNoVer = [];
        $paramsNoVer = [];
        foreach ($data as $col => $val) {
            if ($col === $versionCol) continue;
            $setsNoVer[]   = "`{$col}` = ?";
            $paramsNoVer[] = $val;
        }
        // Rebuild the WHERE clause without version
        $whereNoVerClauses = [];
        foreach ($conds as $col => $val) {
            $whereNoVerClauses[] = "`{$col}` = ?";
            $paramsNoVer[]       = $val;
        }
        $whereNoVerSql = implode(' AND ', $whereNoVerClauses);

        $sqlNoVer = "UPDATE `{$table}` SET "
                  . implode(', ', $setsNoVer)
                  . " WHERE {$whereNoVerSql}";

        $stmt = db_raw($sqlNoVer, ...$paramsNoVer);
        $aff  = mysqli_stmt_affected_rows($stmt);

        bumpVersion($table);
    } finally {
        unlockRow($table, $id, $clientId);
    }

    return $aff;
}

/**
 * Update rows with optimistic locking and row‐level lock fallback.
 *
 * @param string $table        Table name
 * @param array  $data         Column=>value pairs to set
 * @param array  $conds        Column=>value pairs for WHERE
 * @param bool   $optimistic   Use optimistic‐lock fast path?
 * @param string $versionCol   Name of version/timestamp column
 * @return int                 Affected rows
 */
/**
 * Update rows with optimistic locking and row‐level lock fallback.
 *
 * @param string $table        Table name
 * @param array  $data         Column=>value pairs to set
 * @param array  $conds        Column=>value pairs for WHERE
 * @param bool   $optimistic   Use optimistic‐lock fast path?
 * @param string $versionCol   Name of version/timestamp column
 * @return int                 Affected rows
 */
function db_update_manual_update(
    string $table,
    array  $data,
    array  $conds,
    bool   $optimistic  = true,
    string $versionCol  = 'version'
 ): int {
    // 1) Build SET list and params
    $sets   = [];
    $params = [];
    foreach ($data as $col => $val) {
        // **Skip the version column when building the data SETs**
        if ($optimistic && $col === $versionCol) {
            continue;
        }
        $sets[]   = "`{$col}` = ?";
        $params[] = $val;
    }
    // If optimistic, now append the version bump
    if ($optimistic) {
        $sets[] = "`{$versionCol}` = `{$versionCol}` + 1";
    }

    // 2) Build WHERE clauses & params for optimistic
    $whereClauses = [];
    if ($optimistic) {
        if (! isset($data[$versionCol])) {
            throw new InvalidArgumentException("Missing {$versionCol} for optimistic locking");
        }
    }
    foreach ($conds as $col => $val) {
        $whereClauses[] = "`{$col}` = ?";
        $params[]       = $val;
        if ($optimistic) {
            // match the old version
            $whereClauses[] = "`{$versionCol}` = ?";
            $params[]       = $data[$versionCol];
        }
    }
    $whereSql = implode(' AND ', $whereClauses);

    // 3) Try optimistic fast-path
    if ($optimistic) {
        $sql  = "UPDATE `{$table}` SET " . implode(', ', $sets)
              . " WHERE {$whereSql}";
        $stmt = db_raw($sql, ...$params);
        $aff  = mysqli_stmt_affected_rows($stmt);
        if ($aff > 0) {
            bumpVersion($table);
            return $aff;
        }
        // else fall through to locking
    }

    // 4) Fallback: row-level lock
    $keyCol   = key($conds);
    $id       = $conds[$keyCol];
    $clientId = uniqid(getmypid() . '-', true);
    if (! lockRow($table, $id, $clientId)) {
        throw new RuntimeException("Could not acquire lock on {$table}:{$id}");
    }

    try {
        // Build SET list without the version bump or version param
        $setsNoVer   = [];
        $paramsNoVer = [];
        foreach ($data as $col => $val) {
            if ($col === $versionCol) {
                continue;
            }
            $setsNoVer[]   = "`{$col}` = ?";
            $paramsNoVer[] = $val;
        }

        // Build WHERE clause without version
        $wheres = [];
        foreach ($conds as $col => $val) {
            $wheres[]       = "`{$col}` = ?";
            $paramsNoVer[]  = $val;
        }
        $whereNoVerSql = implode(' AND ', $wheres);

        $sqlNoVer = "UPDATE `{$table}` SET "
                  . implode(', ', $setsNoVer)
                  . " WHERE {$whereNoVerSql}";

        $stmt = db_raw($sqlNoVer, ...$paramsNoVer);
        $aff  = mysqli_stmt_affected_rows($stmt);

        bumpVersion($table);
    } finally {
        unlockRow($table, $id, $clientId);
    }

    return $aff;
}


/**
 * Update rows with optimistic locking and row‐level lock fallback.
 *
 * If you request optimistic locking but don’t supply the version in $data, we’ll fetch it automatically.
 * If you expect extremely high write volumes and want to avoid that extra SELECT, you can still revert to 
 * the manual approach (passing the version yourself). But for most use-cases—especially tests and CRUD 
 * screens—this auto-fetch version is both simpler and more robust.
 *
 * @param string $table        Table name
 * @param array  $data         Column=>value pairs to set
 * @param array  $conds        Column=>value pairs for WHERE (must include PK)
 * @param bool   $optimistic   Use optimistic‐lock fast path?
 * @param string $versionCol   Name of version or timestamp column
 * @return int                 Number of affected rows
 */
function db_update(
    string $table,
    array  $data,
    array  $conds,
    bool   $optimistic  = false,
    string $versionCol  = 'version'
): int {
    // If optimistic and no version in $data, fetch it now
    if ($optimistic && ! isset($data[$versionCol])) {
        // build a simple select
        $pk    = key($conds);
        $id    = $conds[$pk];
        $row   = db_select($table, $versionCol, [$pk => $id], '', '1');
        if (! isset($row[0][$versionCol])) {
            throw new RuntimeException("Could not read existing {$versionCol} for {$table}:{$id}");
        }
        $data[$versionCol] = $row[0][$versionCol];
    }

    // 1) Build SET list & params (skip versionCol itself)
    $sets   = [];
    $params = [];
    foreach ($data as $col => $val) {
        if ($optimistic && $col === $versionCol) {
            continue;
        }
        $sets[]   = "`{$col}` = ?";
        $params[] = $val;
    }
    if ($optimistic) {
        // append the bump
        $sets[] = "`{$versionCol}` = `{$versionCol}` + 1";
    }

    // 2) Build WHERE clauses & params (including version guard)
    $whereClauses = [];
    foreach ($conds as $col => $val) {
        $whereClauses[] = "`{$col}` = ?";
        $params[]       = $val;
        if ($optimistic) {
            $whereClauses[] = "`{$versionCol}` = ?";
            $params[]       = $data[$versionCol];
        }
    }
    $whereSql = implode(' AND ', $whereClauses);

    // 3) Try optimistic fast‐path
    if ($optimistic) {
        $sql  = "UPDATE `{$table}` SET " . implode(', ', $sets)
              . " WHERE {$whereSql}";
        $stmt = db_raw($sql, ...$params);
        $aff  = mysqli_stmt_affected_rows($stmt);
        if ($aff > 0) {
            bumpVersion($table);
            return $aff;
        }
        // otherwise fall back to locking
    }

    // 4) Row‐level lock fallback
    $pk       = key($conds);
    $id       = $conds[$pk];
    $clientId = uniqid(getmypid() . '-', true);
    if (! lockRow($table, $id, $clientId)) {
        throw new RuntimeException("Could not acquire lock on {$table}:{$id}");
    }

    try {
        // SET list without version bump
        $setsNoVer   = [];
        $paramsNoVer = [];
        foreach ($data as $col => $val) {
            if ($col === $versionCol) continue;
            $setsNoVer[]   = "`{$col}` = ?";
            $paramsNoVer[] = $val;
        }

        // WHERE without version
        $wheres = [];
        foreach ($conds as $col => $val) {
            $wheres[]      = "`{$col}` = ?";
            $paramsNoVer[] = $val;
        }
        $whereNoVerSql = implode(' AND ', $wheres);

        $sqlNoVer = "UPDATE `{$table}` SET "
                  . implode(', ', $setsNoVer)
                  . " WHERE {$whereNoVerSql}";

        $stmt = db_raw($sqlNoVer, ...$paramsNoVer);
        $aff  = mysqli_stmt_affected_rows($stmt);

        bumpVersion($table);
    } finally {
        unlockRow($table, $id, $clientId);
    }

    return $aff;
}



/**
 * Batch update different values per row. Returns affected rows.
 */
function db_batchUpdate__(string $table, string $keyCol, array $rows): int
{
    if (!$rows) return 0;
    $cases = [];
    $params = [];
    foreach ($rows as $r) {
        $id = $r[$keyCol];
        foreach ($r as $col => $val) {
            if ($col === $keyCol) continue;
            $cases[$col][] = "WHEN ? THEN ?";
            $params[]      = $id;
            $params[]      = $val;
        }
    }
    $sql = "UPDATE `{$table}` SET ";
    foreach ($cases as $col => $whens) {
        $sql .= "`{$col}` = CASE `{$keyCol}` " . implode(' ', $whens) . " ELSE `{$col}` END, ";
    }
    $sql = rtrim($sql, ', ') . " WHERE `{$keyCol}` IN (" . implode(',', array_fill(0, count($rows), '?')) . ")";
    foreach ($rows as $r) {
        $params[] = $r[$keyCol];
    }
    $stmt = db_raw($sql, ...$params);

    $aff = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);

    return $aff;
}

/**
 * Batch update different values per row, with optional optimistic locking
 * via a version or timestamp column. Returns affected rows.
 *
 * @param string $table        Table name
 * @param string $keyCol       Primary key column
 * @param array  $rows         [ [ keyCol=>id, colA=>valA, …, versionCol=>v ], … ]
 * @param bool   $optimistic   If true, each row must include ['versionCol'=>current], or ['updated_at'=>current timestamp].
 *                             If false, the whole batch is wrapped in a table‐level lock.
 * @param string $versionCol   Column to use for optimistic checks (default 'version')
 * @return int                 Total affected rows
 */
function db_batchUpdate(
    string $table,
    string $keyCol,
    array  $rows,
    bool   $optimistic   = false,
    string $versionCol   = 'version'
): int {
    if (empty($rows)) return 0;

    // 1) Build the CASE expressions & params, ignoring versionCol for now
    $cases  = [];
    $params = [];
    foreach ($rows as $r) {
        $id = $r[$keyCol];
        foreach ($r as $col => $val) {
            if ($col === $keyCol || ($optimistic && $col === $versionCol)) {
                continue;
            }
            $cases[$col][] = "WHEN ? THEN ?";
            $params[]      = $id;
            $params[]      = $val;
        }
    }

    // 2) Assemble the SQL
    $sql  = "UPDATE `{$table}` SET ";
    foreach ($cases as $col => $whens) {
        $sql .= "`{$col}` = CASE `{$keyCol}` "
              . implode(' ', $whens)
              . " ELSE `{$col}` END, ";
    }
    // we will append the WHERE clause below

    // 3) Optimistic path: add per-row version/timestamp checks
    $whereClauses = [];
    foreach ($rows as $r) {
        $whereClauses[] = "`{$keyCol}` = ?";
        $params[]       = $r[$keyCol];
        if ($optimistic) {
            if (! isset($r[$versionCol])) {
                throw new InvalidArgumentException("Missing {$versionCol} in row for optimistic locking");
            }
            $whereClauses[] = "`{$versionCol}` = ?";
            $params[]       = $r[$versionCol];
        }
    }
    $sql .= " WHERE (" . implode(" OR ", array_fill(0, count($rows), "( " . implode(' AND ', array_slice($whereClauses, 0, $optimistic ? 2 : 1)) . " )")) . ")";

    // 4) Try optimistic update if enabled
    if ($optimistic) {
        $stmt = db_raw($sql, ...$params);
        $affected = mysqli_stmt_affected_rows($stmt);
        if ($affected > 0) {
            // success: atomic cache invalidation once
            bumpVersion($table);
            return $affected;
        }
        // else fall through to locked path
    }

    // 5) Fallback: table-level lock for the whole batch
    $lockId   = uniqid(getmypid() . '-', true);
    if (! lockRow($table, 'all', $lockId)) {
        throw new RuntimeException("Could not acquire table lock on {$table}");
    }
    try {
        // rebuild SQL without version checks
        $params = [];
        $sqlNoVer  = "UPDATE `{$table}` SET ";
        foreach ($cases as $col => $whens) {
            $sqlNoVer .= "`{$col}` = CASE `{$keyCol}` "
                       . implode(' ', $whens)
                       . " ELSE `{$col}` END, ";
        }
        $sqlNoVer = rtrim($sqlNoVer, ', ')
                  . " WHERE `{$keyCol}` IN (" . implode(',', array_fill(0, count($rows), '?')) . ')';
        foreach ($rows as $r) {
            $params[] = $r[$keyCol];
        }

        $stmt = db_raw($sqlNoVer, ...$params);
        $affected = mysqli_stmt_affected_rows($stmt);

        bumpVersion($table);
    } finally {
        unlockRow($table, 'all', $lockId);
    }

    return $affected;
}


/**
 * UPSERT (INSERT … ON DUPLICATE KEY UPDATE). Returns affected rows.
 */
/*function db_upsert(string $table, array $data, array $updateCols): int
{
    $cols = array_keys($data);
    $vals = array_values($data);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $colL = implode('`,`', $cols);
    $upd  = [];
    foreach ($updateCols as $c) {
        $upd[] = "`{$c}` = VALUES(`{$c}`)";
    }
    $sql = "INSERT INTO `{$table}` (`{$colL}`) VALUES ({$ph})"
         . " ON DUPLICATE KEY UPDATE " . implode(', ', $upd);
    $stmt = db_raw($sql, ...$vals);

    $aff = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);

    return $aff;
}*/


/**
 * UPSERT (INSERT … ON DUPLICATE KEY UPDATE) with optimistic-lock fast path
 *   and row-lock fallback.
 *
 * @param string $table        Table name
 * @param array  $data         Column=>value for INSERT (must include keyCol and optionally versionCol)
 * @param array  $updateCols   Columns to update on duplicate key
 * @param bool   $optimistic   If true, requires $data[$versionCol] and will try a version-guarded UPDATE first
 * @param string $keyCol       Primary key column name (used for locking)
 * @param string $versionCol   Version or timestamp column for optimistic locking
 * @return int                 Number of affected rows
 */
function db_upsert(
    string $table,
    array  $data,
    array  $updateCols,
    bool   $optimistic   = false,
    string $keyCol       = 'id',
    string $versionCol   = 'version'
): int {
    // 1) Fast path: optimistic UPDATE ... WHERE version = oldVersion
    if ($optimistic && isset($data[$versionCol])) {
        $oldVer = $data[$versionCol];

        // Build SET list for UPDATE
        $sets   = [];
        $params = [];
        foreach ($updateCols as $col) {
            if ($col === $versionCol) continue;
            $sets[]   = "`{$col}` = ?";
            $params[] = $data[$col] ?? null;
        }
        // bump version in-row
        $sets[] = "`{$versionCol}` = `{$versionCol}` + 1";

        // WHERE clause: key and version match
        $where  = "`{$keyCol}` = ? AND `{$versionCol}` = ?";
        $params[] = $data[$keyCol];
        $params[] = $oldVer;

        $sql    = "UPDATE `{$table}` SET " . implode(', ', $sets)
                . " WHERE {$where}";

        $stmt   = db_raw($sql, ...$params);
        $aff    = mysqli_stmt_affected_rows($stmt);
        if ($aff > 0) {
            // success: atomic cache bump
            bumpVersion($table);
            return $aff;
        }
        // else someone else raced us → fallback
    }

    // 2) Fallback: row-level lock around INSERT … ON DUPLICATE KEY
    $id       = $data[$keyCol];
    $clientId = uniqid(getmypid().'-', true);
    if (! lockRow($table, $id, $clientId)) {
        throw new RuntimeException("Could not acquire lock on {$table}:{$id}");
    }

    try {
        // Build the INSERT ... ON DUPLICATE KEY UPDATE SQL
        $cols      = array_keys($data);
        $placeH    = implode(',', array_fill(0, count($cols), '?'));
        $colList   = implode('`,`', $cols);

        // Values for INSERT
        $paramsIns = array_values($data);

        // Build the ON DUPLICATE part
        $dupSets   = [];
        $paramsUpd = [];
        foreach ($updateCols as $col) {
            if ($col === $versionCol) {
                // bump version
                $dupSets[]   = "`{$versionCol}` = `{$versionCol}` + 1";
            } else {
                $dupSets[]   = "`{$col}` = VALUES(`{$col}`)";
            }
        }
        // Optionally, if you prefer timestamp:
        // $dupSets[] = "`{$versionCol}` = NOW()";

        $sql = "INSERT INTO `{$table}` (`{$colList}`) VALUES ({$placeH})"
             . " ON DUPLICATE KEY UPDATE " . implode(', ', $dupSets);

        $stmt    = db_raw($sql, ...$paramsIns);
        $affected = mysqli_stmt_affected_rows($stmt);

        // 3) Invalidate cache
        bumpVersion($table);
    } finally {
        unlockRow($table, $id, $clientId);
    }

    return $affected;
}


/**
 * DELETE rows. Returns affected rows.
 */
function db_delete(string $table, array $conds): int
{
    $wheres = [];
    $params = [];
    foreach ($conds as $c => $v) {
        $wheres[] = "`{$c}` = ?";
        $params[] = $v;
    }
    $sql      = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $wheres);
    $stmt     = db_raw($sql, ...$params);
    
    $affected = mysqli_stmt_affected_rows($stmt);
    bumpVersion($table);

    return $affected;
}

/**
 * DELETE with raw WHERE (e.g. "expires < ?" ).
 */
function db_deleteWhere(string $table, string $whereExpr, array $params = []): int
{
    $sql      = "DELETE FROM `{$table}` WHERE {$whereExpr}";
    $stmt     = db_raw($sql, ...$params);
    $affected = mysqli_stmt_affected_rows($stmt);

    bumpVersion($table);
    return $affected;
}

/**
 * EXISTS check.
 */
function db_exists(string $table, array $conds): bool
{
    $res = db_select($table, '1', $conds, '', '1');
    return !empty($res);
}

/**
 * Aggregate: COUNT, SUM, AVG, MIN, MAX.
 */
function db_aggregate(
    string $table,
    string $func,
    string $col,
    array $conds = [],
    array $joins = []
) {
    // Build SQL & params
    $sql    = "SELECT {$func}({$col}) AS value FROM `{$table}`";
    $params = [];
    foreach ($joins as [$type, $t, $on]) {
        $sql .= " {$type} JOIN `{$t}` ON {$on}";
    }
    if ($conds) {
        $clauses = [];
        foreach ($conds as $c => $v) {
            $clauses[] = "`{$c}` = ?";
            $params[]  = $v;
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    // Caching
    $version = getVersion($table);
    $cacheKey = "agg.{$table}.{$func}.{$col}.v{$version}." . md5($sql . json_encode($params));
    if (CACHING === CACHE_ENGINE) {
        $cached = cache_get_versioned($cacheKey);
        if ($cached !== null) {
            return is_numeric($cached) ? $cached + 0 : $cached;
        }
    } elseif (CACHING === CACHE_FILE) {
        if ($data = fileCacheLoad($cacheKey)) {
            return is_numeric($data) ? $data + 0 : $data;
        }
    }

    // Execute
    $stmt = db_raw($sql, ...$params);
    $row  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $value = $row['value'] ?? null;

    // Save cache
    if (CACHING === CACHE_ENGINE) {
        cache_set_versioned($cacheKey, (string)$value, CACHE_TTL_DB);
    } elseif (CACHING === CACHE_FILE) {
        fileCacheSave($cacheKey, (string)$value, CACHE_TTL_DB);
    }

    return $value;
}


/**
 * Increment/decrement a single column with row‐level locking. Returns affected rows.
 */
function db_increment(string $table, string $col, int $by, array $conds): int
{
    // 1) Determine lock target (assumes single‐column cond, e.g. ['id'=>123])
    $keyCol   = key($conds);
    $id        = $conds[$keyCol];
    $clientId  = uniqid(getmypid() . '-', true);

    // 2) Acquire row‐level lock
    if (! lockRow($table, $id, $clientId)) {
        throw new RuntimeException("Could not acquire lock on {$table}:{$id}");
    }

    try {
        // 3) Build and execute the increment SQL
        $wheres = [];
        $params = [$by];
        foreach ($conds as $c => $v) {
            $wheres[] = "`{$c}` = ?";
            $params[] = $v;
        }
        $sql  = "UPDATE `{$table}` "
              . "SET `{$col}` = `{$col}` + ? "
              . "WHERE " . implode(' AND ', $wheres);
        $stmt      = db_raw($sql, ...$params);
        $affected  = mysqli_stmt_affected_rows($stmt);

        // 4) Invalidate cache: bump row‐specific and table versions
        bumpVersion("{$table}:row:{$id}");
        bumpVersion($table);
    } finally {
        // 5) Always release the lock
        unlockRow($table, $id, $clientId);
    }

    return $affected;
}


/**
 * Paginate results.
 */
function db_paginate(
    string $table,
    $cols = '*',
    array $conds = [],
    int $page = 1,
    int $perPage = 20,
    string $order = '',
    array $joins = []
): array {
    $offset = max(0, $page - 1) * $perPage;
    $sqlBase = "SELECT %s FROM `{$table}`";
    // Build data SQL & params
    $colsList = is_array($cols) ? implode(',', $cols) : $cols;
    $sqlData = sprintf($sqlBase, $colsList);
    $params  = [];
    foreach ($joins as [$type, $t, $on]) {
        $sqlData .= " {$type} JOIN `{$t}` ON {$on}";
    }
    if ($conds) {
        $clauses = [];
        foreach ($conds as $c => $v) {
            $clauses[] = "`{$c}` = ?";
            $params[]  = $v;
        }
        $sqlData .= ' WHERE ' . implode(' AND ', $clauses);
    }
    if ($order) $sqlData .= " ORDER BY {$order}";
    $sqlData .= " LIMIT {$perPage} OFFSET {$offset}";

    // Caching key
    $version = getVersion($table);
    $cacheKey = "page.{$table}.v{$version}.p{$page}.s{$perPage}." . md5($sqlData . json_encode($params));

    if (CACHING === CACHE_ENGINE) {
        $cached = cache_get_versioned($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }
    } elseif (CACHING === CACHE_FILE) {
        if ($data = fileCacheLoad($cacheKey)) {
            return json_decode($data, true);
        }
    }

    // Execute data and count
    $data = db_select($table, $cols, $conds, $order, "{$perPage} OFFSET {$offset}", $joins);
    $total= (int) db_aggregate($table, 'COUNT', '*', $conds);
    $result = [
        'data' => $data,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / $perPage),
        ],
    ];

    // Save cache
    if (CACHING === CACHE_ENGINE) {
        cache_set_versioned($cacheKey, json_encode($result), CACHE_TTL_DB);
    } elseif (CACHING === CACHE_FILE) {
        fileCacheSave($cacheKey, json_encode($result), CACHE_TTL_DB);
    }

    return $result;
}


/**
 * Transaction wrapper (no caching)
 */
function db_transaction(callable $fn)
{
    global $mysqli;
    mysqli_begin_transaction($mysqli);
    try {
        $res=$fn($mysqli);
        mysqli_commit($mysqli);
        return $res;
    } catch (Throwable $e) {
        mysqli_rollback($mysqli);
        return false;
    }
}




// --  DB with Caching techniques

/**
 * Execute arbitrary SQL, then auto-invalidate any tables it modified,
 * using only core PHP string functions.
 *
 * Supports:
 *   - Semicolons inside single/double quotes, backticks, and brackets
 *   - CTE blocks (WITH ...)
 *   - DML verbs: INSERT INTO, UPDATE, DELETE FROM,
 *                MERGE INTO, TRUNCATE TABLE, REPLACE INTO, UPSERT INTO
 *   - Schema-qualified names and quoted identifiers (``, "", []),
 *     stripping down to the bare table name.
 *
 * @param string $sql  One or more SQL statements.
 * @return mixed       Result of db_exec().
 */

function nc_raw_sql(string $sql) {
    $result = db_exec($sql);
    $tables = extractTablesFromSql($sql);
    foreach ($tables as $tbl) {
        bumpVersion($tbl);
    }
    return $result;
}

// Alias nc_raw_sql to use cache_bumpver under the hood for raw SQL
function nc_raw_sql2(string $sql)
{
    // existing db_exec call …
    $result = db_exec($sql);
    $tables = extractTablesFromSql($sql);
    foreach ($tables as $t) {
        cache_bumpver($t);
    }
    return $result;
}

//function nc_raw_sql(string $sql) {
function db_raw_sql__(string $sql) {
    // 1. Execute the SQL
    $result = db_exec($sql);

    // 2. Split statements on semicolons not inside quotes/bracketsfde
    $stmts    = [];
    $buffer   = '';
    $inS      = false; // inside single-quote
    $inD      = false; // inside double-quote
    $inBt     = false; // inside backtick
    $inBkt    = false; // inside bracket [...]
    $length   = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $c = $sql[$i];
        // toggle states
        if (!$inD && !$inBkt && !$inBt && $c === "'")      { $inS  = !$inS; }
        elseif (!$inS && !$inBkt && !$inBt && $c === '"')  { $inD  = !$inD; }
        elseif (!$inS && !$inD && !$inBt && $c === '[')    { $inBkt = true; }
        elseif ($inBkt && $c === ']')                     { $inBkt = false; }
        elseif (!$inS && !$inD && !$inBkt && $c === '`')  { $inBt = !$inBt; }

        // split only on unquoted semicolons
        if ($c === ';' && !$inS && !$inD && !$inBkt && !$inBt) {
            $stmts[] = $buffer;
            $buffer  = '';
        } else {
            $buffer .= $c;
        }
    }
    if (trim($buffer) !== '') {
        $stmts[] = $buffer;
    }

    // 3. Look for DMLs and collect affected tables
    $seen  = [];
    $dmls  = [
        'insert into ', 'update ', 'delete from ',
        'merge into ',  'truncate table ', 'replace into ', 'upsert into '
    ];

    foreach ($stmts as $stmt) {
        $s     = trim($stmt);
        if ($s === '') continue;
        $lower = strtolower($s);

        // find DML position, handling leading CTE
        $foundDml = null;
        $offset   = 0;
        if (strpos($lower, 'with ') === 0) {
            // after CTE, find earliest DML keyword
            $bestPos = PHP_INT_MAX;
            foreach ($dmls as $kw) {
                $pos = stripos($s, $kw);
                if ($pos !== false && $pos < $bestPos) {
                    $bestPos   = $pos;
                    $foundDml  = $kw;
                }
            }
            if ($foundDml === null) {
                continue;
            }
            $offset = $bestPos + strlen($foundDml);
        } else {
            // requires DML at start
            foreach ($dmls as $kw) {
                if (strpos($lower, $kw) === 0) {
                    $foundDml = $kw;
                    $offset   = strlen($kw);
                    break;
                }
            }
            if ($foundDml === null) {
                continue;
            }
        }

        // 4. Grab the rest after the DML keyword
        $rest = substr($s, $offset);
        $rest = ltrim($rest);

        // 5. Extract identifier, handling quotes and schema
        $table = '';
        $c0    = $rest[0] ?? '';

        if ($c0 === '`' || $c0 === '"' || $c0 === '[') {
            // quoted identifier
            $close = ($c0 === '[' ? ']' : $c0);
            $end   = strpos($rest, $close, 1);
            if ($end !== false) {
                $table = substr($rest, 1, $end - 1);
                $rest  = substr($rest, $end + 1);
            }
        } else {
            // unquoted: read until delim
            $lenId = strcspn($rest, " \t\n\r\0\x0B`\"[,(.");
            $table = substr($rest, 0, $lenId);
            $rest  = substr($rest, $lenId);
        }

        // 6. If schema-qualified (a.b), take the last part
        if (strpos($table, '.') !== false) {
            $parts = explode('.', $table);
            $table = end($parts);
        }

        $table = strtolower($table);
        if ($table !== '') {
            $seen[$table] = true;
        }
    }

    // 7. Bump cache versions
    foreach (array_keys($seen) as $tbl) {
        cache_command("BUMPVER {$tbl}");
    }

    return $result;
}




function extractTablesFromSql(string $sql): array {
    $stmts = [];
    $buf   = '';
    $inS = $inD = $inBt = $inBkt = false;
    $L = strlen($sql);
    for ($i = 0; $i < $L; $i++) {
        $c = $sql[$i];
        if (!$inD && !$inBkt && !$inBt && $c === "'")    $inS  = !$inS;
        elseif (!$inS && !$inBkt && !$inBt && $c === '"') $inD  = !$inD;
        elseif (!$inS && !$inD && !$inBt && $c === '[')    $inBkt = true;
        elseif ($inBkt && $c === ']')                      $inBkt = false;
        elseif (!$inS && !$inD && !$inBkt && $c === '`')   $inBt = !$inBt;
        if ($c === ';' && !$inS && !$inD && !$inBkt && !$inBt) {
            $stmts[] = $buf;
            $buf = '';
        } else {
            $buf .= $c;
        }
    }
    if (trim($buf) !== '') {
        $stmts[] = $buf;
    }

    $seen = [];
    $dmls = [
        'insert into ', 'update ', 'delete from ',
        'merge into ',  'truncate table ', 'replace into ', 'upsert into '
    ];
    foreach ($stmts as $stmt) {
        $t   = trim($stmt);
        if (!$t) continue;
        $low = strtolower($t);
        $found = null;
        $off   = 0;
        if (strpos($low, 'with ') === 0) {
            $best = PHP_INT_MAX;
            foreach ($dmls as $kw) {
                $p = stripos($t, $kw);
                if ($p !== false && $p < $best) {
                    $best  = $p;
                    $found = $kw;
                }
            }
            if (!$found) continue;
            $off = $best + strlen($found);
        } else {
            foreach ($dmls as $kw) {
                if (strpos($low, $kw) === 0) {
                    $found = $kw;
                    $off   = strlen($kw);
                    break;
                }
            }
            if (!$found) continue;
        }
        $rest = ltrim(substr($t, $off));
        // extract identifier
        $c0 = $rest[0] ?? '';
        $tbl = '';
        if ($c0 === '`' || $c0 === '"' || $c0 === '[') {
            $close = ($c0 === '[' ? ']' : $c0);
            $end   = strpos($rest, $close, 1);
            if ($end !== false) {
                $tbl = substr($rest, 1, $end-1);
            }
        } else {
            $lenId = strcspn($rest, " \t\n\r\0\x0B`\"[,(.");
            $tbl   = substr($rest, 0, $lenId);
        }
        if (strpos($tbl, '.') !== false) {
            $parts = explode('.', $tbl);
            $tbl   = end($parts);
        }
        $tbl = strtolower($tbl);
        if ($tbl) $seen[$tbl] = true;
    }
    return array_keys($seen);
}


/**
 * Execute a raw SQL string (possibly containing multiple statements)
 * and return either the mysqli_result (for SELECT) or boolean true/false.
 *
 * @param string $sql
 * @return mysqli_result|bool
 * @throws RuntimeException on error
 */
/*function db_exec(string $sql)
{
    global $mysqli;

    // If you expect multiple statements, use multi_query:
    if ($mysqli->multi_query($sql)) {
        // Store the first result (if any)
        $result = $mysqli->store_result();
        // Flush any additional results to keep the connection clean
        while ($mysqli->more_results() && $mysqli->next_result()) {
            $extra = $mysqli->store_result();
            if ($extra instanceof mysqli_result) {
                $extra->free();
            }
        }
        return $result !== false ? $result : true;
    } else {
        throw new RuntimeException("SQL error: " . $mysqli->error);
    }
}
*/

/**
 * Load an item from the file cache.
 *
 * Returns:
 * - cached data if found and valid
 * - null if missing, expired, or corrupt
 */
function fileCacheLoad(string $key): ?string
{
    $fn = BASE_PATH . '/cache/' . $key . '.cache';

    if (!is_file($fn)) {
        return null;
    }

    $blob = @file_get_contents($fn);

    if ($blob === false || $blob === '') {
        return null;
    }

    $parts = explode("\n", $blob, 3);

    if (count($parts) < 3) {
        @unlink($fn);
        return null;
    }

    [$ts, $ttl, $data] = $parts;

    $ts  = (int) $ts;
    $ttl = (int) $ttl;

    // TTL 0 means never expire
    if ($ttl === 0) {
        return $data;
    }

    // Cache still valid
    if ((time() - $ts) < $ttl) {
        return $data;
    }

    // Expired
    @unlink($fn);

    return null;
}

/**
 * Save an item to the file cache.
 *
 * TTL:
 * - 0 = never expire
 * - >0 = expire after N seconds
 */
function fileCacheSave(string $key, string $data, int $ttl = 0): void
{
    $dir = BASE_PATH . '/cache';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fn = $dir . '/' . $key . '.cache';

    $blob = time() . "\n"
          . $ttl . "\n"
          . $data;

    file_put_contents($fn, $blob, LOCK_EX);
}

function fileCacheInvalidateByTable(string $table): void
{
    foreach (glob(__DIR__ . "/../cache/{$table}.*.cache") as $fn) {
        @unlink($fn);
    }
}

/**
 * Table-version helpers
 */
function getVersion(string $table): int
{
    switch (CACHING) {
        case CACHE_ENGINE:
            return (int) cache_command("GETVER {$table}");
        case CACHE_FILE:
            $f = __DIR__ . "/../cache/{$table}.ver";
            return is_file($f) ? (int)file_get_contents($f) : 1;
        default:
            return 1;
    }
}

/**
 * Increment the version for a table, invalidating cache entries.
 */
function bumpVersion_immediate(string $table): void
{
    switch (CACHING) {
        case CACHE_ENGINE:
            // Atomically bump the version inside a cache transaction
            cache_tx_begin();
            cache_command("BUMPVER {$table}");
            cache_tx_commit();
            break;

        case CACHE_FILE:
            // File-based version bump and invalidation
            $f = __DIR__ . "/../cache/{$table}.ver";
            $v = (int)(is_file($f) ? file_get_contents($f) : 1) + 1;
            file_put_contents($f, (string)$v);
            fileCacheInvalidateByTable($table);
            break;

        case CACHE_NONE:
        default:
            // no caching
            break;
    }
}


function bumpVersion_apply_queue($tables)
{
    foreach (array_keys($tables) as $t) {
        bumpVersion_immediate($t);
    }
}

function bumpVersion($table)
{
    $table = trim((string)$table);
    if ($table === '') return;

    if (db_in_tx()) {
        $GLOBALS['__noclass_db_bump_queue'][strtolower($table)] = true;
        return;
    }

    bumpVersion_immediate($table);
}



/*

// 1. Raw SQL
$stmt = db_raw("SELECT * FROM users WHERE email = ?", 'demo@demo.com');
$user = $stmt->get_result()->fetch_assoc();

// 2. Simple SELECT
$users = db_select('users', ['id','username'], ['status'=>'active'], 'id DESC', '10');

// 3. JOIN + WHERE + ORDER
$posts = db_select(
    'posts p',
    ['p.status' => 'published'],
    ['p.id','p.title','u.username'],
    'p.created_at DESC',
    '5',
    [['LEFT','users u','p.author_id=u.id']]
);

// 4. EXISTS
if (db_exists('users', ['email'=>'demo@demo.com'])) {
    echo "Email already registered.";
}

// 5. INSERT
$newUserId = db_insert('users', [
    'username'=>'demo',
    'email'=>'demo@demo.com',
    'password'=>password_hash('secret',PASSWORD_BCRYPT)
]);

// 6. UPSERT
db_upsert('settings',
    ['name'=>'theme','value'=>'dark-mode'],
    ['value']
);

// 7. UPDATE
db_update('users', ['username'=>'newname'], ['id'=>$newUserId]);

// 8. INCREMENT
db_increment('counters', 'count', 1, ['id'=>42]);

// 9. BATCH INSERT
db_batchInsert('logs', [
   ['user_id'=>1,'action'=>'login','ts'=>time()],
   ['user_id'=>2,'action'=>'logout','ts'=>time()],
]);

// 10. BATCH UPDATE
db_batchUpdate('products', 'id', [
   ['id'=>101,'price'=>19.99],
   ['id'=>102,'price'=>24.50],
]);

// 11. AGGREGATE
$total = db_aggregate('orders','SUM','total', ['status'=>'complete']);

// 12. PAGINATE
$page2 = db_paginate('posts', ['published'=>1], 2, 10);

// 13. DELETE
db_delete('sessions', ['expires <' => time()]);

// 14. TRANSACTION
$result = db_transaction(function() {
    db_update('accounts', ['balance'=>100], ['id'=>1]);
    db_update('accounts', ['balance'=>200], ['id'=>2]);
    return true;
});


*
// --------------------------------------------------
// Transactions (write connection)
// --------------------------------------------------

function db_tx_begin()
{
    $mysqli = db_write();
    $depth = isset($GLOBALS['__noclass_db_tx_depth']) ? (int)$GLOBALS['__noclass_db_tx_depth'] : 0;

    if ($depth === 0) {
        if (!mysqli_begin_transaction($mysqli)) {
            throw new RuntimeException('DB begin transaction error: ' . mysqli_error($mysqli));
        }
        $GLOBALS['__noclass_db_bump_queue'] = array();
    } else {
        $sp = 'SP_' . $depth;
        @mysqli_query($mysqli, "SAVEPOINT {$sp}");
    }

    $GLOBALS['__noclass_db_tx_depth'] = $depth + 1;
}

function db_tx_commit()
{
    $mysqli = db_write();
    $depth = isset($GLOBALS['__noclass_db_tx_depth']) ? (int)$GLOBALS['__noclass_db_tx_depth'] : 0;
    if ($depth <= 0) return;

    $depth -= 1;

    if ($depth === 0) {
        if (!mysqli_commit($mysqli)) {
            throw new RuntimeException('DB commit error: ' . mysqli_error($mysqli));
        }

        $tables = isset($GLOBALS['__noclass_db_bump_queue']) ? $GLOBALS['__noclass_db_bump_queue'] : array();
        if (!empty($tables)) {
            bumpVersion_apply_queue($tables);
        }
        $GLOBALS['__noclass_db_bump_queue'] = array();
    }

    $GLOBALS['__noclass_db_tx_depth'] = $depth;
}

function db_tx_rollback()
{
    $mysqli = db_write();
    $depth = isset($GLOBALS['__noclass_db_tx_depth']) ? (int)$GLOBALS['__noclass_db_tx_depth'] : 0;
    if ($depth <= 0) return;

    $depth -= 1;

    if ($depth === 0) {
        if (!mysqli_rollback($mysqli)) {
            throw new RuntimeException('DB rollback error: ' . mysqli_error($mysqli));
        }
        $GLOBALS['__noclass_db_bump_queue'] = array();
    } else {
        $sp = 'SP_' . $depth;
        @mysqli_query($mysqli, "ROLLBACK TO SAVEPOINT {$sp}");
    }

    $GLOBALS['__noclass_db_tx_depth'] = $depth;
}

function db_tx($fn)
{
    db_tx_begin();
    try {
        $res = call_user_func($fn);
        db_tx_commit();
        return $res;
    } catch (Exception $e) {
        db_tx_rollback();
        throw $e;
    } catch (Error $e) {
        db_tx_rollback();
        throw $e;
    }
}

*/