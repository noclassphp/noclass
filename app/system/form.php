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
 * Start a form and inject CSRF token.
 */
/*function form_open(string $action = '', string $method = 'POST', array $attrs = []): string {
    // Ensure session started
    //if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }

    // Form tag + hidden CSRF field
    $html  = "<form action=\"" . htmlspecialchars($action, ENT_QUOTES) . "\" method=\"" . strtoupper($method) . "\"{$attrStr}>\n";
    $html .= "<input type=\"hidden\" name=\"csrf_token\" value=\"{$_SESSION['csrf_token']}\">\n";
    return $html;
}*/

function form_open(string $action = '', string $method = 'POST', array $attrs = []): string
{
    // Do NOT session_start() here. index.php already calls secure_session_start()
    // If someone uses this without bootstrapping, you can optionally call secure_session_start():
    if (function_exists('secure_session_start')) secure_session_start();

    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . "\"";
    }

    $html  = "<form action=\"" . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . "\" method=\"" . htmlspecialchars(strtoupper($method), ENT_QUOTES, 'UTF-8') . "\"{$attrStr}>\n";

    // Use the unified CSRF field helper
    if (function_exists('csrf_field')) {
        $html .= csrf_field();
    }

    return $html;
}


/** Close the form */
function form_close(): string {
    return "</form>\n";
}

/** Retrieve old input or default */
function old(string $field, $default = '') {
    if (!empty($_SESSION['old'][$field])) {
        return htmlspecialchars($_SESSION['old'][$field], ENT_QUOTES);
    }
    return htmlspecialchars($default, ENT_QUOTES);
}

/** Set old inputs (call at top of processing) */
function form_set_old(array $data) {
    $_SESSION['old'] = $data;
}

/** Clear old inputs */
function form_clear_old() {
    unset($_SESSION['old']);
}

/** Validation error setter */
function form_set_error(string $field, string $msg) {
    $_SESSION['errors'][$field][] = $msg;
}

/** Retrieve errors for a field */
function form_error(string $field): array {
    return $_SESSION['errors'][$field] ?? [];
}

/** Clear all errors */
function form_clear_errors() {
    unset($_SESSION['errors']);
}

/*function csrf_field(): string {
    //if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' 
         . $_SESSION['csrf_token'] . '">';
}*/


/** Check CSRF token validity */
function form_validate_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Input field generator */
function form_input(string $type, string $name, $value = '', array $attrs = []): string {
    $val = old($name, $value);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"{$type}\" name=\"{$name}\" value=\"{$val}\"{$attrStr}>\n";
}

/** Textarea generator */
function form_textarea(string $name, $value = '', array $attrs = []): string {
    $val = old($name, $value);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<textarea name=\"{$name}\"{$attrStr}>{$val}</textarea>\n";
}

/** Select dropdown generator */
function form_select(string $name, array $options, $selected = null, array $attrs = []): string {
    $sel = old($name, $selected);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    $html  = "<select name=\"{$name}\"{$attrStr}>\n";
    foreach ($options as $value => $label) {
        $isSel = ($value == $sel) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars((string)$value, ENT_QUOTES) . "\"{$isSel}>" 
               . htmlspecialchars($label, ENT_QUOTES) 
               . "</option>\n";
    }
    $html .= "</select>\n";
    return $html;
}

/** Checkbox generator */
function form_checkbox(string $name, $value = '1', $checked = false, array $attrs = []): string {
    $old = old($name, null);
    $isChecked = ($old !== null ? $old == $value : $checked) ? ' checked' : '';
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"checkbox\" name=\"{$name}\" value=\"" . htmlspecialchars((string)$value, ENT_QUOTES) . "\"{$isChecked}{$attrStr}>\n";
}

/** Radio generator */
function form_radio(string $name, $value, $checked = false, array $attrs = []): string {
    $old = old($name, null);
    $isChecked = ($old !== null ? $old == $value : $checked) ? ' checked' : '';
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"radio\" name=\"{$name}\" value=\"" . htmlspecialchars((string)$value, ENT_QUOTES) . "\"{$isChecked}{$attrStr}>\n";
}

/** Simple required field validator */
function validate_required(string $field, string $label, array $data): bool {
    if (empty($data[$field])) {
        form_set_error($field, "{$label} is required.");
        return false;
    }
    return true;
}

/** Email validator */
function validate_email(string $field, string $label, array $data): bool {
    if (!filter_var($data[$field] ?? '', FILTER_VALIDATE_EMAIL)) {
        form_set_error($field, "{$label} must be a valid email.");
        return false;
    }
    return true;
}

/** Integer validator */
function validate_int(string $field, string $label, array $data): bool {
    if (!filter_var($data[$field] ?? '', FILTER_VALIDATE_INT)) {
        form_set_error($field, "{$label} must be an integer.");
        return false;
    }
    return true;
}

/** Custom callback validator */
function validate_callback(string $field, string $label, array $data, callable $fn, string $msg): bool {
    if (!$fn($data[$field] ?? null)) {
        form_set_error($field, $msg);
        return false;
    }
    return true;
}

function form_reset(): void {
    unset($_SESSION['old']);
}


//Render all validation errors in one block (e.g. top of form).
function form_error_summary(): string {
    if (empty($_SESSION['errors'])) return '';
    $html = "<div class=\"error-summary\"><ul>\n";
    foreach ($_SESSION['errors'] as $fieldErrors) {
        foreach ($fieldErrors as $msg) {
            $html .= "<li>" . htmlspecialchars($msg, ENT_QUOTES) . "</li>\n";
        }
    }
    $html .= "</ul></div>\n";
    return $html;
}


//Generate hidden inputs for arbitrary name/value pairs.

function form_hidden(string $name, $value): string {
    return "<input type=\"hidden\" name=\"" 
         . htmlspecialchars($name, ENT_QUOTES) 
         . "\" value=\"" 
         . htmlspecialchars((string)$value, ENT_QUOTES) 
         . "\">\n";
}


function form_multiselect(string $name, array $options, array $selected = [], array $attrs = []): string {
    $old = $_SESSION['old'][$name] ?? $selected;
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    $html = "<select name=\"" . htmlspecialchars($name,ENT_QUOTES) 
          . "[]\" multiple{$attrStr}>\n";
    foreach ($options as $val => $lbl) {
        $sel = in_array($val, (array)$old, true) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars((string)$val,ENT_QUOTES) 
               . "\"{$sel}>" . htmlspecialchars($lbl,ENT_QUOTES) . "</option>\n";
    }
    $html .= "</select>\n";
    return $html;
}


/**
 * Generate a date input.
 *
 * @param string $name   Field name
 * @param string $value  Initial value (YYYY-MM-DD)
 * @param array  $attrs  Additional HTML attributes
 * @return string        HTML <input type="date">
 */
function form_date(string $name, string $value = '', array $attrs = []): string {
    $val = old($name, $value);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"date\" name=\"{$name}\" value=\"" 
         . htmlspecialchars($val, ENT_QUOTES) . "\"{$attrStr}>";
}

/**
 * Generate a datetime-local input.
 *
 * @param string $name
 * @param string $value  Initial value (YYYY-MM-DDTHH:MM)
 * @param array  $attrs
 * @return string
 */
function form_datetime(string $name, string $value = '', array $attrs = []): string {
    $val = old($name, $value);
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"datetime-local\" name=\"{$name}\" value=\"" 
         . htmlspecialchars($val, ENT_QUOTES) . "\"{$attrStr}>";
}

/**
 * Generate a file input.
 *
 * @param string $name   Field name
 * @param array  $attrs  HTML attributes
 * @return string
 */
function form_file(string $name, array $attrs = []): string {
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        $attrStr .= " {$k}=\"" . htmlspecialchars($v, ENT_QUOTES) . "\"";
    }
    return "<input type=\"file\" name=\"{$name}\"{$attrStr}>";
}

/**
 * Validate that a file was uploaded.
 */
function validate_file_required(string $field, string $label): bool {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        form_set_error($field, "{$label} is required.");
        return false;
    }
    return true;
}

/**
 * Validate file upload: enforces MIME-type AND extension whitelist.
 *
 * @param string $field      Field name
 * @param array  $mimeTypes  Allowed MIME types, e.g. ['image/jpeg','image/png']
 * @param array  $exts       Allowed file extensions, e.g. ['jpg','jpeg','png']
 * @param int    $maxSize    Max size in bytes (default 2MB)
 * @return bool
 */
function validate_file(string $field, array $mimeTypes, array $exts, int $maxSize = 2097152): bool {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        form_set_error($field, "Error uploading file.");
        return false;
    }
    $file = $_FILES[$field];

    // Size check
    if ($file['size'] > $maxSize) {
        form_set_error($field, "File exceeds max size of " . ($maxSize / 1048576) . " MB.");
        return false;
    }

    // MIME-type check
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $mimeTypes, true)) {
        form_set_error($field, "Invalid file type ($mime). Allowed: " . implode(', ', $mimeTypes));
        return false;
    }

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) {
        form_set_error($field, "Invalid file extension ($ext). Allowed: " . implode(', ', $exts));
        return false;
    }

    return true;
}

/**
 * Handle file upload: randomize name, store outside web root, set permissions, log on failure.
 *
 * @param string $field      Field name
 * @param string $targetDir  Absolute path outside web root (must be writable)
 * @param string $prefix     Optional filename prefix
 * @return string|false      Final filename or false on failure
 */
function handle_file_upload(string $field, string $targetDir, string $prefix = '') {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        error_log("[NoClass][UPLOAD] No valid upload for field: {$field}");
        return false;
    }
    $file = $_FILES[$field];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Ensure target directory exists with 0700
    if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true)) {
        error_log("[NoClass][UPLOAD] Failed to create directory: {$targetDir}");
        form_set_error($field, "Server error: cannot create upload directory.");
        return false;
    }
    chmod($targetDir, 0700);

    // Generate randomized filename
    $basename = bin2hex(random_bytes(8));
    $filename = ($prefix ? $prefix . '_' : '') . $basename . '.' . $ext;
    $dest     = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error_log("[NoClass][UPLOAD] move_uploaded_file failed for {$field} to {$dest}");
        form_set_error($field, "Server error: cannot save uploaded file.");
        return false;
    }

    // Secure the file permissions
    chmod($dest, 0600);

    return $filename;
}

//Supporting older browsers for date input
function form_date_fallback(string $name, string $value = '', array $attrs = []): string {
  $val = old($name, $value);
  list($y,$m,$d) = explode('-', $val . '-01-01'); 
  // Year select
  $html = "<select name=\"{$name}[year]\">"; 
  for($i=date('Y')-100;$i<=date('Y');$i++){
    $sel = $i==$y?' selected':''; $html.="<option{$sel}>{$i}</option>";
  }
  $html .= "</select> - ";
  // Month select
  $html .= "<select name=\"{$name}[month]\">";
  for($i=1;$i<=12;$i++){
    $sel=$i==$m?' selected':''; $html.="<option value=\"{$i}\"{$sel}>{$i}</option>";
  }
  $html .= "</select> - ";
  // Day select
  $html .= "<select name=\"{$name}[day]\">";
  for($i=1;$i<=31;$i++){
    $sel=$i==$d?' selected':''; $html.="<option value=\"{$i}\"{$sel}>{$i}</option>";
  }
  $html .= "</select>";
  return $html;
}

/*

// In your action handling upload:
$formOk = validate_file('avatar', ['image/jpeg','image/png'], ['jpg','jpeg','png'], 2*1024*1024);
if ($formOk) {
    $saved = handle_file_upload('avatar', '/var/www/uploads', 'avatar');
    if ($saved !== false) {
        // store $saved in DB...
    }
}





Older browsers that don’t support <input type="date"> or <input type="datetime-local"> will simply render them as text fields. To ensure usability, you can:

Detect Support and Polyfill
Use JavaScript to check support and load a date-picker polyfill (e.g. Pikaday or jQuery UI Datepicker) only when needed.

<!-- in your page’s <head> -->
<script>
document.addEventListener('DOMContentLoaded', function(){
  var input = document.createElement('input');
  input.setAttribute('type','date');
  if (input.type === 'text') {
    // Browser doesn’t support type="date"
    var script = document.createElement('script');
    script.src = '/assets/js/pikaday.js';
    document.head.appendChild(script);
    script.onload = function(){
      new Pikaday({ field: document.getElementById('birthdate') });
    };
  }
});
</script>

Use Separate <select> Fallbacks
Provide three <select> fields (day/month/year) when HTML5 date isn’t available:

function form_date_fallback(string $name, string $value = '', array $attrs = []): string {
  $val = old($name, $value);
  list($y,$m,$d) = explode('-', $val . '-01-01'); 
  // Year select
  $html = "<select name=\"{$name}[year]\">"; 
  for($i=date('Y')-100;$i<=date('Y');$i++){
    $sel = $i==$y?' selected':''; $html.="<option{$sel}>{$i}</option>";
  }
  $html .= "</select> - ";
  // Month select
  $html .= "<select name=\"{$name}[month]\">";
  for($i=1;$i<=12;$i++){
    $sel=$i==$m?' selected':''; $html.="<option value=\"{$i}\"{$sel}>{$i}</option>";
  }
  $html .= "</select> - ";
  // Day select
  $html .= "<select name=\"{$name}[day]\">";
  for($i=1;$i<=31;$i++){
    $sel=$i==$d?' selected':''; $html.="<option value=\"{$i}\"{$sel}>{$i}</option>";
  }
  $html .= "</select>";
  return $html;
}

On form processing, combine them:

if (isset($_POST['birthdate'])) {
  $y = (int)$_POST['birthdate']['year'];
  $m = str_pad((int)$_POST['birthdate']['month'],2,'0',STR_PAD_LEFT);
  $d = str_pad((int)$_POST['birthdate']['day'],2,'0',STR_PAD_LEFT);
  $birthdate = "{$y}-{$m}-{$d}";
}

*/




//USAGE

/*

<?php
// In your controller action:

// 1. On form display:
form_clear_old();
form_clear_errors();
echo form_open('/submit', 'POST', ['class'=>'my-form']);
echo form_input('text','username','',['placeholder'=>'Username']);
foreach (form_error('username') as $err) echo "<small class='error'>{$err}</small>";
echo form_input('email','email','',['placeholder'=>'Email']);
foreach (form_error('email') as $err) echo "<small class='error'>{$err}</small>";
echo form_input('password','password','',['placeholder'=>'Password']);
echo '<button type="submit">Register</button>';
echo form_close();

// 2. On form submission (e.g. POST to /submit):
form_clear_errors();
form_set_old($_POST);

// CSRF check
if (!form_validate_csrf($_POST['csrf_token'] ?? '')) {
    die("Invalid CSRF token");
}

// Validation
$ok  = validate_required('username','Username',$_POST);
$ok &= validate_email   ('email','Email',$_POST);
$ok &= validate_required('password','Password',$_POST);

if (!$ok) {
    // Redisplay form with errors & old values
    header("Location: /register");
    exit;
}

// Process data (e.g. db_insert)
$userId = db_insert('users', [
    'username'=>$_POST['username'],
    'email'=>$_POST['email'],
    'password'=>password_hash($_POST['password'],PASSWORD_DEFAULT)
]);

// Clear old/input after success
form_clear_old();
form_clear_errors();
echo "Registered user ID {$userId}";


//Quick Summary of New Helpers

csrf_field();
form_reset();
echo form_error_summary();
form_hidden('token',$token);
echo form_file('avatar');
validate_file_required('avatar');
validate_file_type('avatar',['image/jpeg','image/png']);
echo form_multiselect('tags',['php'=>'PHP','js'=>'JavaScript'],['php']);
echo form_date('birthdate');
echo form_datetime('event_time');


// In your view:
echo form_open('/profile', 'POST', ['enctype'=>'multipart/form-data']);
echo '<label>Birthdate:</label>' . form_date('birthdate');
foreach (form_error('birthdate') as $e) echo "<div class='error'>{$e}</div>";
echo '<label>Appointment:</label>' . form_datetime('appointment');
foreach (form_error('appointment') as $e) echo "<div class='error'>{$e}</div>";
echo '<label>Avatar:</label>' . form_file('avatar');
foreach (form_error('avatar') as $e) echo "<div class='error'>{$e}</div>";
echo '<button type="submit">Submit</button>';
echo form_close();



<?php
// Controller or action:

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    form_clear_errors();
    form_set_old($_POST);

    // CSRF check
    if (!form_validate_csrf($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF");
    }

    // Validate date and datetime
    validate_required('birthdate','Birthdate',$_POST);
    validate_required('appointment','Appointment',$_POST);

    // Validate file
    validate_file_required('avatar','Avatar');
    validate_file('avatar', ['image/jpeg','image/png'], 2*1024*1024);

    if (empty($_SESSION['errors'])) {
        // Handle file upload
        $filename = handle_file_upload('avatar', __DIR__ . '/uploads', 'user_avatar');
        if ($filename !== false) {
            // Save $filename and dates to DB...
            $birthdate   = $_POST['birthdate'];      // 'YYYY-MM-DD'
            $appointment = $_POST['appointment'];    // 'YYYY-MM-DDTHH:MM'
            db_insert('profiles', [
                'birthdate'   => $birthdate,
                'appointment' => str_replace('T',' ',$appointment) . ':00',
                'avatar'      => $filename,
            ]);
            form_reset();
            echo "Profile saved!";
            exit;
        }
    }
    // If errors, redisplay form...
}

// In your view:
echo form_open('/profile', 'POST', ['enctype'=>'multipart/form-data']);
echo '<label>Birthdate:</label>' . form_date('birthdate');
foreach (form_error('birthdate') as $e) echo "<div class='error'>{$e}</div>";
echo '<label>Appointment:</label>' . form_datetime('appointment');
foreach (form_error('appointment') as $e) echo "<div class='error'>{$e}</div>";
echo '<label>Avatar:</label>' . form_file('avatar');
foreach (form_error('avatar') as $e) echo "<div class='error'>{$e}</div>";
echo '<button type="submit">Submit</button>';
echo form_close();



*/