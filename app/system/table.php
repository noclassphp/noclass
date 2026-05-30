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
 * Open a table tag with optional attributes.
 *
 * @param array $attrs  e.g. ['id'=>'userTable','class'=>'table']
 * @return string
 */
function table_open(array $attrs = []): string {
    $s = '<table';
    foreach ($attrs as $k => $v) {
        $s .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . '"';
    }
    return $s . ">\n";
}

/**
 * Render a table header row.
 *
 * @param array $columns  e.g. ['ID','Name','Email']
 * @return string
 */
function table_header(array $columns): string {
    $s = "<thead><tr>\n";
    foreach ($columns as $col) {
        $s .= '<th>' . htmlspecialchars($col, ENT_QUOTES) . "</th>\n";
    }
    return $s . "</tr></thead>\n";
}

/**
 * Render table body rows.
 *
 * @param array<mixed[]> $rows  array of associative or indexed arrays
 * @param array|null     $keys  if associative, list of keys to pick in order
 * @return string
 */
function table_body(array $rows, array $keys = null): string {
    $s = "<tbody>\n";
    foreach ($rows as $row) {
        $s .= "<tr>\n";
        if ($keys) {
            foreach ($keys as $k) {
                $s .= '<td>' . htmlspecialchars((string)($row[$k] ?? ''), ENT_QUOTES) . "</td>\n";
            }
        } else {
            foreach ($row as $cell) {
                $s .= '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES) . "</td>\n";
            }
        }
        $s .= "</tr>\n";
    }
    return $s . "</tbody>\n";
}

/**
 * Close the table tag.
 */
function table_close(): string {
    return "</table>\n";
}

/**
 * Render a complete table in one call.
 *
 * @param array      $columns  header titles
 * @param array      $rows     data rows
 * @param array|null $keys     if associative rows, keys to pick
 * @param array      $attrs    table attributes
 * @return string
 */
function render_table(array $columns, array $rows, array $keys = null, array $attrs = []): string {
    $html  = table_open($attrs);
    $html .= table_header($columns);
    $html .= table_body($rows, $keys);
    $html .= table_close();
    return $html;
}

/**
 * Embed Grid.js initialization for a given table ID.
 *
 * Requires you to include Grid.js assets:
 *   <link href="https://cdn.jsdelivr.net/npm/gridjs/dist/theme/mermaid.min.css" rel="stylesheet" />
 *   <script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>
 *
 * @param string   $tableId  the table’s HTML id
 * @param string[] $columns  column titles
 * @return string  <script>…</script>
 */

/**
 * Grid.js initialiser helper.
 *
 * By default this returns an inline <script> that initialises Grid.js for the table.
 * If strict CSP forbids inline scripts, set:
 *   define('TABLE_GRIDJS_INLINE', false);
 * and use table_gridjs_attrs() + a small external JS bootstrap (see docs).
 *
 * @param string $tableId
 * @param array  $columns  column header strings
 * @param array  $options  Grid.js options override (search/sort/pagination/etc)
 * @return string
 */
function table_init_gridjs(string $tableId, array $columns, array $options = []): string
{
    $inline = !defined('TABLE_GRIDJS_INLINE') || TABLE_GRIDJS_INLINE;

    // Sanitise table id (safe for DOM selector)
    //$tableIdSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tableId);
    $tableIdSafe = sanitizeIdentifierFast($tableId);
    if ($tableIdSafe === '') $tableIdSafe = $tableId; // fallback

    // Sanitise column names (string, strip tags)
    $cols = array_map(function ($c) {
        $name = htmlspecialchars(strip_tags((string)$c), ENT_QUOTES, 'UTF-8');
        return ['name' => $name];
    }, $columns);

    $baseCfg = [
        'columns'     => $cols,
        'sort'        => true,
        'search'      => true,
        'pagination'  => ['enabled' => true, 'limit' => 10],
        'fixedHeader' => true,
        'resizable'   => true,
    ];

    // Allow overrides (shallow merge + special merge for pagination)
    if (!empty($options)) {
        if (isset($options['pagination']) && is_array($options['pagination'])) {
            $baseCfg['pagination'] = array_merge($baseCfg['pagination'], $options['pagination']);
            unset($options['pagination']);
        }
        $baseCfg = array_merge($baseCfg, $options);
    }

    // Encode config once
    $cfgJson = json_encode($baseCfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($cfgJson === false) $cfgJson = '{}';

    // CSP-friendly mode: no inline script output
    if (!$inline) {
        // In CSP mode, you should place config on the table and initialise via an external JS file.
        // We return an empty string here to avoid inline JS.
        return '';
    }

    $cfgJs = $cfgJson;

    // Optional CSP nonce support (if you generate a nonce per request)
    $nonceAttr = '';
    if (defined('CSP_NONCE') && CSP_NONCE) {
        $nonceAttr = ' nonce="' . htmlspecialchars((string)CSP_NONCE, ENT_QUOTES) . '"';
    }

    return <<<JS
<script{$nonceAttr}>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById("{$tableIdSafe}");
  if (!el) return;
  var cfg = {$cfgJs};
  new gridjs.Grid(cfg).render(el);
});
</script>
JS;
}

/**
 * Return attributes to attach to a <table> (or a container <div>) for CSP-safe Grid.js init.
 *
 * Usage:
 *   $attrs = table_gridjs_attrs(['ID','Email']);
 *   echo table_open(array_merge(['id'=>'usersTable'], $attrs));
 *
 * In strict CSP mode, set TABLE_GRIDJS_INLINE=false and initialise in external JS:
 *   window.NoClassGridInit();
 */
function table_gridjs_attrs(array $columns, array $options = []): array
{
    $cols = array_map(function ($c) {
        $name = htmlspecialchars(strip_tags((string)$c), ENT_QUOTES, 'UTF-8');
        return ['name' => $name];
    }, $columns);

    $baseCfg = [
        'columns'     => $cols,
        'sort'        => true,
        'search'      => true,
        'pagination'  => ['enabled' => true, 'limit' => 10],
        'fixedHeader' => true,
        'resizable'   => true,
    ];

    if (!empty($options)) {
        if (isset($options['pagination']) && is_array($options['pagination'])) {
            $baseCfg['pagination'] = array_merge($baseCfg['pagination'], $options['pagination']);
            unset($options['pagination']);
        }
        $baseCfg = array_merge($baseCfg, $options);
    }

    $cfgJson = json_encode($baseCfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($cfgJson === false) $cfgJson = '{}';

    // Put config in a data attribute. Keep it simple and explicit.
    return [
        'data-noclass-grid' => '1',
        'data-gridjs'       => htmlspecialchars($cfgJson, ENT_QUOTES, 'UTF-8'),
    ];
}



/**
 * Output CSV headers and data, then exit.
 *
 * @param array $columns Header titles
 * @param array $rows    Data rows (associative)
 */
function table_export_csv(array $columns, array $rows)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $key = array_search($col, $columns, true);
            $line[] = $row[array_keys($row)[$key]];
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/**
 * Output JSON and exit.
 *
 * @param array $rows Data rows
 */
function table_export_json(array $rows)
{
    header('Content-Type: application/json');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Add a click handler to each row by injecting data-url attribute.
 *
 * @param array       $rows      Data rows
 * @param string      $urlPrefix Prefix URL, e.g. '/user/view/'
 * @param string      $key       Primary key column name
 * @return array                 Rows with 'data-url' added
 */
function table_make_rows_clickable(array $rows, string $urlPrefix, string $key): array
{
    foreach ($rows as &$row) {
        if (isset($row[$key])) {
            $row['data-url'] = $urlPrefix . $row[$key];
        }
    }
    return $rows;
}

/**
 * JS initializer for clickable rows (add in footer).
 */
function table_init_clickable(): string
{
    return <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('table tr[data-url]').forEach(function(tr){
      tr.style.cursor = 'pointer';
      tr.addEventListener('click', function(){
        window.location = tr.getAttribute('data-url');
      });
    });
  });
</script>
JS;
}


/**
 * Wrap table HTML in a responsive div.
 */
function table_responsive(string $tableHtml): string
{
    return '<div style="overflow-x:auto;">' . $tableHtml . '</div>';
}


/**
 * Add a CSS class to each row if the callback returns true.
 *
 * @param array    $rows       Data rows
 * @param callable $callback   fn(array \$row): bool
 * @param string   $className  CSS class to add
 * @return array               Rows with '_row_class' key added
 */
function table_highlight_rows(array $rows, callable $callback, string $className): array
{
    foreach ($rows as &$row) {
        if ($callback($row)) {
            $row['_row_class'] = $className;
        }
    }
    return $rows;

    /*
    // inside table_body():
    $class = isset($row['_row_class']) ? ' class="' . htmlspecialchars($row['_row_class'],ENT_QUOTES) . '"' : '';
    $s .= "<tr{$class}>\n";
    // ...rest of cells...
    */
}

/**
 * Render table with custom cell renderers.
 *
 * @param array      $columns     Header titles
 * @param array      $rows        Data rows
 * @param array|null $keys        Columns to pick
 * @param array      $renderers   [columnKey => fn($value,$row): string]
 * @param array      $attrs       Table attributes
 */
function render_table_custom(array $columns, array $rows, array $keys = null, array $renderers = [], array $attrs = []): string
{
    $html = table_open($attrs) . table_header($columns) . "<tbody>\n";
    foreach ($rows as $row) {
        $html .= "<tr>\n";
        if ($keys) {
            foreach ($keys as $k) {
                $val = $row[$k] ?? '';
                if (isset($renderers[$k])) {
                    $html .= '<td>' . $renderers[$k]($val, $row) . "</td>\n";
                } else {
                    $html .= '<td>' . htmlspecialchars((string)$val, ENT_QUOTES) . "</td>\n";
                }
            }
        } else {
            foreach ($row as $col => $val) {
                if (isset($renderers[$col])) {
                    $html .= '<td>' . $renderers[$col]($val, $row) . "</td>\n";
                } else {
                    $html .= '<td>' . htmlspecialchars((string)$val, ENT_QUOTES) . "</td>\n";
                }
            }
        }
        $html .= "</tr>\n";
    }
    $html .= "</tbody>\n</table>\n";
    return $html;

    /*
        // Highlight amounts > 100 as red
        $rows = table_highlight_rows($orders, fn($r)=>$r['amount']>100,'highlight');

        // Render "status" column with badges
        echo render_table_custom(
          ['ID','Amount','Status'],
          $rows,
          ['id','amount','status'],
          [
            'status' => fn($v,$r)=>"<span class='badge'>{$v}</span>"
          ],
          ['id'=>'orderTable','class'=>'table']
        );
    */
}



/**
 * Render a table footer row with summary values.
 *
 * @param array $summaries   [columnKey => summaryValue]
 * @param array $keys        Column keys (same order as header)
 * @return string
 */
function table_footer(array $summaries, array $keys): string {
    $s = "<tfoot><tr>\n";
    foreach ($keys as $k) {
        $val = isset($summaries[$k]) ? $summaries[$k] : '';
        $s .= '<td>' . htmlspecialchars((string)$val, ENT_QUOTES) . "</td>\n";
    }
    return $s . "</tr></tfoot>\n";

    /*
        // After computing total amount:
        $summary = ['id'=>'Total', 'amount'=>array_sum(array_column($rows,'amount'))];
        echo table_open();
        echo table_header(['ID','Amount'], ['id','amount']);
        echo table_body($rows, ['id','amount']);
        echo table_footer($summary, ['id','amount']);
        echo table_close();
    */
}


/**
 * Render a text input that filters table rows (vanilla JS).
 *
 * @param string $tableId
 * @param array  $attrs   HTML attributes for the input
 * @return string
 */
function table_filter_input(string $tableId, array $attrs = []): string {
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    $html  = "<input type=\"text\" placeholder=\"Filter...\"{$attrStr} onkeyup=\"";
    $html .= "var q=this.value.toLowerCase(),rows=document.getElementById('{$tableId}').getElementsByTagName('tbody')[0].rows;";
    $html .= "for(var i=0;i<rows.length;i++){rows[i].style.display=rows[i].innerText.toLowerCase().includes(q)?'':'none';}\">";
    return $html;

    /*
        echo table_filter_input('userTable', ['class'=>'form-control mb-2']);
        echo render_table([...], $rows, [...], ['id'=>'userTable']);
    */
}

/**
 * Make cells editable inline and POST changes via AJAX.
 *
 * @param string   $tableId
 * @param callable $onSave  JS callback name, e.g. 'saveCell'
 * @return string
 */
function table_inline_edit(string $tableId, string $onSave = 'saveCell'): string {
    return <<<JS
<script>
document.addEventListener('DOMContentLoaded', function(){
  var tbl = document.getElementById("{$tableId}");
  tbl.querySelectorAll('td').forEach(function(td){
    td.setAttribute('contenteditable', true);
    td.addEventListener('blur', function(){
      var data = { table: '{$tableId}', row: this.parentNode.rowIndex-1, col: this.cellIndex, value: this.innerText };
      fetch('/api/inline-edit', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
      }).then(res=>res.json()).then(window['{$onSave}']);
    });
  });
});
</script>
JS;
}

//IMPORTANT: You’d implement /api/inline-edit to update the DB and respond with {success:true}.


/**
 * Responsive Column Toggle
 * Add checkboxes to show/hide columns.
 *
 * @param string   $tableId
 * @param string[] $labels  ['colKey'=>'Label']
 * @return string
 */
function table_column_toggles(string $tableId, array $labels): string {
    $html = "<div class='column-toggles'>";
    foreach ($labels as $key=>$label) {
        $html .= "<label><input type='checkbox' checked onchange=\"";
        $html .= "var idx=[...document.getElementById('{$tableId}').querySelectorAll('thead th')].findIndex(th=>th.innerText=='{$label}');";
        $html .= "document.getElementById('{$tableId}').querySelectorAll('tr').forEach(r=>r.cells[idx].style.display=this.checked?'':'none');";
        $html .= "\"> {$label}</label> ";
    }
    $html .= "</div>";
    return $html;
}




// USAGES
/*
<?php
// In your controller:
$data = db_select('users', ['id','username','email'], ['status'=>'active']);

// In your view:
echo render_table(
  ['ID','Username','Email'], // headers
  $data,                     // rows
  ['id','username','email'], // keys for associative arrays
  ['id'=>'userTable','class'=>'table table-striped']
);

// (Optional) include Grid.js assets in <head>:
?>
<link href="https://cdn.jsdelivr.net/npm/gridjs/dist/theme/mermaid.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>

<?php
// Then initialize:
echo table_init_gridjs('userTable', ['ID','Username','Email']);


if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  table_export_csv(['ID','Username','Email'], $data);
}
echo '<a href="?export=csv">Download CSV</a>';


if (isset($_GET['format']) && $_GET['format'] === 'json') {
  table_export_json($data);
}
echo '<a href="?format=json">Download JSON</a>';

*/