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
 * Initialize a new query state.
 *
 * @param  string $table
 * @return array           The query state
 */
function query(string $table): array {
    return [
        'table' => $table,
        'cols'  => '*',
        'conds' => [],
        'joins' => [],
        'order' => '',
        'limit' => '',
    ];
}

/** Set the columns to select. */
function query_select(array $q, $cols): array {
    $q['cols'] = is_array($cols) ? implode(',', $cols) : $cols;
    return $q;
}

/** Add a WHERE clause. */
function query_where(array $q, string $col, string $op, $val): array {
    // e.g. ['age >' => 18]
    $q['conds']["{$col} {$op}"] = $val;
    return $q;
}

/** Add a JOIN. */
function query_join(array $q, string $type, string $table, string $on): array {
    $q['joins'][] = [$type, $table, $on];
    return $q;
}

/** Set ORDER BY. */
function query_order(array $q, string $order): array {
    $q['order'] = $order;
    return $q;
}

/** Set LIMIT. */
function query_limit(array $q, string $limit): array {
    $q['limit'] = $limit;
    return $q;
}

/**
 * Execute the query state using db_select().
 *
 * @return array  Result rows
 */
function query_get(array $q): array {
    return db_select(
        $q['table'],
        $q['cols'],
        $q['conds'],
        $q['order'],
        $q['limit'],
        $q['joins']
    );
}


// Extend your query state:
function query_groupBy(array $q, string $expr): array {
    $q['group_by'] = $expr;
    return $q;
}

function query_having(array $q, string $col, string $op, $val): array {
    $q['having']["{$col} {$op}"] = $val;
    return $q;
}

// In `query_get()`, pass these into `db_select()` (you’d need to update its signature)


function query_distinct(array $q, bool $on = true): array {
    $q['distinct'] = $on;
    return $q;
}

// In query_get(), prepend "SELECT DISTINCT" when `$q['distinct']` is true.


function query_whereIn(array $q, string $col, array $vals): array {
    $q['conds']["{$col} IN"] = $vals;
    return $q;
}

// In db_select(), recognize the “IN” operator as shown before.


function query_whereBetween(array $q, string $col, $min, $max): array {
    $q['conds']["{$col} BETWEEN"] = [$min, $max];
    return $q;
}


function query_whereRaw(array $q, string $expr, array $params = []): array {
    // Store both an expression and its parameters
    $q['where_raw'][] = ['expr' => $expr, 'params' => $params];
    return $q;
}

// In db_select(), after normal clauses, iterate `where_raw` and append "AND {$expr}", merging `$params`.


function query_leftJoin(array $q, string $table, string $on): array {
    return query_join($q, 'LEFT', $table, $on);
}

function query_rightJoin(array $q, string $table, string $on): array {
    return query_join($q, 'RIGHT', $table, $on);
}


/**
 * Delete rows based on the query state.
 *
 * @param array $q   Query state (must include 'table' and optionally 'conds', 'whereRaw')
 * @return int       Number of affected rows
 */
function query_delete(array $q): int
{
    // Base DELETE statement
    $sql = "DELETE FROM `{$q['table']}`";
    $params = [];

    // Build WHERE clauses from 'conds'
    if (!empty($q['conds'])) {
        $clauses = [];
        foreach ($q['conds'] as $colOp => $val) {
            if (strpos($colOp, ' ') !== false) {
                list($col, $op) = explode(' ', $colOp, 2);
                if (strtoupper($op) === 'IN' && is_array($val)) {
                    $placeholders = implode(',', array_fill(0, count($val), '?'));
                    $clauses[] = "`{$col}` IN ({$placeholders})";
                    $params = array_merge($params, $val);
                    continue;
                } elseif (strtoupper($op) === 'BETWEEN' && is_array($val) && count($val) === 2) {
                    $clauses[] = "`{$col}` BETWEEN ? AND ?";
                    $params[] = $val[0];
                    $params[] = $val[1];
                    continue;
                }
            }
            $clauses[] = "`{$colOp}` = ?";
            $params[]  = $val;
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    // Add any raw WHERE expressions
    if (!empty($q['whereRaw'])) {
        foreach ($q['whereRaw'] as $wr) {
            $sql .= empty($q['conds']) ? ' WHERE ' : ' AND ';
            $sql .= "({$wr[0]})";
            $params = array_merge($params, $wr[1]);
        }
    }

    // Execute and return affected rows
    $stmt = db_raw($sql, ...$params);
    return $stmt->affected_rows;
}

/**
 * Update rows based on the query state.
 *
 * @param array $q     Query state (must include 'table' and 'conds')
 * @param array $data  [column => newValue]
 * @return int         Number of affected rows
 */
function query_update(array $q, array $data): int
{
    // Build SET clause
    $sets = [];
    $params = [];
    foreach ($data as $col => $val) {
        $sets[]    = "`{$col}` = ?";
        $params[]  = $val;
    }
    $sql = "UPDATE `{$q['table']}` SET " . implode(', ', $sets);

    // Build WHERE clauses from 'conds'
    if (!empty($q['conds'])) {
        $clauses = [];
        foreach ($q['conds'] as $colOp => $val) {
            if (strpos($colOp, ' ') !== false) {
                list($col, $op) = explode(' ', $colOp, 2);
                if (strtoupper($op) === 'IN' && is_array($val)) {
                    $placeholders = implode(',', array_fill(0, count($val), '?'));
                    $clauses[] = "`{$col}` IN ({$placeholders})";
                    $params = array_merge($params, $val);
                    continue;
                } elseif (strtoupper($op) === 'BETWEEN' && is_array($val) && count($val) === 2) {
                    $clauses[] = "`{$col}` BETWEEN ? AND ?";
                    $params[] = $val[0];
                    $params[] = $val[1];
                    continue;
                }
            }
            $clauses[] = "`{$colOp}` = ?";
            $params[]  = $val;
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    // Add any raw WHERE expressions
    if (!empty($q['whereRaw'])) {
        foreach ($q['whereRaw'] as $wr) {
            $sql .= empty($q['conds']) ? ' WHERE ' : ' AND ';
            $sql .= "({$wr[0]})";
            $params = array_merge($params, $wr[1]);
        }
    }

    // Execute and return affected rows
    $stmt = db_raw($sql, ...$params);
    return $stmt->affected_rows;
}





/*


$q = query('users');
$q = query_where($q, 'status', '=', 'inactive');
$deletedCount = query_delete($q);

$q2 = query('posts');
$q2 = query_where($q2, 'author_id', '=', 42);




$q = query('orders');
$q = query_select($q, ['user_id','SUM(total) AS total_spent']);
$q = query_where($q, 'status','=', 'complete');
$q = query_groupBy($q, 'user_id');
$q = query_having($q, 'SUM(total)','>', 100);
$result = query_get($q);




// In your controller or wherever:

// 1. Start a query on ‘users’
$q = query('users');

// 2. Chain settings
$q = query_select($q, ['id','username','email']);
$q = query_where ($q, 'status', '=', 'active');
$q = query_where ($q, 'age',    '>=', 18);
$q = query_order ($q, 'created_at DESC');
$q = query_limit ($q, '10');

// 3. Execute
$activeAdults = query_get($q);

// Alternatively, in a single statement:
$activeAdults = query_get(
    query_limit(
    query_order(
    query_where(
    query_where(
    query_select(
      query('users'),
      ['id','username']
    ),
    'status','=', 'active'),
    'age',    '>=', 18),
    'created_at DESC'),
    '10')
);

print_r($activeAdults);

*/