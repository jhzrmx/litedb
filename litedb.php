<?php
/**
 * LiteDB 1.0.0 - lightweight single-file database manager
 * MySQL / MariaDB, PostgreSQL and SQLite via PDO.
 * Runs on PHP 5.6 up to PHP 8.5. License: MIT. @jhzrmx
 *
 * SECURITY: a database manager is a powerful tool. Set 'access_password' and/or
 * 'allowed_ips' below, serve over HTTPS, and delete or rename this file when you
 * are not using it. Credentials are kept in the PHP session on the server.
 */

// ============================== CONFIG ==============================
$LITEDB_CONFIG = array(
    'access_password' => '',                      // extra password required on the login form ('' = off)
    'allowed_ips'     => array(),                 // e.g. array('127.0.0.1', '::1') ('' = any)
    'drivers'         => array('mysql', 'pgsql', 'sqlite'), // you can omit some drivers if you only use specific database
    'default_driver'  => '',                      // 'mysql', 'pgsql' or 'sqlite' ('' = first enabled driver)
    'default_server'  => 'localhost',             // login form default (host, host:port or /socket/path)
    'default_user'    => '',                      // login form default username
    'default_pass'    => '',                      // used when the password field is left empty (never sent to the browser)
    'default_db'      => '',                      // login form default database (SQLite: file path)
    'sqlite_basedir'  => '',                      // restrict SQLite files to this directory ('' = any path)
    'readonly'        => false,                   // block writes from the UI (use a read-only DB user for real safety)
    'per_page'        => 50,
    'max_result_rows' => 1000,                    // max rows shown by the SQL console
);
// ====================================================================

define('LITEDB_VERSION', '1.0.0');

if (version_compare(PHP_VERSION, '5.6.0', '<')) {
    die('LiteDB requires PHP 5.6 or newer.');
}
if (!class_exists('PDO')) {
    die('LiteDB requires the PDO extension.');
}

@ini_set('display_errors', '0');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly', '1');

function cfg($k)
{
    global $LITEDB_CONFIG;
    return $LITEDB_CONFIG[$k];
}

/** Validate $LITEDB_CONFIG: report mistakes, fall back to safe defaults. */
function config_normalize($cfg, &$err)
{
    $def = array(
        'access_password' => '', 'allowed_ips' => array(), 'drivers' => array('mysql', 'pgsql', 'sqlite'),
        'default_driver' => '', 'default_server' => 'localhost', 'default_user' => '', 'default_pass' => '', 'default_db' => '',
        'sqlite_basedir' => '', 'readonly' => false, 'per_page' => 50, 'max_result_rows' => 1000,
    );
    if (!is_array($cfg)) {
        $err[] = '$LITEDB_CONFIG must be an array.';
        $cfg = array();
    }
    foreach ($cfg as $k => $v) {
        if (!array_key_exists($k, $def)) {
            $err[] = "Unknown option '" . $k . "' (typo?). Valid options: " . implode(', ', array_keys($def)) . '.';
        }
    }
    $out = $def;
    foreach ($def as $k => $dv) {
        if (!array_key_exists($k, $cfg)) {
            continue;
        }
        $v = $cfg[$k];
        if (is_array($dv)) {
            $ok = is_array($v);
            $want = 'an array';
        } elseif (is_bool($dv)) {
            $ok = is_bool($v);
            $want = 'true or false';
        } elseif (is_int($dv)) {
            $ok = is_int($v);
            $want = 'an integer';
        } else {
            $ok = is_string($v);
            $want = 'a string';
        }
        if (!$ok) {
            $err[] = "Option '" . $k . "' must be " . $want . ', ' . gettype($v) . ' given. The default is used instead.';
            continue;
        }
        $out[$k] = $v;
    }
    $valid = array('mysql', 'pgsql', 'sqlite');
    $avail = PDO::getAvailableDrivers();
    $dr = array();
    foreach ($out['drivers'] as $d) {
        if (!is_string($d) || !in_array($d, $valid, true)) {
            $err[] = "Invalid entry in 'drivers': " . (is_string($d) ? "'" . $d . "'" : gettype($d)) . ' (allowed: mysql, pgsql, sqlite).';
            continue;
        }
        $dr[] = $d;
        if (!in_array($d, $avail, true)) {
            $err[] = "Driver '" . $d . "' is enabled in 'drivers' but the PHP extension pdo_" . $d . ' is not loaded.';
        }
    }
    if (!$dr) {
        $err[] = "'drivers' contains no valid entry; all drivers are enabled instead.";
        $dr = $valid;
    }
    $out['drivers'] = $dr;
    if ($out['default_driver'] !== '' && !in_array($out['default_driver'], $dr, true)) {
        $err[] = "'default_driver' is '" . $out['default_driver'] . "' but it is not listed in 'drivers'; ignored.";
        $out['default_driver'] = '';
    }
    foreach ($out['allowed_ips'] as $ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $err[] = "Invalid IP address in 'allowed_ips': " . (is_string($ip) ? "'" . $ip . "'" : gettype($ip)) . '.';
        }
    }
    if (trim($out['default_server']) === '') {
        $err[] = "'default_server' is empty; 'localhost' is used.";
        $out['default_server'] = 'localhost';
    }
    if ($out['sqlite_basedir'] !== '' && !is_dir($out['sqlite_basedir'])) {
        $err[] = "'sqlite_basedir' is not an existing directory: " . $out['sqlite_basedir'];
    } elseif ($out['sqlite_basedir'] !== '' && !is_readable($out['sqlite_basedir'])) {
        $err[] = "'sqlite_basedir' is not readable by PHP: " . $out['sqlite_basedir'];
    }
    if ($out['per_page'] < 1 || $out['per_page'] > 1000) {
        $err[] = "'per_page' must be between 1 and 1000; 50 is used.";
        $out['per_page'] = 50;
    }
    if ($out['max_result_rows'] < 1) {
        $err[] = "'max_result_rows' must be at least 1; 1000 is used.";
        $out['max_result_rows'] = 1000;
    }
    if ($out['default_pass'] !== '' && $out['access_password'] === '' && !$out['allowed_ips']) {
        $err[] = "Warning: 'default_pass' is set but neither 'access_password' nor 'allowed_ips' restricts access. Anyone who can open this page can log in with it.";
    }
    return $out;
}
$CONFIG_ERRORS = array();
$LITEDB_CONFIG = config_normalize($LITEDB_CONFIG, $CONFIG_ERRORS);

// ---------- access control + session ----------
$ips = cfg('allowed_ips');
if ($ips && !in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', $ips, true)) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied.');
}

session_name('litedb_sid');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
} else {
    session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
}
session_start();

// ---------- helpers ----------
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function gv($k, $d = '')
{
    return (isset($_GET[$k]) && is_string($_GET[$k])) ? $_GET[$k] : $d;
}
function pv($k, $d = '')
{
    return (isset($_POST[$k]) && is_string($_POST[$k])) ? $_POST[$k] : $d;
}
function pa($k)
{
    return (isset($_POST[$k]) && is_array($_POST[$k])) ? $_POST[$k] : array();
}
function url($p)
{
    $o = array();
    foreach ($p as $k => $v) {
        if ($v !== '' && $v !== null && $v !== false) {
            $o[$k] = $v;
        }
    }
    return '?' . http_build_query($o, '', '&');
}
function flash($msg, $type = 'ok')
{
    $_SESSION['flash'][] = array($type, $msg);
}
function redirect($u)
{
    header('Location: ' . $u);
    exit;
}
function redir_ret($default)
{
    $r = pv('ret');
    if ($r !== '' && $r[0] === '?') {
        redirect($r);
    }
    redirect($default);
}
function rnd($n)
{
    if (function_exists('random_bytes')) {
        try {
            return bin2hex(random_bytes($n));
        } catch (Exception $e) {
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($n));
    }
    $s = '';
    for ($i = 0; $i < $n; $i++) {
        $s .= chr(mt_rand(0, 255));
    }
    return bin2hex($s);
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = rnd(16);
}
function csrf_field()
{
    return '<input type="hidden" name="_t" value="' . h($_SESSION['csrf']) . '">';
}
function csrf_check($val = null)
{
    $val = ($val === null) ? pv('_t') : $val;
    if (!hash_equals((string)$_SESSION['csrf'], (string)$val)) {
        throw new Exception('Invalid security token. Reload the page and try again.');
    }
}
function no_ro()
{
    if (cfg('readonly')) {
        throw new Exception('LiteDB is in read-only mode.');
    }
}
function hsize($b)
{
    $b = (float)$b;
    $u = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($b >= 1024 && $i < 4) {
        $b /= 1024;
        $i++;
    }
    return ($i ? round($b, 1) : (int)$b) . ' ' . $u[$i];
}
function truthy($v)
{
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true' || $v === 'YES';
}
function litedb_unquote($x)
{
    return str_replace("''", "'", $x);
}
function cut($s, $n)
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}
function val_str($v)
{
    if (is_resource($v)) {
        $v = stream_get_contents($v);
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    return (string)$v;
}
function is_bin_str($s)
{
    return $s !== '' && preg_match('//u', $s) === false;
}
function cell($v)
{
    if ($v === null) {
        return '<td class="null">NULL</td>';
    }
    $s = val_str($v);
    if (is_bin_str($s)) {
        return '<td class="bin" title="' . h(bin2hex(substr($s, 0, 32))) . '">[' . strlen($s) . ' bytes]</td>';
    }
    if (strlen($s) > 200) {
        return '<td title="' . h(cut($s, 1000)) . '">' . h(cut($s, 200)) . '&hellip;</td>';
    }
    return '<td>' . h($s) . '</td>';
}
function bin_cell($len, $url)
{
    $t = hsize($len);
    return $url !== '' ? '<td class="bin" title="' . h((string)$len) . ' bytes"><a href="' . h($url) . '">&#11015; ' . h($t) . '</a></td>'
        : '<td class="bin" title="' . h((string)$len) . ' bytes">[' . h($t) . ']</td>';
}
function rk_enc($arr)
{
    $j = json_encode($arr);
    if ($j === false) {
        return null;
    }
    return rtrim(strtr(base64_encode($j), '+/', '-_'), '=');
}
function rk_dec($s)
{
    $a = json_decode((string)base64_decode(strtr($s, '-_', '+/')), true);
    return is_array($a) ? $a : null;
}
function csv_line($arr)
{
    $o = array();
    foreach ($arr as $v) {
        if ($v === null) {
            $o[] = '';
            continue;
        }
        $v = val_str($v);
        $o[] = (strpbrk($v, ",\"\r\n") !== false) ? '"' . str_replace('"', '""', $v) . '"' : $v;
    }
    return implode(',', $o) . "\r\n";
}

/** Split SQL text into statements (quotes, comments, pg dollar quoting, MySQL DELIMITER). */
function split_sql($sql, $driver)
{
    $out = array();
    $buf = '';
    $n = strlen($sql);
    $i = 0;
    $delim = ';';
    $mysql = ($driver === 'mysql');
    while ($i < $n) {
        if ($mysql && strspn($buf, " \t\r\n") === strlen($buf)
            && preg_match('/\G\s*DELIMITER[ \t]+(\S+)[ \t]*(?:\r?\n|$)/i', $sql, $m, 0, $i)) {
            $delim = $m[1];
            $i += strlen($m[0]);
            $buf = '';
            continue;
        }
        $dl = strlen($delim);
        $k = strcspn($sql, '\'"`-#/$;' . $delim[0], $i);
        if ($k > 0) {
            $buf .= substr($sql, $i, $k);
            $i += $k;
            continue;
        }
        if (substr($sql, $i, $dl) === $delim) {
            $st = trim($buf);
            if ($st !== '') {
                $out[] = $st;
            }
            $buf = '';
            $i += $dl;
            continue;
        }
        $c = $sql[$i];
        $d = ($i + 1 < $n) ? $sql[$i + 1] : '';
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $n) {
                $x = $sql[$j];
                if ($x === '\\' && $mysql && $c !== '`') {
                    $j += 2;
                    continue;
                }
                if ($x === $c) {
                    if ($j + 1 < $n && $sql[$j + 1] === $c) {
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $j++;
            }
            $buf .= substr($sql, $i, $j - $i + 1);
            $i = $j + 1;
            continue;
        }
        if (($c === '-' && $d === '-') || ($c === '#' && $mysql)) {
            $j = strpos($sql, "\n", $i);
            $i = ($j === false) ? $n : $j;
            continue;
        }
        if ($c === '/' && $d === '*') {
            $j = strpos($sql, '*/', $i + 2);
            $j = ($j === false) ? $n : $j + 2;
            $chunk = substr($sql, $i, $j - $i);
            if (substr($chunk, 0, 3) === '/*!') {
                $buf .= $chunk;
            }
            $i = $j;
            continue;
        }
        if ($c === '$' && $driver === 'pgsql' && preg_match('/\G\$[A-Za-z_0-9]*\$/', $sql, $m, 0, $i)) {
            $tag = $m[0];
            $j = strpos($sql, $tag, $i + strlen($tag));
            $j = ($j === false) ? $n : $j + strlen($tag);
            $buf .= substr($sql, $i, $j - $i);
            $i = $j;
            continue;
        }
        $buf .= $c;
        $i++;
    }
    $st = trim($buf);
    if ($st !== '') {
        $out[] = $st;
    }
    return $out;
}

/** Streaming CSV parser (generator); no dependency on fgetcsv/str_getcsv escape defaults. */
function csv_rows($s, $d)
{
    $n = strlen($s);
    $i = 0;
    $row = array();
    $f = '';
    $q = false;
    $had = false;
    while ($i < $n) {
        if ($q) {
            $k = strcspn($s, '"', $i);
            $f .= substr($s, $i, $k);
            $i += $k;
            if ($i >= $n) {
                break;
            }
            if ($i + 1 < $n && $s[$i + 1] === '"') {
                $f .= '"';
                $i += 2;
            } else {
                $q = false;
                $i++;
            }
            continue;
        }
        $k = strcspn($s, $d . "\"\r\n", $i);
        if ($k > 0) {
            $f .= substr($s, $i, $k);
            $i += $k;
            $had = true;
            continue;
        }
        $c = $s[$i];
        if ($c === '"') {
            $q = true;
            $had = true;
            $i++;
            continue;
        }
        if ($c === $d) {
            $row[] = $f;
            $f = '';
            $had = true;
            $i++;
            continue;
        }
        if ($c === "\r" && $i + 1 < $n && $s[$i + 1] === "\n") {
            $i++;
        }
        $i++;
        if ($had || $f !== '' || $row) {
            $row[] = $f;
            yield $row;
        }
        $row = array();
        $f = '';
        $had = false;
    }
    if ($had || $f !== '' || $row) {
        $row[] = $f;
        yield $row;
    }
}

// ============================== DB LAYER ==============================
class LiteDB_Db
{
    public $pdo;
    public $driver = '';
    public $version = '';

    public function q($id)
    {
        if ($this->driver === 'mysql') {
            return '`' . str_replace('`', '``', $id) . '`';
        }
        return '"' . str_replace('"', '""', $id) . '"';
    }

    public function connect($a, $db)
    {
        $this->driver = $a['driver'];
        $o = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC);
        if ($this->driver === 'sqlite') {
            $this->pdo = new PDO('sqlite:' . $a['path'], null, null, $o);
        } else {
            $host = $a['server'];
            $port = '';
            $socket = ($host !== '' && $host[0] === '/');
            if (!$socket && preg_match('/^([^:]+):(\d+)$/', $host, $m)) {
                $host = $m[1];
                $port = $m[2];
            }
            if ($this->driver === 'mysql') {
                $dsn = $socket ? 'mysql:unix_socket=' . $host : 'mysql:host=' . $host . ($port !== '' ? ';port=' . $port : '');
                $dsn .= ';charset=utf8mb4';
            } else {
                $dbn = ($db !== '') ? $db : (($a['db'] !== '') ? $a['db'] : 'postgres');
                $dsn = 'pgsql:host=' . $host . ($port !== '' ? ';port=' . $port : '') . ';dbname=' . $dbn;
            }
            $this->pdo = new PDO($dsn, $a['user'], $a['pass'], $o);
            if ($this->driver === 'mysql' && $db !== '') {
                $this->pdo->exec('USE ' . $this->q($db));
            }
        }
        $this->version = (string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function run($sql, $params = array())
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }
    public function all($sql, $params = array())
    {
        return $this->run($sql, $params)->fetchAll();
    }
    public function one($sql, $params = array())
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** MySQL storage engines that can be used (empty for other drivers). */
    public function engines()
    {
        $out = array();
        if ($this->driver === 'mysql') {
            foreach ($this->all('SHOW ENGINES') as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                if (in_array(strtoupper((string)$r['support']), array('YES', 'DEFAULT'), true)) {
                    $out[] = $r['engine'];
                }
            }
            sort($out);
        }
        return $out;
    }

    /** MySQL collations grouped by character set: array(charset => array(collation, ...)). */
    public function collations()
    {
        $out = array();
        if ($this->driver === 'mysql') {
            foreach ($this->all('SHOW COLLATION') as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $out[$r['charset']][] = $r['collation'];
            }
            ksort($out);
        }
        return $out;
    }

    public function schemas()
    {
        $out = array();
        if ($this->driver === 'pgsql') {
            foreach ($this->all("SELECT schema_name FROM information_schema.schemata WHERE schema_name <> 'information_schema' AND LEFT(schema_name, 3) <> 'pg_' ORDER BY 1") as $r) {
                $out[] = $r['schema_name'];
            }
        }
        return $out;
    }

    public function databases()
    {
        $out = array();
        if ($this->driver === 'mysql') {
            $rows = $this->all('SHOW DATABASES');
        } elseif ($this->driver === 'pgsql') {
            $rows = $this->all('SELECT datname FROM pg_database WHERE NOT datistemplate ORDER BY 1');
        } else {
            return $out;
        }
        foreach ($rows as $r) {
            $v = array_values($r);
            $out[] = $v[0];
        }
        return $out;
    }

    public function tables()
    {
        $out = array();
        if ($this->driver === 'mysql') {
            foreach ($this->all('SHOW TABLE STATUS') as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $view = ($r['engine'] === null || $r['comment'] === 'VIEW');
                $out[] = array('name' => $r['name'], 'type' => $view ? 'view' : 'table', 'engine' => (string)$r['engine'], 'collation' => (string)$r['collation'],
                    'rows' => $view ? '' : $r['rows'], 'size' => (int)$r['data_length'] + (int)$r['index_length']);
            }
        } elseif ($this->driver === 'pgsql') {
            $sql = "SELECT c.relname AS name, c.relkind AS kind, c.reltuples::bigint AS rows, pg_total_relation_size(c.oid) AS size
                    FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = current_schema() AND c.relkind IN ('r','v','m','p','f') ORDER BY c.relname";
            foreach ($this->all($sql) as $r) {
                $view = ($r['kind'] === 'v' || $r['kind'] === 'm');
                $out[] = array('name' => $r['name'], 'type' => $view ? 'view' : 'table', 'engine' => '',
                    'rows' => $view ? '' : '~' . max(0, (int)$r['rows']), 'size' => (int)$r['size']);
            }
        } else {
            $sql = "SELECT name, type FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\' ORDER BY name";
            foreach ($this->all($sql) as $r) {
                $n = '';
                if ($r['type'] === 'table') {
                    try {
                        $n = $this->one('SELECT COUNT(*) FROM ' . $this->q($r['name']));
                    } catch (Exception $e) {
                    }
                }
                $out[] = array('name' => $r['name'], 'type' => $r['type'], 'engine' => '', 'rows' => $n, 'size' => 0);
            }
        }
        return $out;
    }

    public function columns($t)
    {
        $out = array();
        $qt = $this->q($t);
        if ($this->driver === 'mysql') {
            foreach ($this->all('SHOW FULL COLUMNS FROM ' . $qt) as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $auto = stripos($r['extra'], 'auto_increment') !== false;
                $out[] = array('name' => $r['field'], 'type' => $r['type'], 'null' => $r['null'] === 'YES',
                    'default' => $r['default'], 'pk' => $r['key'] === 'PRI', 'auto' => $auto,
                    'hasdef' => ($r['default'] !== null || $auto || stripos($r['extra'], 'DEFAULT_GENERATED') !== false),
                    'extra' => $r['extra'], 'collation' => $r['collation'], 'comment' => (string)$r['comment']);
            }
        } elseif ($this->driver === 'sqlite') {
            $rows = $this->all('PRAGMA table_info(' . $qt . ')');
            $npk = 0;
            foreach ($rows as $r) {
                if ((int)$r['pk'] > 0) {
                    $npk++;
                }
            }
            foreach ($rows as $r) {
                $pk = (int)$r['pk'] > 0;
                $auto = $pk && $npk === 1 && strtoupper(trim($r['type'])) === 'INTEGER';
                $out[] = array('name' => $r['name'], 'type' => (string)$r['type'], 'null' => !(int)$r['notnull'],
                    'default' => $r['dflt_value'], 'pk' => $pk, 'pkpos' => (int)$r['pk'], 'auto' => $auto,
                    'hasdef' => ($r['dflt_value'] !== null || $auto), 'extra' => $auto ? 'auto_increment' : '');
            }
        } else {
            $base = "SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type, NOT a.attnotnull AS nullable,
                    pg_get_expr(d.adbin, d.adrelid) AS def, COALESCE(i.indisprimary, false) AS pk%s
                    FROM pg_attribute a
                    LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
                    LEFT JOIN pg_index i ON i.indrelid = a.attrelid AND i.indisprimary AND a.attnum = ANY(i.indkey)
                    WHERE a.attrelid = CAST(? AS regclass) AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum";
            try {
                $rows = $this->all(sprintf($base, ", a.attidentity AS ident"), array($qt));
            } catch (Exception $e) {
                $rows = $this->all(sprintf($base, ", '' AS ident"), array($qt));
            }
            foreach ($rows as $r) {
                $ident = isset($r['ident']) && $r['ident'] !== '' && $r['ident'] !== null;
                $auto = $ident || (strpos((string)$r['def'], 'nextval(') === 0);
                $out[] = array('name' => $r['name'], 'type' => $r['type'], 'null' => truthy($r['nullable']),
                    'default' => $r['def'], 'pk' => truthy($r['pk']), 'auto' => $auto,
                    'hasdef' => ($r['def'] !== null || $auto), 'extra' => $auto ? 'auto_increment' : '');
            }
        }
        foreach ($out as $k => $c) {
            if (!array_key_exists('collation', $out[$k])) {
                $out[$k]['collation'] = null;
            }
            if (!isset($out[$k]['comment'])) {
                $out[$k]['comment'] = '';
            }
            if (!isset($out[$k]['pkpos'])) {
                $out[$k]['pkpos'] = 0;
            }
            $out[$k]['binary'] = (bool)preg_match('/blob|binary|bytea/i', $c['type']);
            $out[$k]['text'] = (bool)preg_match('/char|text|clob|json|uuid|xml|name/i', $c['type']);
            $out[$k]['kind'] = '';
            $out[$k]['opts'] = array();
            if ($this->driver === 'mysql' && preg_match('/^(enum|set)\((.*)\)$/is', trim($c['type']), $m)) {
                preg_match_all("/'((?:[^']|'')*)'/", $m[2], $mm);
                $out[$k]['kind'] = strtolower($m[1]);
                $out[$k]['opts'] = array_map('litedb_unquote', $mm[1]);
            }
            $out[$k]['set'] = ($out[$k]['kind'] === 'set');
        }
        return $out;
    }

    /** Labels of a PostgreSQL enum type (empty array when the type is not an enum). */
    public function pgEnum($type)
    {
        if ($this->driver !== 'pgsql' || strpos($type, '[') !== false || strpos($type, '(') !== false
            || preg_match('/^(integer|bigint|smallint|text|character|boolean|numeric|real|double|date|time|timestamp|uuid|json|bytea|interval|bit|money|inet|cidr|macaddr|xml|oid|name|point|line|box|path|polygon|circle)/i', $type)) {
            return array();
        }
        if (!preg_match('/^(?:"?[^".]+"?\.)?"?([^"]+)"?$/', $type, $m)) {
            return array();
        }
        try {
            $o = array();
            foreach ($this->all('SELECT e.enumlabel FROM pg_enum e JOIN pg_type t ON t.oid = e.enumtypid WHERE t.typname = ? ORDER BY e.enumsortorder', array($m[1])) as $r) {
                $o[] = $r['enumlabel'];
            }
            return $o;
        } catch (Exception $e) {
            return array();
        }
    }

    public function pgConstraints($t, $type)
    {
        return $this->all('SELECT conname, pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conrelid = CAST(? AS regclass) AND contype = ? ORDER BY conname', array($this->q($t), $type));
    }

    /** Foreign keys: array(name, key, cols[], rtable, rcols[], upd, del). */
    public function foreignKeys($t)
    {
        $out = array();
        if ($this->driver === 'mysql') {
            $sql = "SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS col, k.REFERENCED_TABLE_NAME AS rtable, k.REFERENCED_COLUMN_NAME AS rcol, r.UPDATE_RULE AS upd, r.DELETE_RULE AS del
                    FROM information_schema.KEY_COLUMN_USAGE k
                    JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
                    WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
                    ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION";
            $g = array();
            foreach ($this->all($sql, array($t)) as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $n = $r['name'];
                if (!isset($g[$n])) {
                    $g[$n] = array('name' => $n, 'key' => $n, 'cols' => array(), 'rtable' => $r['rtable'], 'rcols' => array(), 'upd' => $r['upd'], 'del' => $r['del']);
                }
                $g[$n]['cols'][] = $r['col'];
                $g[$n]['rcols'][] = $r['rcol'];
            }
            $out = array_values($g);
        } elseif ($this->driver === 'pgsql') {
            $sql = "SELECT c.conname AS name, CAST(CAST(c.confrelid AS regclass) AS text) AS rtable, c.confupdtype AS upd, c.confdeltype AS del,
                    (SELECT string_agg(a.attname, ',' ORDER BY k.ord) FROM unnest(c.conkey) WITH ORDINALITY AS k(attnum, ord) JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum) AS cols,
                    (SELECT string_agg(a.attname, ',' ORDER BY k.ord) FROM unnest(c.confkey) WITH ORDINALITY AS k(attnum, ord) JOIN pg_attribute a ON a.attrelid = c.confrelid AND a.attnum = k.attnum) AS rcols
                    FROM pg_constraint c WHERE c.contype = 'f' AND c.conrelid = CAST(? AS regclass) ORDER BY c.conname";
            $codes = array('a' => 'NO ACTION', 'r' => 'RESTRICT', 'c' => 'CASCADE', 'n' => 'SET NULL', 'd' => 'SET DEFAULT');
            foreach ($this->all($sql, array($this->q($t))) as $r) {
                $out[] = array('name' => $r['name'], 'key' => $r['name'], 'cols' => explode(',', (string)$r['cols']), 'rtable' => $r['rtable'],
                    'rcols' => explode(',', (string)$r['rcols']), 'upd' => isset($codes[$r['upd']]) ? $codes[$r['upd']] : 'NO ACTION',
                    'del' => isset($codes[$r['del']]) ? $codes[$r['del']] : 'NO ACTION');
            }
        } else {
            $row = $this->all("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", array($t));
            if ($row && $row[0]['sql'] !== null) {
                $p = sqlite_parse_table($row[0]['sql']);
                foreach (sqlite_constraints($p['items']) as $c) {
                    if ($c['kind'] === 'fk') {
                        $out[] = array('name' => $c['name'], 'key' => $c['key'], 'cols' => $c['cols'], 'rtable' => $c['rtable'], 'rcols' => $c['rcols'], 'upd' => $c['upd'], 'del' => $c['del']);
                    }
                }
            }
        }
        return $out;
    }

    /** Check constraints: array(name, expr, key). */
    public function checks($t)
    {
        $out = array();
        if ($this->driver === 'mysql') {
            $sql = "SELECT c.CONSTRAINT_NAME AS name, c.CHECK_CLAUSE AS expr
                    FROM information_schema.TABLE_CONSTRAINTS tc
                    JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                    WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_TYPE = 'CHECK' ORDER BY c.CONSTRAINT_NAME";
            foreach ($this->all($sql, array($t)) as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $out[] = array('name' => $r['name'], 'expr' => $r['expr'], 'key' => $r['name']);
            }
        } elseif ($this->driver === 'pgsql') {
            foreach ($this->pgConstraints($t, 'c') as $r) {
                $out[] = array('name' => $r['conname'], 'expr' => preg_replace('/^CHECK\s*\((.*)\)(\s+NOT VALID)?$/s', '$1', $r['def']), 'key' => $r['conname']);
            }
        } else {
            $row = $this->all("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", array($t));
            if ($row && $row[0]['sql'] !== null) {
                $p = sqlite_parse_table($row[0]['sql']);
                foreach (sqlite_constraints($p['items']) as $c) {
                    if ($c['kind'] === 'check') {
                        $out[] = array('name' => $c['name'], 'expr' => $c['expr'], 'key' => $c['key']);
                    }
                }
            }
        }
        return $out;
    }

    public function pkCols($cols)
    {
        $o = array();
        foreach ($cols as $c) {
            if ($c['pk']) {
                $o[] = $c['name'];
            }
        }
        return $o;
    }

    public function indexes($t)
    {
        $out = array();
        $qt = $this->q($t);
        if ($this->driver === 'mysql') {
            $g = array();
            foreach ($this->all('SHOW INDEX FROM ' . $qt) as $r) {
                $r = array_change_key_case($r, CASE_LOWER);
                $n = $r['key_name'];
                if (!isset($g[$n])) {
                    $g[$n] = array('name' => $n, 'unique' => !(int)$r['non_unique'], 'primary' => $n === 'PRIMARY', 'cols' => array(), 'plain' => true);
                }
                $g[$n]['cols'][] = $r['column_name'];
                if (!in_array(strtoupper((string)$r['index_type']), array('BTREE', 'HASH'), true) || $r['sub_part'] !== null || $r['column_name'] === null) {
                    $g[$n]['plain'] = false;
                }
            }
            foreach ($g as $x) {
                $out[] = array('name' => $x['name'], 'unique' => $x['unique'], 'primary' => $x['primary'], 'columns' => implode(', ', $x['cols']), 'plain' => $x['plain']);
            }
        } elseif ($this->driver === 'sqlite') {
            foreach ($this->all('PRAGMA index_list(' . $qt . ')') as $r) {
                $cs = array();
                foreach ($this->all('PRAGMA index_info(' . $this->q($r['name']) . ')') as $ci) {
                    $cs[] = $ci['name'];
                }
                $out[] = array('name' => $r['name'], 'unique' => (bool)(int)$r['unique'],
                    'primary' => (isset($r['origin']) && $r['origin'] === 'pk') || strpos($r['name'], 'sqlite_autoindex') === 0,
                    'columns' => implode(', ', $cs), 'plain' => (!in_array(null, $cs, true) && empty($r['partial'])));
            }
        } else {
            $sql = "SELECT i.relname AS name, ix.indisunique AS uniq, ix.indisprimary AS prim, pg_get_indexdef(ix.indexrelid) AS def
                    FROM pg_index ix JOIN pg_class i ON i.oid = ix.indexrelid WHERE ix.indrelid = CAST(? AS regclass) ORDER BY i.relname";
            foreach ($this->all($sql, array($qt)) as $r) {
                $cols = preg_match('/\((.*)\)[^()]*$/', $r['def'], $m) ? $m[1] : '';
                $out[] = array('name' => $r['name'], 'unique' => truthy($r['uniq']), 'primary' => truthy($r['prim']),
                    'columns' => $cols, 'def' => $r['def'],
                    'plain' => (stripos($r['def'], ' where ') === false && stripos($r['def'], ' include ') === false && (bool)preg_match('/ USING (btree|hash) /i', $r['def'])));
            }
        }
        return $out;
    }

    /** CREATE statement(s) joined by ";\n" without trailing semicolon. */
    public function createSql($t, $type)
    {
        $qt = $this->q($t);
        if ($this->driver === 'mysql') {
            $r = $this->all('SHOW CREATE TABLE ' . $qt);
            $v = array_values($r[0]);
            return $v[1];
        }
        if ($this->driver === 'sqlite') {
            $parts = array((string)$this->one('SELECT sql FROM sqlite_master WHERE name = ?', array($t)));
            foreach ($this->all("SELECT sql FROM sqlite_master WHERE tbl_name = ? AND type = 'index' AND sql IS NOT NULL", array($t)) as $r) {
                $parts[] = $r['sql'];
            }
            return implode(";\n", $parts);
        }
        if ($type === 'view') {
            return 'CREATE VIEW ' . $qt . ' AS ' . rtrim(trim((string)$this->one('SELECT pg_get_viewdef(CAST(? AS regclass))', array($qt))), ';');
        }
        $cols = $this->columns($t);
        $lines = array();
        foreach ($cols as $c) {
            $l = '  ' . $this->q($c['name']) . ' ' . $c['type'];
            if (!$c['null']) {
                $l .= ' NOT NULL';
            }
            if ($c['default'] !== null && !$c['auto']) {
                $l .= ' DEFAULT ' . $c['default'];
            }
            $lines[] = $l;
        }
        $pk = $this->pkCols($cols);
        if ($pk) {
            $lines[] = '  PRIMARY KEY (' . implode(', ', array_map(array($this, 'q'), $pk)) . ')';
        }
        foreach ($this->pgConstraints($t, 'c') as $r) {
            $lines[] = '  CONSTRAINT ' . $this->q($r['conname']) . ' ' . $r['def'];
        }
        $parts = array('CREATE TABLE ' . $qt . " (\n" . implode(",\n", $lines) . "\n)");
        foreach ($this->indexes($t) as $ix) {
            if (!$ix['primary'] && isset($ix['def'])) {
                $parts[] = $ix['def'];
            }
        }
        return implode(";\n", $parts);
    }
}

// ============================== APP HELPERS ==============================
function need_table($t)
{
    global $TABLES;
    foreach ($TABLES as $x) {
        if ($x['name'] === $t) {
            return $x;
        }
    }
    throw new Exception('Table not found: ' . $t);
}
function tbl_map()
{
    global $TABLES;
    $m = array();
    foreach ($TABLES as $x) {
        $m[$x['name']] = $x;
    }
    return $m;
}
/** SELECT list; BLOB columns are replaced by their byte length so rows stay small. */
function select_list($cols, $rid)
{
    global $DB;
    $pre = '';
    if ($rid) {
        if ($DB->driver === 'sqlite') {
            $pre = 'rowid AS "__litedb_rid"';
        } elseif ($DB->driver === 'pgsql') {
            $pre = 'CAST(ctid AS TEXT) AS "__litedb_rid"';
        }
    }
    $parts = array();
    $bin = false;
    foreach ($cols as $c) {
        $qc = $DB->q($c['name']);
        if ($c['binary']) {
            $bin = true;
            $len = ($DB->driver === 'mysql') ? 'LENGTH(' . $qc . ')' : (($DB->driver === 'sqlite') ? 'LENGTH(CAST(' . $qc . ' AS BLOB))' : 'OCTET_LENGTH(' . $qc . ')');
            $parts[] = $len . ' AS ' . $qc;
        } else {
            $parts[] = $qc;
        }
    }
    $list = $bin ? implode(', ', $parts) : '*';
    return $pre !== '' ? $pre . ', ' . $list : $list;
}
function upload_blob($n)
{
    if (!isset($_FILES['blob']['error']) || !is_array($_FILES['blob']['error']) || !isset($_FILES['blob']['error'][$n])) {
        return null;
    }
    $e = $_FILES['blob']['error'][$n];
    if ($e === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($e !== UPLOAD_ERR_OK) {
        throw new Exception('Upload for column ' . $n . ' failed (error code ' . $e . '; upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . ').');
    }
    $d = file_get_contents($_FILES['blob']['tmp_name'][$n]);
    if ($d === false) {
        throw new Exception('Could not read the uploaded file for column ' . $n . '.');
    }
    return array('blob' => $d);
}
/** Placeholder for a value: binary data goes through UNHEX()/decode() on MySQL and PostgreSQL. */
function ph_for($v)
{
    global $DB;
    if (is_array($v)) {
        if ($DB->driver === 'mysql') {
            return 'UNHEX(?)';
        }
        if ($DB->driver === 'pgsql') {
            return "decode(?, 'hex')";
        }
    }
    return '?';
}
function param_for($v)
{
    global $DB;
    return (is_array($v) && ($DB->driver === 'mysql' || $DB->driver === 'pgsql')) ? bin2hex($v['blob']) : $v;
}
/** Execute with typed binding (NULL, text, and BLOB streams for SQLite). */
function exec_bound($sql, $params)
{
    global $DB;
    $st = $DB->pdo->prepare($sql);
    $i = 1;
    $streams = array();
    foreach ($params as $v) {
        if (is_array($v)) {
            $fp = fopen('php://memory', 'w+');
            fwrite($fp, $v['blob']);
            rewind($fp);
            $streams[] = $fp;
            $st->bindValue($i, $fp, PDO::PARAM_LOB);
        } elseif ($v === null) {
            $st->bindValue($i, null, PDO::PARAM_NULL);
        } else {
            $st->bindValue($i, $v, PDO::PARAM_STR);
        }
        $i++;
    }
    $st->execute();
    foreach ($streams as $fp) {
        fclose($fp);
    }
    return $st;
}

function where_from_key($key, &$params)
{
    global $DB;
    $w = array();
    foreach ($key as $c => $v) {
        if ($c === '__litedb_rid') {
            $w[] = ($DB->driver === 'sqlite') ? 'rowid = ?' : 'ctid = CAST(? AS tid)';
            $params[] = $v;
        } elseif ($v === null) {
            $w[] = $DB->q($c) . ' IS NULL';
        } else {
            $w[] = $DB->q($c) . ' = ?';
            $params[] = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
        }
    }
    if (!$w) {
        throw new Exception('Cannot identify the row.');
    }
    return implode(' AND ', $w);
}
function sql_default($v)
{
    global $DB;
    $v = trim($v);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^(NULL|CURRENT_TIMESTAMP(\(\d*\))?|TRUE|FALSE)$/i', $v) || is_numeric($v)) {
        return ' DEFAULT ' . $v;
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\([^;]*\)$/', $v)) {
        return ' DEFAULT ' . ($DB->driver === 'pgsql' ? $v : '(' . $v . ')');
    }
    return ' DEFAULT ' . $DB->pdo->quote($v);
}
function safe_type($t)
{
    $t = trim($t);
    if ($t === '' || strpos($t, ';') !== false || strpos($t, '--') !== false || strpos($t, '/*') !== false) {
        throw new Exception('Invalid column type: ' . $t);
    }
    return $t;
}
function is_read_sql($s)
{
    $s = preg_replace('/^(\s+|--[^\n]*(\n|$)|#[^\n]*(\n|$)|\/\*.*?\*\/)*/s', '', $s);
    return (bool)preg_match('/^(select|show|describe|desc|explain|with|values|pragma\s+table_info)\b/i', $s);
}

function pick_cols($names, $arr)
{
    $o = array();
    foreach ($arr as $c) {
        if (is_string($c) && in_array($c, $names, true) && !in_array($c, $o, true)) {
            $o[] = $c;
        }
    }
    return $o;
}
/** Primary key column names in key order. */
function pk_names($cols)
{
    $p = array();
    foreach ($cols as $c) {
        if ($c['pk']) {
            $p[] = array(isset($c['pkpos']) ? (int)$c['pkpos'] : 0, $c['name']);
        }
    }
    usort($p, 'litedb_cmp_pk');
    $o = array();
    foreach ($p as $x) {
        $o[] = $x[1];
    }
    return $o;
}
function litedb_cmp_pk($a, $b)
{
    return $a[0] - $b[0];
}
function sqlite_cols($t)
{
    global $DB;
    $o = array();
    foreach ($DB->columns($t) as $c) {
        $o[] = array('name' => $c['name'], 'old' => $c['name'], 'type' => $c['type'], 'null' => $c['null'],
            'defsql' => ($c['default'] !== null) ? ' DEFAULT (' . $c['default'] . ')' : '');
    }
    return $o;
}
/**
 * Rebuild a SQLite table with a new column list (used for DROP/ALTER COLUMN and PRIMARY KEY changes).
 * $cols: array of array(name, old|null, type, null, defsql). $pkcols: new primary key column names.
 * Returns the names of indexes/triggers that could not be recreated.
 */
function sqlite_rebuild($t, $cols, $pkcols)
{
    global $DB;
    $pdo = $DB->pdo;
    $qt = $DB->q($t);
    $map = array();
    foreach ($cols as $c) {
        if ($c['old'] !== null) {
            $map[$c['old']] = $c['name'];
        }
    }
    $info = array();
    foreach ($DB->indexes($t) as $x) {
        $info[$x['name']] = $x;
    }
    $idxSql = $DB->all("SELECT name, sql FROM sqlite_master WHERE tbl_name = ? AND type = 'index' AND sql IS NOT NULL", array($t));
    $trgSql = $DB->all("SELECT name, sql FROM sqlite_master WHERE tbl_name = ? AND type = 'trigger' AND sql IS NOT NULL", array($t));
    $defs = array();
    $inline = false;
    $new = array();
    $old = array();
    foreach ($cols as $c) {
        $d = $DB->q($c['name']);
        if (count($pkcols) === 1 && $pkcols[0] === $c['name'] && strtoupper(trim($c['type'])) === 'INTEGER') {
            $d .= ' INTEGER PRIMARY KEY';
            $inline = true;
        } else {
            $d .= ($c['type'] !== '' ? ' ' . $c['type'] : '') . ($c['null'] ? '' : ' NOT NULL') . $c['defsql'];
        }
        $defs[] = $d;
        if ($c['old'] !== null) {
            $new[] = $DB->q($c['name']);
            $old[] = $DB->q($c['old']);
        }
    }
    if ($pkcols && !$inline) {
        $defs[] = 'PRIMARY KEY (' . implode(', ', array_map(array($DB, 'q'), $pkcols)) . ')';
    }
    $tmp = $DB->q('__litedb_tmp_' . substr(md5($t), 0, 8));
    $skipped = array();
    $fkOn = (int)$DB->one('PRAGMA foreign_keys');
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys=OFF');
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec('CREATE TABLE ' . $tmp . " (\n  " . implode(",\n  ", $defs) . "\n)");
        if ($new) {
            $pdo->exec('INSERT INTO ' . $tmp . ' (' . implode(', ', $new) . ') SELECT ' . implode(', ', $old) . ' FROM ' . $qt);
        }
        $pdo->exec('DROP TABLE ' . $qt);
        $pdo->exec('ALTER TABLE ' . $tmp . ' RENAME TO ' . $qt);
        foreach ($idxSql as $r) {
            $x = isset($info[$r['name']]) ? $info[$r['name']] : null;
            $cl = $x ? array_map('trim', explode(',', $x['columns'])) : array();
            $skip = false;
            $renamed = false;
            $expr = false;
            $mapped = array();
            foreach ($cl as $cn) {
                if ($cn === '') {
                    $expr = true;
                    continue;
                }
                if (!isset($map[$cn])) {
                    $skip = true;
                    break;
                }
                if ($map[$cn] !== $cn) {
                    $renamed = true;
                }
                $mapped[] = $DB->q($map[$cn]);
            }
            if ($skip) {
                $skipped[] = $r['name'];
                continue;
            }
            $sql = ($renamed && !$expr) ? 'CREATE ' . ($x['unique'] ? 'UNIQUE ' : '') . 'INDEX ' . $DB->q($r['name']) . ' ON ' . $qt . ' (' . implode(', ', $mapped) . ')' : $r['sql'];
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                $skipped[] = $r['name'];
            }
        }
        foreach ($trgSql as $r) {
            try {
                $pdo->exec($r['sql']);
            } catch (Exception $e) {
                $skipped[] = $r['name'];
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($fkOn) {
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
        throw $e;
    }
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $skipped;
}
function sqlite_drop_column($t, $col)
{
    global $DB;
    $all = sqlite_cols($t);
    $keep = array();
    foreach ($all as $c) {
        if ($c['name'] !== $col) {
            $keep[] = $c;
        }
    }
    if (count($keep) === count($all)) {
        throw new Exception('Column not found.');
    }
    if (!$keep) {
        throw new Exception('Cannot drop the only column of a table.');
    }
    $pk = array();
    foreach (pk_names($DB->columns($t)) as $n) {
        if ($n !== $col) {
            $pk[] = $n;
        }
    }
    return sqlite_rebuild($t, $keep, $pk);
}
define('LITEDB_NM', '"(?:[^"]|"")+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_$]*');

function sql_unq($s)
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if ($s[0] === '"') {
        return str_replace('""', '"', substr($s, 1, -1));
    }
    if ($s[0] === '`' || $s[0] === '[') {
        return substr($s, 1, -1);
    }
    return $s;
}
function sqlite_list($s)
{
    $o = array();
    foreach (explode(',', $s) as $x) {
        $x = sql_unq($x);
        if ($x !== '') {
            $o[] = $x;
        }
    }
    return $o;
}
function sql_match_paren($s, $open)
{
    $n = strlen($s);
    $d = 0;
    for ($i = $open; $i < $n; $i++) {
        $c = $s[$i];
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $n) {
                if ($s[$j] === $c) {
                    if ($j + 1 < $n && $s[$j + 1] === $c) {
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $j++;
            }
            $i = $j;
            continue;
        }
        if ($c === '(') {
            $d++;
        } elseif ($c === ')') {
            $d--;
            if ($d === 0) {
                return $i;
            }
        }
    }
    return -1;
}
/** Split a CREATE TABLE statement into head "CREATE TABLE x (", top-level items and tail ")...". */
function sqlite_parse_table($sql)
{
    $n = strlen($sql);
    $start = strpos($sql, '(');
    if ($start === false) {
        throw new Exception('Cannot parse the table definition.');
    }
    $depth = 0;
    $items = array();
    $cur = '';
    $end = -1;
    for ($i = $start + 1; $i < $n; $i++) {
        $c = $sql[$i];
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $n) {
                if ($sql[$j] === $c) {
                    if ($j + 1 < $n && $sql[$j + 1] === $c) {
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $j++;
            }
            $cur .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === '[') {
            $j = strpos($sql, ']', $i);
            $j = ($j === false) ? $n - 1 : $j;
            $cur .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === '-' && $i + 1 < $n && $sql[$i + 1] === '-') {
            $j = strpos($sql, "\n", $i);
            $i = ($j === false) ? $n : $j;
            $cur .= ' ';
            continue;
        }
        if ($c === '/' && $i + 1 < $n && $sql[$i + 1] === '*') {
            $j = strpos($sql, '*/', $i + 2);
            $i = ($j === false) ? $n : $j + 1;
            $cur .= ' ';
            continue;
        }
        if ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            if ($depth === 0) {
                $end = $i;
                break;
            }
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            $items[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if ($end < 0) {
        throw new Exception('Cannot parse the table definition.');
    }
    if (trim($cur) !== '') {
        $items[] = trim($cur);
    }
    return array('head' => substr($sql, 0, $start + 1), 'items' => $items, 'tail' => substr($sql, $end));
}
function sqlite_parse_ref($s)
{
    $NM = LITEDB_NM;
    $r = array('rtable' => '', 'rcols' => array(), 'upd' => 'NO ACTION', 'del' => 'NO ACTION');
    if (preg_match('/^REFERENCES\s+(?:(?:' . $NM . ')\s*\.\s*)?(' . $NM . ')\s*(?:\(([^)]*)\))?(.*)$/is', $s, $m)) {
        $r['rtable'] = sql_unq($m[1]);
        $r['rcols'] = isset($m[2]) ? sqlite_list($m[2]) : array();
        $rest = isset($m[3]) ? $m[3] : '';
        foreach (array('upd' => 'UPDATE', 'del' => 'DELETE') as $k => $w) {
            if (preg_match('/ON\s+' . $w . '\s+(SET\s+NULL|SET\s+DEFAULT|CASCADE|RESTRICT|NO\s+ACTION)/i', $rest, $a)) {
                $r[$k] = strtoupper(preg_replace('/\s+/', ' ', $a[1]));
            }
        }
    }
    return $r;
}
/** Find CHECK and FOREIGN KEY constraints (table-level and inline) in the parsed items of a CREATE TABLE. */
function sqlite_constraints($items)
{
    $NM = LITEDB_NM;
    $out = array();
    $refRe = '/(?:CONSTRAINT\s+(' . $NM . ')\s+)?REFERENCES\s+(?:' . $NM . ')(?:\s*\([^)]*\))?(?:\s+ON\s+(?:DELETE|UPDATE)\s+(?:SET\s+NULL|SET\s+DEFAULT|CASCADE|RESTRICT|NO\s+ACTION)|\s+MATCH\s+\w+|\s+(?:NOT\s+)?DEFERRABLE(?:\s+INITIALLY\s+(?:DEFERRED|IMMEDIATE))?)*/is';
    foreach ($items as $i => $it) {
        if (preg_match('/^(?:CONSTRAINT\s+(' . $NM . ')\s+)?(CHECK|FOREIGN\s+KEY|PRIMARY\s+KEY|UNIQUE)\b/is', $it, $m)) {
            $name = (isset($m[1]) && $m[1] !== '') ? sql_unq($m[1]) : '';
            $kw = strtoupper(preg_replace('/\s+/', ' ', $m[2]));
            if ($kw === 'CHECK') {
                $po = strpos($it, '(', strlen($m[0]));
                $pc = ($po === false) ? -1 : sql_match_paren($it, $po);
                if ($pc > 0) {
                    $out[] = array('kind' => 'check', 'name' => $name, 'expr' => trim(substr($it, $po + 1, $pc - $po - 1)), 'item' => $i,
                        'inline' => false, 'off' => 0, 'text' => $it, 'key' => md5('check|' . $i . '|' . $it));
                }
            } elseif ($kw === 'FOREIGN KEY') {
                if (preg_match('/^(?:CONSTRAINT\s+(?:' . $NM . ')\s+)?FOREIGN\s+KEY\s*\(([^)]*)\)\s*(REFERENCES\s+.*)$/is', $it, $fm)) {
                    $ref = sqlite_parse_ref($fm[2]);
                    $out[] = array('kind' => 'fk', 'name' => $name, 'cols' => sqlite_list($fm[1]), 'rtable' => $ref['rtable'], 'rcols' => $ref['rcols'],
                        'upd' => $ref['upd'], 'del' => $ref['del'], 'item' => $i, 'inline' => false, 'off' => 0, 'text' => $it, 'key' => md5('fk|' . $i . '|' . $it));
                }
            }
            continue;
        }
        $col = preg_match('/^(' . $NM . ')/', $it, $cm) ? sql_unq($cm[1]) : '';
        $off = 0;
        while (preg_match('/(?:CONSTRAINT\s+(' . $NM . ')\s+)?CHECK\s*\(/i', $it, $m, PREG_OFFSET_CAPTURE, $off)) {
            $start = $m[0][1];
            $po = $start + strlen($m[0][0]) - 1;
            $pc = sql_match_paren($it, $po);
            if ($pc < 0) {
                break;
            }
            $name = (isset($m[1]) && $m[1][1] >= 0) ? sql_unq($m[1][0]) : '';
            $text = substr($it, $start, $pc - $start + 1);
            $out[] = array('kind' => 'check', 'name' => $name, 'expr' => trim(substr($it, $po + 1, $pc - $po - 1)), 'item' => $i,
                'inline' => true, 'off' => $start, 'text' => $text, 'key' => md5('check|' . $i . '|' . $start . '|' . $text));
            $off = $pc + 1;
        }
        $off = 0;
        while (preg_match($refRe, $it, $m, PREG_OFFSET_CAPTURE, $off)) {
            $start = $m[0][1];
            $text = $m[0][0];
            $name = (isset($m[1]) && $m[1][1] >= 0) ? sql_unq($m[1][0]) : '';
            $rp = stripos($text, 'REFERENCES');
            $ref = sqlite_parse_ref(substr($text, $rp));
            $out[] = array('kind' => 'fk', 'name' => $name, 'cols' => array($col), 'rtable' => $ref['rtable'], 'rcols' => $ref['rcols'],
                'upd' => $ref['upd'], 'del' => $ref['del'], 'item' => $i, 'inline' => true, 'off' => $start, 'text' => $text,
                'key' => md5('fk|' . $i . '|' . $start . '|' . $text));
            $off = $start + max(1, strlen($text));
        }
    }
    return $out;
}
/** Recreate a SQLite table from new CREATE TABLE SQL, copying data, indexes and triggers. */
function sqlite_rebuild_sql($t, $newSql)
{
    global $DB;
    $pdo = $DB->pdo;
    $qt = $DB->q($t);
    $NM = LITEDB_NM;
    $tmp = $DB->q('__litedb_tmp_' . substr(md5($t), 0, 8));
    $count = 0;
    $newSql = preg_replace('/^\s*CREATE\s+(?:TEMP(?:ORARY)?\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:(?:' . $NM . ')\s*\.\s*)?(?:' . $NM . ')\s*\(/is', 'CREATE TABLE ' . str_replace('\\', '\\\\', $tmp) . ' (', $newSql, 1, $count);
    if (!$count) {
        throw new Exception('Cannot parse the table definition.');
    }
    $names = array();
    foreach ($DB->columns($t) as $c) {
        $names[] = $DB->q($c['name']);
    }
    $idxSql = $DB->all("SELECT name, sql FROM sqlite_master WHERE tbl_name = ? AND type = 'index' AND sql IS NOT NULL", array($t));
    $trgSql = $DB->all("SELECT name, sql FROM sqlite_master WHERE tbl_name = ? AND type = 'trigger' AND sql IS NOT NULL", array($t));
    $skipped = array();
    $fkOn = (int)$DB->one('PRAGMA foreign_keys');
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys=OFF');
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec($newSql);
        $pdo->exec('INSERT INTO ' . $tmp . ' (' . implode(', ', $names) . ') SELECT ' . implode(', ', $names) . ' FROM ' . $qt);
        $pdo->exec('DROP TABLE ' . $qt);
        $pdo->exec('ALTER TABLE ' . $tmp . ' RENAME TO ' . $qt);
        foreach (array_merge($idxSql, $trgSql) as $r) {
            try {
                $pdo->exec($r['sql']);
            } catch (Exception $e) {
                $skipped[] = $r['name'];
            }
        }
        $bad = $DB->all('PRAGMA foreign_key_check(' . $qt . ')');
        if ($bad) {
            throw new Exception(count($bad) . ' existing row(s) violate a foreign key. Nothing was changed.');
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($fkOn) {
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
        throw $e;
    }
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $skipped;
}
function sqlite_constraint_alter($t, $kind, $dropKey, $clause)
{
    global $DB;
    $row = $DB->all("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", array($t));
    if (!$row || $row[0]['sql'] === null) {
        throw new Exception('Table definition not found.');
    }
    $p = sqlite_parse_table($row[0]['sql']);
    $items = $p['items'];
    if ($dropKey !== null) {
        $found = false;
        foreach (sqlite_constraints($items) as $c) {
            if ($c['kind'] === $kind && $c['key'] === $dropKey) {
                if ($c['inline']) {
                    $items[$c['item']] = trim(preg_replace('/\s+/', ' ', substr_replace($items[$c['item']], '', $c['off'], strlen($c['text']))));
                } else {
                    unset($items[$c['item']]);
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception('Constraint not found (the table may have changed).');
        }
        $items = array_values($items);
    }
    if ($clause !== null) {
        $items[] = $clause;
    }
    return sqlite_rebuild_sql($t, $p['head'] . "\n  " . implode(",\n  ", $items) . "\n" . $p['tail']);
}

function fk_clause($n)
{
    global $DB;
    $s = ($n['name'] !== '' ? 'CONSTRAINT ' . $DB->q($n['name']) . ' ' : '') . 'FOREIGN KEY (' . implode(', ', array_map(array($DB, 'q'), $n['cols'])) . ') REFERENCES '
        . $DB->q($n['rtable']) . ' (' . implode(', ', array_map(array($DB, 'q'), $n['rcols'])) . ')';
    if ($n['upd'] !== '') {
        $s .= ' ON UPDATE ' . $n['upd'];
    }
    if ($n['del'] !== '') {
        $s .= ' ON DELETE ' . $n['del'];
    }
    return $s;
}
function check_clause($n)
{
    global $DB;
    return ($n['name'] !== '' ? 'CONSTRAINT ' . $DB->q($n['name']) . ' ' : '') . 'CHECK (' . $n['expr'] . ')';
}
function read_fk_post($t)
{
    global $DB;
    $names = array();
    foreach ($DB->columns($t) as $c) {
        $names[] = $c['name'];
    }
    $cols = pick_cols($names, pa('fcols'));
    $rt = pv('rtable');
    $map = tbl_map();
    if (!isset($map[$rt]) || $map[$rt]['type'] !== 'table') {
        throw new Exception('Choose a valid referenced table.');
    }
    $rn = array();
    foreach ($DB->columns($rt) as $c) {
        $rn[] = $c['name'];
    }
    $rcols = pick_cols($rn, pa('rcols'));
    if (!$cols || count($cols) !== count($rcols)) {
        throw new Exception('Select the same number of local and referenced columns.');
    }
    $acts = array('', 'NO ACTION', 'RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT');
    if (!in_array(pv('upd'), $acts, true) || !in_array(pv('del'), $acts, true)) {
        throw new Exception('Invalid referential action.');
    }
    return array('name' => trim(pv('cname')), 'cols' => $cols, 'rtable' => $rt, 'rcols' => $rcols, 'upd' => pv('upd'), 'del' => pv('del'));
}
function read_check_post()
{
    $e = trim(pv('expr'));
    if ($e === '' || strpos($e, ';') !== false) {
        throw new Exception('Enter a check expression (without semicolons).');
    }
    return array('name' => trim(pv('cname')), 'expr' => $e);
}
/** Add, replace or drop a foreign key / check constraint ($kind: fk|check). */
function constraint_alter($t, $kind, $dropKey, $new)
{
    global $DB;
    $qt = $DB->q($t);
    $pdo = $DB->pdo;
    $clause = null;
    if ($new !== null) {
        $clause = ($kind === 'fk') ? fk_clause($new) : check_clause($new);
    }
    if ($DB->driver === 'sqlite') {
        return sqlite_constraint_alter($t, $kind, $dropKey, $clause);
    }
    $parts = array();
    if ($DB->driver === 'mysql') {
        if ($dropKey !== null) {
            $parts[] = ($kind === 'fk' ? 'DROP FOREIGN KEY ' : 'DROP CHECK ') . $DB->q($dropKey);
        }
        if ($clause !== null) {
            $parts[] = 'ADD ' . $clause;
        }
        try {
            $pdo->exec('ALTER TABLE ' . $qt . ' ' . implode(', ', $parts));
        } catch (Exception $e) {
            if ($kind === 'check' && $dropKey !== null) {
                $parts[0] = 'DROP CONSTRAINT ' . $DB->q($dropKey);
                try {
                    $pdo->exec('ALTER TABLE ' . $qt . ' ' . implode(', ', $parts));
                    return array();
                } catch (Exception $e2) {
                }
            }
            throw $e;
        }
        return array();
    }
    if ($dropKey !== null) {
        $parts[] = 'DROP CONSTRAINT ' . $DB->q($dropKey);
    }
    if ($clause !== null) {
        $parts[] = 'ADD ' . $clause;
    }
    $pdo->exec('ALTER TABLE ' . $qt . ' ' . implode(', ', $parts));
    return array();
}

function rebuild_note($skipped)
{
    return $skipped ? ' Could not recreate: ' . implode(', ', $skipped) . '.' : '';
}

function collation_select($name, $sel = '', $label = '(default)')
{
    global $DB;
    $o = '<option value="">' . h($label) . '</option>';
    try {
        foreach ($DB->collations() as $cs => $list) {
            $o .= '<optgroup label="' . h($cs) . '">';
            foreach ($list as $c) {
                $o .= '<option' . (($sel !== '' && $c === $sel) ? ' selected' : '') . '>' . h($c) . '</option>';
            }
            $o .= '</optgroup>';
        }
    } catch (Exception $e) {
    }
    return '<select name="' . h($name) . '" title="Collation">' . $o . '</select>';
}
function engine_select($name, $sel = '', $label = '(default)')
{
    global $DB;
    $o = '<option value="">' . h($label) . '</option>';
    try {
        foreach ($DB->engines() as $e) {
            $o .= '<option' . (($sel !== '' && strcasecmp($e, $sel) === 0) ? ' selected' : '') . '>' . h($e) . '</option>';
        }
    } catch (Exception $e) {
    }
    return '<select name="' . h($name) . '" title="Engine">' . $o . '</select>';
}
/** Character set of a (validated) MySQL collation. */
function collation_charset($coll)
{
    global $DB;
    foreach ($DB->collations() as $cs => $list) {
        if (in_array($coll, $list, true)) {
            return $cs;
        }
    }
    throw new Exception('Unknown collation: ' . $coll);
}

// ============================== POST ACTIONS ==============================
function handle_post()
{
    global $DB, $curDb;
    csrf_check();
    no_ro();
    $do = pv('do');
    $t = gv('t');
    $qt = $DB->q($t);
    $self = url(array('a' => 'table', 'db' => $curDb, 't' => $t));
    switch ($do) {
        case 'create_db':
            $n = trim(pv('name'));
            if ($n === '') {
                throw new Exception('Database name is required.');
            }
            $sql = 'CREATE DATABASE ' . $DB->q($n);
            if ($DB->driver === 'mysql' && pv('collation') !== '') {
                $sql .= ' CHARACTER SET ' . collation_charset(pv('collation')) . ' COLLATE ' . pv('collation');
            }
            $DB->pdo->exec($sql);
            flash('Database created.');
            redirect(url(array('db' => $n)));
            break;

        case 'drop_db':
            $n = pv('name');
            $DB->pdo->exec('DROP DATABASE ' . $DB->q($n));
            flash('Database dropped.');
            redirect('?db=');
            break;

        case 'drop_tables':
        case 'truncate_tables':
            $map = tbl_map();
            $names = pa('tables');
            $cnt = 0;
            if ($DB->driver === 'mysql') {
                $DB->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            }
            foreach ($names as $n) {
                if (!is_string($n) || !isset($map[$n])) {
                    continue;
                }
                $isView = $map[$n]['type'] === 'view';
                if ($do === 'drop_tables') {
                    $DB->pdo->exec('DROP ' . ($isView ? 'VIEW ' : 'TABLE ') . $DB->q($n));
                } elseif (!$isView) {
                    $DB->pdo->exec(($DB->driver === 'sqlite' ? 'DELETE FROM ' : 'TRUNCATE TABLE ') . $DB->q($n));
                } else {
                    continue;
                }
                $cnt++;
            }
            if ($DB->driver === 'mysql') {
                $DB->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
            flash($cnt . ($do === 'drop_tables' ? ' table(s) dropped.' : ' table(s) emptied.'));
            redirect(url(array('db' => $curDb)));
            break;

        case 'create_table':
            $name = trim(pv('name'));
            if ($name === '') {
                throw new Exception('Table name is required.');
            }
            $defs = array();
            $pks = array();
            $inlinePk = false;
            $cs = pa('c');
            foreach ($cs as $c) {
                if (!is_array($c) || trim(isset($c['name']) ? $c['name'] : '') === '') {
                    continue;
                }
                $cn = trim($c['name']);
                $ty = safe_type(isset($c['type']) && $c['type'] !== '' ? $c['type'] : 'INT');
                $ai = !empty($c['ai']);
                $pk = !empty($c['pk']);
                $nl = !empty($c['null']);
                $d = $DB->q($cn);
                if ($ai && $DB->driver === 'sqlite') {
                    $d .= ' INTEGER PRIMARY KEY AUTOINCREMENT';
                    $inlinePk = true;
                } else {
                    if ($ai && $DB->driver === 'pgsql') {
                        $ty = (stripos($ty, 'big') !== false) ? 'BIGSERIAL' : 'SERIAL';
                    }
                    $d .= ' ' . $ty . ($nl && !$pk && !$ai ? ' NULL' : ' NOT NULL');
                    if ($ai && $DB->driver === 'mysql') {
                        $d .= ' AUTO_INCREMENT';
                    }
                    if (isset($c['def']) && !$ai) {
                        $d .= sql_default($c['def']);
                    }
                    if ($pk || $ai) {
                        $pks[] = $DB->q($cn);
                    }
                }
                $defs[] = $d;
            }
            if (!$defs) {
                throw new Exception('Add at least one column.');
            }
            if ($pks && !$inlinePk) {
                $defs[] = 'PRIMARY KEY (' . implode(', ', $pks) . ')';
            }
            $opt = '';
            if ($DB->driver === 'mysql') {
                if (pv('engine') !== '') {
                    if (!in_array(pv('engine'), $DB->engines(), true)) {
                        throw new Exception('Unknown storage engine: ' . pv('engine'));
                    }
                    $opt .= ' ENGINE=' . pv('engine');
                }
                if (pv('collation') !== '') {
                    $opt .= ' DEFAULT CHARSET=' . collation_charset(pv('collation')) . ' COLLATE=' . pv('collation');
                }
            }
            $DB->pdo->exec('CREATE TABLE ' . $DB->q($name) . " (\n  " . implode(",\n  ", $defs) . "\n)" . $opt);
            flash('Table created.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $name)));
            break;

        case 'save_row':
            need_table($t);
            $cols = $DB->columns($t);
            $f = pa('f');
            $nulls = pa('null');
            $edit = pv('k') !== '';
            $set = array();
            foreach ($cols as $c) {
                $n = $c['name'];
                if ($c['binary']) {
                    $up = upload_blob($n);
                    if (isset($nulls[$n])) {
                        $set[$n] = null;
                    } elseif ($up !== null) {
                        $set[$n] = $up;
                    } elseif (!$edit && !$c['hasdef'] && !$c['null']) {
                        $set[$n] = array('blob' => '');
                    }
                    continue;
                }
                $v = '';
                if (isset($f[$n])) {
                    if (is_string($f[$n])) {
                        $v = $f[$n];
                    } elseif (is_array($f[$n])) {
                        $parts = array();
                        foreach ($f[$n] as $pp) {
                            if (is_string($pp)) {
                                $parts[] = $pp;
                            }
                        }
                        $v = implode(',', $parts);
                    }
                }
                if (isset($nulls[$n])) {
                    $set[$n] = null;
                } elseif ($edit) {
                    $set[$n] = ($v === '' && $c['null'] && !$c['text'] && !$c['set']) ? null : $v;
                } elseif ($v === '') {
                    if ($c['hasdef']) {
                        continue;
                    }
                    $set[$n] = ($c['null'] && !$c['text'] && !$c['set']) ? null : '';
                } else {
                    $set[$n] = $v;
                }
            }
            if ($edit) {
                $key = rk_dec(pv('k'));
                if (!$key) {
                    throw new Exception('Invalid row key.');
                }
                $p0 = array();
                $orig = $DB->all('SELECT ' . ($set ? implode(', ', array_map(array($DB, 'q'), array_keys($set))) : '1 AS x') . ' FROM ' . $qt . ' WHERE ' . where_from_key($key, $p0) . ' LIMIT 1', $p0);
                if (!$orig) {
                    throw new Exception('Row not found.');
                }
                foreach ($set as $n => $v) {
                    $o = $orig[0][$n];
                    if (!is_array($v) && $v === (($o === null) ? null : val_str($o))) {
                        unset($set[$n]);
                    }
                }
                $params = array();
                $sets = array();
                foreach ($set as $n => $v) {
                    $sets[] = $DB->q($n) . ' = ' . ph_for($v);
                    $params[] = param_for($v);
                }
                if (!$sets) {
                    flash('No changes.');
                    redir_ret($self);
                }
                $w = where_from_key($key, $params);
                exec_bound('UPDATE ' . $qt . ' SET ' . implode(', ', $sets) . ' WHERE ' . $w . ($DB->driver === 'mysql' ? ' LIMIT 1' : ''), $params);
                flash('Row updated.');
            } else {
                if (!$set) {
                    $sql = ($DB->driver === 'mysql') ? 'INSERT INTO ' . $qt . ' () VALUES ()' : 'INSERT INTO ' . $qt . ' DEFAULT VALUES';
                    $DB->pdo->exec($sql);
                } else {
                    $phs = array();
                    $vals = array();
                    foreach ($set as $v) {
                        $phs[] = ph_for($v);
                        $vals[] = param_for($v);
                    }
                    exec_bound('INSERT INTO ' . $qt . ' (' . implode(', ', array_map(array($DB, 'q'), array_keys($set))) . ') VALUES (' . implode(', ', $phs) . ')', $vals);
                }
                flash('Row inserted.');
                if (pv('again') !== '') {
                    redirect(url(array('a' => 'edit', 'db' => $curDb, 't' => $t, 'ret' => pv('ret'))));
                }
            }
            redir_ret($self);
            break;

        case 'delete_row':
            need_table($t);
            $key = rk_dec(pv('k'));
            if (!$key) {
                throw new Exception('Invalid row key.');
            }
            $params = array();
            $w = where_from_key($key, $params);
            $st = $DB->run('DELETE FROM ' . $qt . ' WHERE ' . $w . ($DB->driver === 'mysql' ? ' LIMIT 1' : ''), $params);
            flash($st->rowCount() . ' row(s) deleted.');
            redir_ret($self);
            break;

        case 'add_column':
            need_table($t);
            $cn = trim(pv('cname'));
            if ($cn === '') {
                throw new Exception('Column name is required.');
            }
            $sql = 'ALTER TABLE ' . $qt . ' ADD COLUMN ' . $DB->q($cn) . ' ' . safe_type(pv('ctype', 'TEXT'))
                . (pv('cnull') !== '' ? ' NULL' : ' NOT NULL') . sql_default(pv('cdef'));
            $DB->pdo->exec($sql);
            flash('Column added.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'drop_column':
            need_table($t);
            if ($DB->driver === 'sqlite' && version_compare($DB->version, '3.35.0', '<')) {
                $sk = sqlite_drop_column($t, pv('name'));
                flash('Column dropped (table rebuilt because SQLite ' . $DB->version . ' lacks DROP COLUMN; check constraints and foreign keys).' . rebuild_note($sk));
            } else {
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' DROP COLUMN ' . $DB->q(pv('name')));
                flash('Column dropped.');
            }
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'alter_column':
            need_table($t);
            $cols = $DB->columns($t);
            $names = array();
            $old = pv('old');
            $cur = null;
            foreach ($cols as $c) {
                $names[] = $c['name'];
                if ($c['name'] === $old) {
                    $cur = $c;
                }
            }
            if (!$cur) {
                throw new Exception('Column not found.');
            }
            $new = trim(pv('name'));
            if ($new === '') {
                throw new Exception('Column name is required.');
            }
            if ($new !== $old && in_array($new, $names, true)) {
                throw new Exception('A column named ' . $new . ' already exists.');
            }
            $type = safe_type(pv('type'));
            $nullable = pv('null') !== '';
            $def = trim(pv('def'));
            $curDef = ($cur['default'] === null) ? '' : trim((string)$cur['default']);
            $qn = $DB->q($new);
            $qo = $DB->q($old);
            $pdo = $DB->pdo;
            if ($DB->driver === 'mysql') {
                if (preg_match('/(VIRTUAL|STORED) GENERATED/i', $cur['extra'])) {
                    throw new Exception('Generated columns cannot be edited here.');
                }
                $sql = 'ALTER TABLE ' . $qt . ' CHANGE COLUMN ' . $qo . ' ' . $qn . ' ' . $type;
                if (pv('collation') !== '' && preg_match('/char|text|enum|set/i', $type)) {
                    $sql .= ' CHARACTER SET ' . collation_charset(pv('collation')) . ' COLLATE ' . pv('collation');
                }
                $sql .= $nullable ? ' NULL' : ' NOT NULL';
                $sql .= ($def === '' && $cur['default'] === '' && preg_match('/char|text/i', $type)) ? " DEFAULT ''" : sql_default($def);
                if (pv('ai') !== '') {
                    $sql .= ' AUTO_INCREMENT';
                }
                if (preg_match('/on update (current_timestamp(?:\(\d*\))?)/i', $cur['extra'], $m)) {
                    $sql .= ' ON UPDATE ' . $m[1];
                }
                if (pv('comment') !== '') {
                    $sql .= ' COMMENT ' . $pdo->quote(pv('comment'));
                }
                $after = pv('after');
                if ($after === '__first') {
                    $sql .= ' FIRST';
                } elseif ($after !== '' && $after !== $old && in_array($after, $names, true)) {
                    $sql .= ' AFTER ' . $DB->q($after);
                }
                $pdo->exec($sql);
                flash('Column updated.');
            } elseif ($DB->driver === 'pgsql') {
                $pdo->beginTransaction();
                try {
                    if ($new !== $old) {
                        $pdo->exec('ALTER TABLE ' . $qt . ' RENAME COLUMN ' . $qo . ' TO ' . $qn);
                    }
                    if (strtolower($type) !== strtolower(trim($cur['type']))) {
                        $pdo->exec('ALTER TABLE ' . $qt . ' ALTER COLUMN ' . $qn . ' TYPE ' . $type . ' USING CAST(' . $qn . ' AS ' . $type . ')');
                    }
                    if ($nullable !== $cur['null']) {
                        $pdo->exec('ALTER TABLE ' . $qt . ' ALTER COLUMN ' . $qn . ($nullable ? ' DROP' : ' SET') . ' NOT NULL');
                    }
                    if ($def !== $curDef) {
                        $pdo->exec('ALTER TABLE ' . $qt . ' ALTER COLUMN ' . $qn . ($def === '' ? ' DROP DEFAULT' : ' SET' . sql_default($def)));
                    }
                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                flash('Column updated.');
            } else {
                $same = (strtolower($type) === strtolower(trim($cur['type'])) && $nullable === $cur['null'] && $def === $curDef);
                if ($same && $new === $old) {
                    flash('No changes.');
                } elseif ($same && version_compare($DB->version, '3.25.0', '>=')) {
                    $pdo->exec('ALTER TABLE ' . $qt . ' RENAME COLUMN ' . $qo . ' TO ' . $qn);
                    flash('Column renamed.');
                } else {
                    $nc = sqlite_cols($t);
                    foreach ($nc as $k => $c) {
                        if ($c['old'] === $old) {
                            $nc[$k]['name'] = $new;
                            $nc[$k]['type'] = $type;
                            $nc[$k]['null'] = $nullable;
                            if ($def !== $curDef) {
                                $nc[$k]['defsql'] = sql_default($def);
                            }
                        }
                    }
                    $pk = array();
                    foreach (pk_names($cols) as $n) {
                        $pk[] = ($n === $old) ? $new : $n;
                    }
                    $sk = sqlite_rebuild($t, $nc, $pk);
                    flash('Column updated (table rebuilt; check constraints and foreign keys are not preserved).' . rebuild_note($sk));
                }
            }
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'rename_table':
            $info = need_table($t);
            $nn = trim(pv('newname'));
            if ($nn === '') {
                throw new Exception('New table name is required.');
            }
            if ($nn === $t) {
                flash('No changes.');
                redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            }
            $DB->pdo->exec('ALTER TABLE ' . $qt . ' RENAME TO ' . $DB->q($nn));
            flash('Table renamed.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $nn)));
            break;

        case 'table_options':
            $info = need_table($t);
            if ($DB->driver !== 'mysql') {
                throw new Exception('Table options are only available for MySQL/MariaDB.');
            }
            $did = array();
            $eng = pv('engine');
            if ($eng !== '' && strcasecmp($eng, $info['engine']) !== 0) {
                if (!in_array($eng, $DB->engines(), true)) {
                    throw new Exception('Unknown storage engine: ' . $eng);
                }
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' ENGINE=' . $eng);
                $did[] = 'engine';
            }
            $col = pv('collation');
            if ($col !== '' && $col !== $info['collation']) {
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' CONVERT TO CHARACTER SET ' . collation_charset($col) . ' COLLATE ' . $col);
                $did[] = 'collation';
            }
            flash($did ? 'Table updated: ' . implode(', ', $did) . '.' : 'No changes.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'set_pk':
            need_table($t);
            $cols = $DB->columns($t);
            $names = array();
            foreach ($cols as $c) {
                $names[] = $c['name'];
            }
            $pk = pick_cols($names, pa('pkcols'));
            $curpk = pk_names($cols);
            if ($pk === $curpk) {
                flash('No changes.');
                redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            }
            $cl = implode(', ', array_map(array($DB, 'q'), $pk));
            if ($DB->driver === 'mysql') {
                $parts = array();
                if ($curpk) {
                    $parts[] = 'DROP PRIMARY KEY';
                }
                if ($pk) {
                    $parts[] = 'ADD PRIMARY KEY (' . $cl . ')';
                }
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' ' . implode(', ', $parts));
            } elseif ($DB->driver === 'pgsql') {
                $parts = array();
                if ($curpk) {
                    foreach ($DB->indexes($t) as $x) {
                        if ($x['primary']) {
                            $parts[] = 'DROP CONSTRAINT ' . $DB->q($x['name']);
                        }
                    }
                }
                if ($pk) {
                    $parts[] = 'ADD PRIMARY KEY (' . $cl . ')';
                }
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' ' . implode(', ', $parts));
            } else {
                $sk = sqlite_rebuild($t, sqlite_cols($t), $pk);
                flash('Primary key updated (table rebuilt).' . rebuild_note($sk));
                redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            }
            flash($pk ? 'Primary key updated.' : 'Primary key removed.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'edit_index':
            need_table($t);
            $names = array();
            foreach ($DB->columns($t) as $c) {
                $names[] = $c['name'];
            }
            $cs = pick_cols($names, pa('icols'));
            if (!$cs) {
                throw new Exception('Select at least one column.');
            }
            $old = pv('old');
            $in = trim(pv('iname'));
            if ($in === '') {
                $in = 'idx_' . $t . '_' . implode('_', $cs);
            }
            $cl = implode(', ', array_map(array($DB, 'q'), $cs));
            $u = (pv('iunique') !== '') ? 'UNIQUE ' : '';
            if ($DB->driver === 'mysql') {
                $DB->pdo->exec('ALTER TABLE ' . $qt . ' DROP INDEX ' . $DB->q($old) . ', ADD ' . $u . 'INDEX ' . $DB->q($in) . ' (' . $cl . ')');
            } else {
                $pdo = $DB->pdo;
                $pdo->beginTransaction();
                try {
                    $pdo->exec('DROP INDEX ' . $DB->q($old));
                    $pdo->exec('CREATE ' . $u . 'INDEX ' . $DB->q($in) . ' ON ' . $qt . ' (' . $cl . ')');
                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            flash('Index updated.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'add_fk':
        case 'edit_fk':
        case 'drop_fk':
        case 'add_check':
        case 'edit_check':
        case 'drop_check':
            need_table($t);
            $kind = (strpos($do, 'fk') !== false) ? 'fk' : 'check';
            $op = substr($do, 0, strpos($do, '_'));
            $key = ($op === 'add') ? null : pv('old');
            $new = null;
            if ($op !== 'drop') {
                $new = ($kind === 'fk') ? read_fk_post($t) : read_check_post();
            }
            $sk = constraint_alter($t, $kind, $key, $new);
            $what = ($kind === 'fk') ? 'Foreign key' : 'Check constraint';
            flash($what . ($op === 'add' ? ' added.' : ($op === 'edit' ? ' updated.' : ' dropped.')) . rebuild_note(is_array($sk) ? $sk : array()));
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'add_index':
            need_table($t);
            $cs = array();
            foreach (pa('icols') as $c) {
                if (is_string($c) && $c !== '') {
                    $cs[] = $c;
                }
            }
            if (!$cs) {
                throw new Exception('Select at least one column.');
            }
            $in = trim(pv('iname'));
            if ($in === '') {
                $in = 'idx_' . $t . '_' . implode('_', $cs);
            }
            $DB->pdo->exec('CREATE ' . (pv('iunique') !== '' ? 'UNIQUE ' : '') . 'INDEX ' . $DB->q($in) . ' ON ' . $qt . ' (' . implode(', ', array_map(array($DB, 'q'), $cs)) . ')');
            flash('Index created.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;

        case 'drop_index':
            need_table($t);
            $in = pv('name');
            $DB->pdo->exec($DB->driver === 'mysql' ? 'DROP INDEX ' . $DB->q($in) . ' ON ' . $qt : 'DROP INDEX ' . $DB->q($in));
            flash('Index dropped.');
            redirect(url(array('a' => 'struct', 'db' => $curDb, 't' => $t)));
            break;
    }
    throw new Exception('Unknown action.');
}

// ============================== EXPORT ==============================
function do_export()
{
    global $DB, $curDb;
    csrf_check();
    $map = tbl_map();
    $sel = array();
    foreach (pa('tables') as $n) {
        if (is_string($n) && isset($map[$n])) {
            $sel[] = $map[$n];
        }
    }
    if (!$sel) {
        throw new Exception('Select at least one table.');
    }
    @set_time_limit(0);
    $fmt = pv('format') === 'csv' ? 'csv' : 'sql';
    $base = preg_replace('/[^A-Za-z0-9_.-]+/', '_', ($DB->driver === 'sqlite' ? 'export' : $curDb));
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . ($fmt === 'csv' ? '_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $sel[0]['name']) . '.csv' : '.sql') . '"');
    while (ob_get_level()) {
        ob_end_clean();
    }
    $struct = pv('structure') !== '';
    $data = pv('data') !== '';
    $drop = pv('drop') !== '';
    if ($fmt === 'csv') {
        $t = $sel[0]['name'];
        $cols = $DB->columns($t);
        $names = array();
        foreach ($cols as $c) {
            $names[] = $c['name'];
        }
        echo "\xEF\xBB\xBF" . csv_line($names);
        dump_rows($t, $cols, 'csv');
        exit;
    }
    echo '-- LiteDB ' . LITEDB_VERSION . " dump\n-- " . date('Y-m-d H:i:s') . "\n\n";
    if ($DB->driver === 'mysql') {
        echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    }
    foreach ($sel as $x) {
        $t = $x['name'];
        $isView = $x['type'] === 'view';
        echo '-- ' . ($isView ? 'View ' : 'Table ') . $t . "\n";
        if ($struct) {
            if ($drop) {
                echo 'DROP ' . ($isView ? 'VIEW' : 'TABLE') . ' IF EXISTS ' . $DB->q($t) . ";\n";
            }
            echo rtrim($DB->createSql($t, $x['type']), ';') . ";\n\n";
        }
        if ($data && !$isView) {
            dump_rows($t, $DB->columns($t), 'sql');
            echo "\n";
        }
    }
    if ($struct && $DB->driver === 'pgsql') {
        foreach ($sel as $x) {
            if ($x['type'] === 'table') {
                foreach ($DB->pgConstraints($x['name'], 'f') as $r) {
                    echo 'ALTER TABLE ' . $DB->q($x['name']) . ' ADD CONSTRAINT ' . $DB->q($r['conname']) . ' ' . $r['def'] . ";\n";
                }
            }
        }
    }
    if ($DB->driver === 'mysql') {
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
    }
    exit;
}
function dump_val($v, $c)
{
    global $DB;
    if ($v === null) {
        return 'NULL';
    }
    if (is_resource($v)) {
        $v = stream_get_contents($v);
    }
    if (is_bool($v)) {
        return $v ? 'TRUE' : 'FALSE';
    }
    if (is_int($v) || is_float($v)) {
        return (string)$v;
    }
    $v = (string)$v;
    if ($c['binary']) {
        $hex = bin2hex($v);
        if ($DB->driver === 'mysql') {
            return $hex === '' ? "''" : '0x' . $hex;
        }
        return ($DB->driver === 'sqlite') ? "X'" . $hex . "'" : "decode('" . $hex . "', 'hex')";
    }
    if ($DB->driver !== 'pgsql' && preg_match('/^-?(0|[1-9][0-9]{0,14})$/', $v)) {
        return $v;
    }
    return $DB->pdo->quote($v);
}
function dump_rows($t, $cols, $mode)
{
    global $DB;
    $pk = $DB->pkCols($cols);
    $order = $pk ? ' ORDER BY ' . implode(', ', array_map(array($DB, 'q'), $pk)) : '';
    $qcols = array();
    foreach ($cols as $c) {
        $qcols[] = $DB->q($c['name']);
    }
    $chunk = 500;
    $off = 0;
    do {
        $rows = $DB->all('SELECT * FROM ' . $DB->q($t) . $order . ' LIMIT ' . $chunk . ' OFFSET ' . $off);
        if ($rows) {
            if ($mode === 'csv') {
                foreach ($rows as $r) {
                    echo csv_line(array_values($r));
                }
            } else {
                $vals = array();
                foreach ($rows as $r) {
                    $vs = array();
                    foreach ($cols as $c) {
                        $vs[] = dump_val($r[$c['name']], $c);
                    }
                    $vals[] = '(' . implode(', ', $vs) . ')';
                }
                echo 'INSERT INTO ' . $DB->q($t) . ' (' . implode(', ', $qcols) . ") VALUES\n" . implode(",\n", $vals) . ";\n";
            }
            flush();
        }
        $off += $chunk;
    } while (count($rows) === $chunk);
}

// ============================== LAYOUT ==============================
function css()
{
    $light = '--bg:#f5f6f8;--panel:#fff;--text:#1c2331;--muted:#68728a;--border:#dfe3ea;--accent:#2f6fed;--accent2:#e8efff;--danger:#d92d20;--okbg:#e6f6ec;--ok:#067647;--errbg:#fdecea;--code:#f0f2f6;--th:#f0f2f6;';
    $dark = '--bg:#10131a;--panel:#181c25;--text:#e4e8f0;--muted:#8d97ad;--border:#2a3140;--accent:#6b9bff;--accent2:#1f2a44;--danger:#ff6b5e;--okbg:#12301f;--ok:#5fd28f;--errbg:#3a1a17;--code:#212734;--th:#212734;';
    $s = ':root{' . $light . '}@media(prefers-color-scheme:dark){:root{' . $dark . '}}:root[data-theme=light]{' . $light . '}:root[data-theme=dark]{' . $dark . '}';
    $s .= <<<'CSS'
*{box-sizing:border-box}html,body{margin:0}body{font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text)}
a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
.top{display:flex;align-items:center;gap:14px;padding:8px 16px;background:var(--panel);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:5}
.brand{font-weight:700;font-size:17px;color:var(--text)}.brand b{color:var(--accent)}.crumb{color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wrap{display:flex;min-height:calc(100vh - 47px)}aside{width:250px;flex:none;padding:12px;border-right:1px solid var(--border);background:var(--panel)}
aside select,aside input{width:100%;margin-bottom:8px}aside .links a{display:inline-block;margin:0 8px 8px 0}
.tl{list-style:none;margin:0;padding:0;max-height:calc(100vh - 230px);overflow:auto}.tl li a{display:block;padding:3px 8px;border-radius:6px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tl li a:hover,.tl li a.on{background:var(--accent2);text-decoration:none}.tl .v{font-style:italic}
main{flex:1;min-width:0;padding:16px 20px}h2{margin:0 0 12px;font-size:19px}h3{margin:20px 0 8px;font-size:15px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px}
.tabs{display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap}.tabs a{padding:5px 12px;border-radius:7px;color:var(--text)}.tabs a.on{background:var(--accent);color:#fff}.tabs a:hover{text-decoration:none;background:var(--accent2)}.tabs a.on:hover{background:var(--accent)}
.scroll{overflow:auto;border:1px solid var(--border);border-radius:10px;background:var(--panel);margin-bottom:12px}
table{border-collapse:collapse;width:100%}th,td{padding:5px 10px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top;white-space:nowrap;max-width:420px;overflow:hidden;text-overflow:ellipsis}
th{background:var(--th);font-weight:600;position:sticky;top:0}tr:hover td{background:var(--accent2)}td.null{color:var(--muted);font-style:italic}td.bin{color:var(--muted)}td.act{width:1%}
input[type=text],input[type=password],input[type=number],select,textarea{font:inherit;padding:6px 8px;border:1px solid var(--border);border-radius:7px;background:var(--panel);color:var(--text);max-width:100%}
textarea{width:100%;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px}input:focus,select:focus,textarea:focus{outline:2px solid var(--accent);outline-offset:-1px}
button,.btn{font:inherit;padding:6px 14px;border-radius:7px;border:1px solid var(--border);background:var(--panel);color:var(--text);cursor:pointer;display:inline-block}
button.p,.btn.p{background:var(--accent);border-color:var(--accent);color:#fff}button.d{color:var(--danger)}button.s,.btn.s{padding:1px 8px;font-size:12px}button:hover,.btn:hover{filter:brightness(.96);text-decoration:none}
.msg{padding:9px 14px;border-radius:8px;margin-bottom:12px}.msg.ok{background:var(--okbg);color:var(--ok)}.msg.err{background:var(--errbg);color:var(--danger);white-space:pre-wrap}
pre,code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}pre{background:var(--code);padding:10px 12px;border-radius:8px;overflow:auto;margin:0}code{background:var(--code);padding:1px 5px;border-radius:4px}
.muted{color:var(--muted)}.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}.pg a,.pg span{padding:3px 9px;border:1px solid var(--border);border-radius:6px;margin-right:3px;display:inline-block}.pg span{background:var(--accent);color:#fff;border-color:var(--accent)}
.form td:first-child{width:1%;font-weight:600}.form td{white-space:normal}.form textarea{min-height:60px}.chk{display:inline-block;margin:0 14px 4px 0;white-space:nowrap}.nb{font-size:12px;color:var(--muted);white-space:nowrap}
.login{max-width:380px;margin:8vh auto;padding:0 16px}.login label{display:block;margin:10px 0 3px;font-weight:600}.login input,.login select{width:100%}.login button{width:100%;margin-top:16px}
.sqlite-only{display:none}form[data-d=sqlite] .sqlite-only{display:inline}form[data-d=sqlite] .net-only{display:none}
.hist a{display:block;padding:2px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:ui-monospace,monospace;font-size:12px}
@media(max-width:800px){.wrap{flex-direction:column}aside{width:auto;border-right:0;border-bottom:1px solid var(--border)}.tl{max-height:180px}}
CSS;
    return $s;
}

function layout_start($title)
{
    global $DB, $curDb, $auth, $STARTED, $CONFIG_ERRORS;
    $STARTED = true;
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'self'");
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">';
    echo '<title>' . h($title) . ' - LiteDB</title><style>' . css() . '</style>';
    echo '<script>(function(){var r=document.documentElement;try{var t=localStorage.getItem("litedb-theme");if(t)r.setAttribute("data-theme",t)}catch(e){}'
        . 'window.toggleTheme=function(){var c=r.getAttribute("data-theme")||(window.matchMedia&&matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light");var n=c==="dark"?"light":"dark";r.setAttribute("data-theme",n);try{localStorage.setItem("litedb-theme",n)}catch(e){}}})();</script>';
    echo '</head><body>';
    if ($CONFIG_ERRORS) {
        echo '<div class="msg err" style="margin:12px 16px"><b>Configuration problem' . (count($CONFIG_ERRORS) > 1 ? 's' : '') . ' in litedb.php:</b>';
        foreach ($CONFIG_ERRORS as $ce) {
            echo "\n&bull; " . h($ce);
        }
        echo '</div>';
    }
    if (!$auth) {
        return;
    }
    $drv = array('mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite');
    echo '<div class="top"><a class="brand" href="' . h(($DB->driver === 'sqlite' ? url(array('db' => 'main')) : '?db=')) . '">Lite<b>DB</b></a><div class="crumb">';
    $srv = ($DB->driver === 'sqlite') ? basename($auth['path']) : $auth['user'] . '@' . $auth['server'];
    echo h($drv[$DB->driver]) . ' ' . h($DB->version) . ' &middot; ' . h($srv);
    if ($DB->driver !== 'sqlite' && $curDb !== '') {
        echo ' &rsaquo; <a href="' . h(url(array('db' => $curDb))) . '">' . h($curDb) . '</a>';
    }
    if (gv('t') !== '') {
        echo ' &rsaquo; ' . h(gv('t'));
    }
    echo '</div><button class="s" onclick="toggleTheme()" title="Toggle theme" type="button">&#9680; Theme</button>';
    echo '<a href="' . h(url(array('a' => 'logout', '_t' => $_SESSION['csrf']))) . '">Log out</a></div>';
    echo '<div class="wrap">';
    sidebar();
    echo '<main>';
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) {
            echo '<div class="msg ' . ($f[0] === 'ok' ? 'ok' : 'err') . '">' . h($f[1]) . '</div>';
        }
        unset($_SESSION['flash']);
    }
}

function layout_end()
{
    global $auth;
    if ($auth) {
        echo '</main></div>';
    }
    echo '<script>' . js() . '</script></body></html>';
}

function js()
{
    return <<<'JS'
(function(){
var $=function(s,c){return(c||document).querySelector(s)},$$=function(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))};
var f=$('#tf');if(f)f.oninput=function(){var v=f.value.toLowerCase();$$('.tl li').forEach(function(li){li.style.display=li.textContent.toLowerCase().indexOf(v)<0?'none':''})};
$$('[data-all]').forEach(function(a){a.onclick=function(){$$('input[name="tables[]"]').forEach(function(c){c.checked=a.checked})}});
$$('.fv').forEach(function(w){var c=$('.nb input',w);if(c)$$('.in',w).forEach(function(i){['input','change'].forEach(function(ev){i.addEventListener(ev,function(){c.checked=false})})})});
var s=$('#sql');if(s)s.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.keyCode===13){e.preventDefault();s.form.submit()}});
$$('.hist a').forEach(function(a){a.onclick=function(e){e.preventDefault();if(s){s.value=a.getAttribute('data-sql');s.focus()}}});
var ad=$('#addcol');if(ad){var tpl=$('#cols tr').cloneNode(true),n=$$('#cols tr').length;ad.onclick=function(){var tr=tpl.cloneNode(true);$$('input',tr).forEach(function(i){i.name=i.name.replace(/c\[\d+\]/,'c['+n+']');if(i.type==='checkbox')i.checked=false;else i.value=i.getAttribute('data-def')||''});n++;$('#cols').appendChild(tr)};$('#cols').addEventListener('click',function(e){var b=e.target;if(b&&b.className&&String(b.className).indexOf('rm')>=0){var tr=b;while(tr&&tr.tagName!=='TR')tr=tr.parentNode;if(tr)tr.parentNode.removeChild(tr)}})}
var ds=$('#drv');if(ds)ds.onchange=function(){ds.form.setAttribute('data-d',ds.value)};
})();
JS;
}

function sidebar()
{
    global $DB, $curDb, $TABLES;
    echo '<aside>';
    if ($DB->driver !== 'sqlite') {
        $dbs = array();
        try {
            $dbs = $DB->databases();
        } catch (Exception $e) {
        }
        echo '<select onchange="location=\'?db=\'+encodeURIComponent(this.value)"><option value="">&mdash; server &mdash;</option>';
        foreach ($dbs as $d) {
            echo '<option' . ($d === $curDb ? ' selected' : '') . '>' . h($d) . '</option>';
        }
        echo '</select>';
    }
    if ($DB->driver === 'pgsql' && $curDb !== '') {
        try {
            $cs = (string)$DB->one('SELECT current_schema()');
            echo '<select title="Schema" onchange="location=\'?db=\'+encodeURIComponent(' . h(json_encode($curDb)) . ')+\'&amp;sc=\'+encodeURIComponent(this.value)">';
            foreach ($DB->schemas() as $x) {
                echo '<option' . ($x === $cs ? ' selected' : '') . '>' . h($x) . '</option>';
            }
            echo '</select>';
        } catch (Exception $e) {
        }
    }
    echo '<div class="links"><a href="' . h(url(array('a' => 'sql', 'db' => $curDb))) . '">SQL command</a>';
    if ($curDb !== '') {
        echo '<a href="' . h(url(array('a' => 'import', 'db' => $curDb))) . '">Import</a>';
        echo '<a href="' . h(url(array('a' => 'export', 'db' => $curDb))) . '">Export</a>';
        echo '<a href="' . h(url(array('a' => 'create', 'db' => $curDb))) . '">New table</a>';
    }
    echo '</div>';
    if ($curDb !== '') {
        echo '<input type="text" id="tf" placeholder="Filter tables..." autocomplete="off"><ul class="tl">';
        foreach ($TABLES as $x) {
            echo '<li><a class="' . ($x['type'] === 'view' ? 'v ' : '') . ($x['name'] === gv('t') ? 'on' : '') . '" href="'
                . h(url(array('a' => 'table', 'db' => $curDb, 't' => $x['name']))) . '" title="' . h($x['name']) . '">' . h($x['name']) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</aside>';
}

function tabs($active)
{
    global $curDb;
    $t = gv('t');
    $info = need_table($t);
    $items = array('table' => 'Browse', 'struct' => 'Structure');
    if ($info['type'] === 'table' && !cfg('readonly')) {
        $items['edit'] = 'Insert';
    }
    $items['export'] = 'Export';
    echo '<h2>' . ($info['type'] === 'view' ? 'View: ' : 'Table: ') . h($t) . '</h2><div class="tabs">';
    foreach ($items as $k => $l) {
        echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . h(url(array('a' => $k, 'db' => $curDb, 't' => $t))) . '">' . $l . '</a>';
    }
    echo '</div>';
}

// ============================== PAGES ==============================
function render_login($err)
{
    global $auth;
    $auth = null;
    layout_start('Login');
    $drivers = array();
    $avail = PDO::getAvailableDrivers();
    $names = array('mysql' => 'MySQL / MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite');
    foreach (cfg('drivers') as $d) {
        if (in_array($d, $avail, true) && isset($names[$d])) {
            $drivers[$d] = $names[$d];
        }
    }
    $cur = pv('driver', (cfg('default_driver') !== '' && isset($drivers[cfg('default_driver')])) ? cfg('default_driver') : ($drivers ? key($drivers) : ''));
    echo '<div class="login"><h2 style="font-size:26px">Lite<span style="color:var(--accent)">DB</span></h2>';
    if ($err !== '') {
        echo '<div class="msg err">' . h($err) . '</div>';
    }
    if (!$drivers) {
        echo '<div class="msg err">No supported PDO drivers (pdo_mysql, pdo_pgsql, pdo_sqlite) are enabled.</div></div>';
        layout_end();
        return;
    }
    echo '<form method="post" data-d="' . h($cur) . '" autocomplete="off">' . csrf_field() . '<input type="hidden" name="do" value="login">';
    echo '<label>System</label><select name="driver" id="drv">';
    foreach ($drivers as $k => $l) {
        echo '<option value="' . $k . '"' . ($k === $cur ? ' selected' : '') . '>' . h($l) . '</option>';
    }
    echo '</select>';
    echo '<div class="net-only"><label>Server</label><input type="text" name="server" value="' . h(pv('server', cfg('default_server'))) . '">';
    echo '<label>Username</label><input type="text" name="user" value="' . h(pv('user', cfg('default_user'))) . '">';
    echo '<label>Password</label><input type="password" name="pass" autocomplete="new-password"' . (cfg('default_pass') !== '' ? ' placeholder="(default from config)"' : '') . '></div>';
    echo '<label><span class="net-only">Database (optional)</span><span class="sqlite-only">SQLite file path</span></label><input type="text" name="db" value="' . h(pv('db', cfg('default_db'))) . '">';
    if (cfg('access_password') !== '') {
        echo '<label>Access password</label><input type="password" name="gate" autocomplete="off">';
    }
    echo '<button class="p" type="submit">Login</button></form><p class="muted" style="text-align:center;margin-top:18px">LiteDB ' . LITEDB_VERSION . ' &middot; PHP ' . h(PHP_VERSION) . '</p></div>';
    layout_end();
}

function page_server()
{
    global $DB;
    layout_start('Server');
    echo '<h2>Databases</h2><div class="scroll"><table><tr><th>Database</th><th class="act"></th></tr>';
    foreach ($DB->databases() as $d) {
        echo '<tr><td><a href="' . h(url(array('db' => $d))) . '">' . h($d) . '</a></td><td class="act">';
        if (!cfg('readonly')) {
            echo '<form method="post" onsubmit="return confirm(\'Drop database ' . h(addslashes($d)) . '?\')">' . csrf_field()
                . '<input type="hidden" name="do" value="drop_db"><input type="hidden" name="name" value="' . h($d) . '"><button class="s d">Drop</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
    if (!cfg('readonly')) {
        echo '<div class="card"><form method="post" class="row" style="margin:0">' . csrf_field() . '<input type="hidden" name="do" value="create_db">'
            . '<input type="text" name="name" placeholder="New database name">' . ($DB->driver === 'mysql' ? collation_select('collation') : '') . '<button class="p">Create database</button></form></div>';
    }
    layout_end();
}

function page_tables()
{
    global $DB, $curDb, $TABLES;
    layout_start($curDb);
    echo '<h2>Tables &amp; views <span class="muted" style="font-size:13px">(' . count($TABLES) . ')</span></h2>';
    echo '<form method="post"><input type="hidden" name="_t" value="' . h($_SESSION['csrf']) . '"><div class="scroll"><table>';
    echo '<tr><th class="act"><input type="checkbox" data-all></th><th>Name</th><th>Type</th><th>Engine</th><th>Rows</th><th>Size</th><th></th></tr>';
    foreach ($TABLES as $x) {
        $n = $x['name'];
        $u = array('db' => $curDb, 't' => $n);
        echo '<tr><td class="act"><input type="checkbox" name="tables[]" value="' . h($n) . '"></td>';
        echo '<td><a href="' . h(url($u + array('a' => 'table'))) . '">' . h($n) . '</a></td><td>' . h($x['type']) . '</td><td>' . h($x['engine']) . '</td>';
        echo '<td>' . ($x['rows'] === '' ? '' : h(is_numeric($x['rows']) ? number_format((float)$x['rows']) : $x['rows'])) . '</td><td>' . ($x['size'] ? hsize($x['size']) : '') . '</td>';
        echo '<td><a href="' . h(url($u + array('a' => 'struct'))) . '">Structure</a>';
        if ($x['type'] === 'table' && !cfg('readonly')) {
            echo ' &middot; <a href="' . h(url($u + array('a' => 'edit'))) . '">Insert</a>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
    if (!cfg('readonly')) {
        echo '<div class="row"><span class="muted">Selected:</span>'
            . '<button class="d" name="do" value="truncate_tables" onclick="return confirm(\'Empty selected tables?\')">Empty</button>'
            . '<button class="d" name="do" value="drop_tables" onclick="return confirm(\'Drop selected tables?\')">Drop</button></div>';
    }
    echo '</form>';
    layout_end();
}

function page_browse()
{
    global $DB, $curDb;
    $t = gv('t');
    $info = need_table($t);
    $cols = $DB->columns($t);
    $names = array();
    foreach ($cols as $c) {
        $names[] = $c['name'];
    }
    $pk = $DB->pkCols($cols);
    $canEdit = ($info['type'] === 'table' && !cfg('readonly'));
    $per = (int)gv('n', (string)cfg('per_page'));
    $per = max(1, min(1000, $per));
    $p = max(1, (int)gv('p', '1'));
    $ord = gv('o');
    $desc = gv('d') === '1';
    $fc = gv('fc');
    $fo = gv('fo', '=');
    $fv = gv('fv');
    $ops = array('=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL');
    $where = '';
    $params = array();
    if ($fc !== '' && in_array($fc, $names, true) && in_array($fo, $ops, true)) {
        $qc = $DB->q($fc);
        if ($fo === 'IS NULL' || $fo === 'IS NOT NULL') {
            $where = ' WHERE ' . $qc . ' ' . $fo;
        } else {
            if ($DB->driver === 'pgsql' && ($fo === 'LIKE' || $fo === 'NOT LIKE')) {
                $qc = 'CAST(' . $qc . ' AS TEXT)';
            }
            $where = ' WHERE ' . $qc . ' ' . $fo . ' ?';
            $params[] = $fv;
        }
    }
    $isTbl = ($info['type'] === 'table');
    $binCols = array();
    foreach ($cols as $c) {
        if ($c['binary']) {
            $binCols[$c['name']] = true;
        }
    }
    $sel = select_list($cols, $isTbl && !$pk);
    $order = '';
    if ($ord !== '' && in_array($ord, $names, true)) {
        $order = ' ORDER BY ' . $DB->q($ord) . ($desc ? ' DESC' : ' ASC');
    } elseif ($pk) {
        $order = ' ORDER BY ' . implode(', ', array_map(array($DB, 'q'), $pk));
    }
    $total = (int)$DB->one('SELECT COUNT(*) FROM ' . $DB->q($t) . $where, $params);
    $pages = max(1, (int)ceil($total / $per));
    $p = min($p, $pages);
    $sql = 'SELECT ' . $sel . ' FROM ' . $DB->q($t) . $where . $order . ' LIMIT ' . $per . ' OFFSET ' . (($p - 1) * $per);
    $t0 = microtime(true);
    $rows = $DB->all($sql, $params);
    $dt = microtime(true) - $t0;

    layout_start($t);
    tabs('table');
    $base = array('a' => 'table', 'db' => $curDb, 't' => $t, 'o' => $ord, 'd' => $desc ? '1' : '', 'fc' => $fc, 'fo' => $fo, 'fv' => $fv, 'n' => ($per != cfg('per_page')) ? $per : '');
    $ret = '?' . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

    echo '<form method="get" class="row"><input type="hidden" name="a" value="table"><input type="hidden" name="db" value="' . h($curDb) . '"><input type="hidden" name="t" value="' . h($t) . '">';
    echo '<select name="fc"><option value="">Search column...</option>';
    foreach ($names as $n) {
        echo '<option' . ($n === $fc ? ' selected' : '') . '>' . h($n) . '</option>';
    }
    echo '</select><select name="fo">';
    foreach ($ops as $o) {
        echo '<option' . ($o === $fo ? ' selected' : '') . '>' . h($o) . '</option>';
    }
    echo '</select><input type="text" name="fv" value="' . h($fv) . '" placeholder="Value"><select name="n">';
    foreach (array(25, 50, 100, 250, 500, 1000) as $x) {
        echo '<option' . ($x == $per ? ' selected' : '') . '>' . $x . '</option>';
    }
    echo '</select><button class="p">Search</button>';
    if ($fc !== '') {
        echo '<a href="' . h(url(array('a' => 'table', 'db' => $curDb, 't' => $t))) . '">Reset</a>';
    }
    echo '</form>';

    echo '<p class="muted">' . number_format($total) . ' row(s) &middot; ' . round($dt * 1000, 1) . ' ms &middot; <code>' . h(cut($sql, 300)) . '</code></p>';
    if ($canEdit) {
        echo '<form id="rf" method="post">' . csrf_field() . '<input type="hidden" name="do" value="delete_row"><input type="hidden" name="ret" value="' . h($ret) . '"></form>';
    }
    echo '<div class="scroll"><table><tr>' . ($canEdit ? '<th></th>' : '');
    foreach ($names as $n) {
        $nd = ($ord === $n && !$desc) ? '1' : '';
        $arrow = ($ord === $n) ? ($desc ? ' &#9660;' : ' &#9650;') : '';
        echo '<th><a href="' . h(url(array_merge($base, array('o' => $n, 'd' => $nd, 'p' => '')))) . '">' . h($n) . '</a>' . $arrow . '</th>';
    }
    echo '</tr>';
    if (!$rows) {
        echo '<tr><td colspan="' . (count($names) + 1) . '" class="muted">No rows.</td></tr>';
    }
    foreach ($rows as $row) {
        foreach ($row as $c => $v) {
            if (is_resource($v)) {
                $row[$c] = stream_get_contents($v);
            }
        }
        $rid = null;
        if (array_key_exists('__litedb_rid', $row)) {
            $rid = $row['__litedb_rid'];
            unset($row['__litedb_rid']);
        }
        echo '<tr>';
        $key = null;
        if ($isTbl) {
            if ($pk) {
                if (!array_intersect($pk, array_keys($binCols))) {
                    $k = array();
                    foreach ($pk as $c) {
                        $k[$c] = $row[$c];
                    }
                    $key = rk_enc($k);
                }
            } elseif ($rid !== null) {
                $key = rk_enc(array('__litedb_rid' => $rid));
            } elseif ($DB->driver === 'mysql' && !$binCols) {
                $key = rk_enc($row);
            }
        }
        if ($canEdit) {
            echo '<td class="act">';
            if ($key !== null) {
                echo '<a href="' . h(url(array('a' => 'edit', 'db' => $curDb, 't' => $t, 'k' => $key, 'ret' => $ret))) . '">Edit</a> '
                    . '<button class="s d" type="submit" form="rf" name="k" value="' . h($key) . '" onclick="return confirm(\'Delete this row?\')">&times;</button>';
            }
            echo '</td>';
        }
        foreach ($names as $n) {
            if (isset($binCols[$n]) && $row[$n] !== null) {
                echo bin_cell($row[$n], $key !== null ? url(array('a' => 'blob', 'db' => $curDb, 't' => $t, 'c' => $n, 'k' => $key)) : '');
            } else {
                echo cell($row[$n]);
            }
        }
        echo '</tr>';
    }
    echo '</table></div>';
    if ($pages > 1) {
        echo '<div class="pg">';
        $from = max(1, $p - 4);
        $to = min($pages, $p + 4);
        if ($p > 1) {
            echo '<a href="' . h(url(array_merge($base, array('p' => $p - 1)))) . '">&laquo; Prev</a>';
        }
        for ($i = $from; $i <= $to; $i++) {
            echo ($i === $p) ? '<span>' . $i . '</span>' : '<a href="' . h(url(array_merge($base, array('p' => $i)))) . '">' . $i . '</a>';
        }
        if ($p < $pages) {
            echo '<a href="' . h(url(array_merge($base, array('p' => $p + 1)))) . '">Next &raquo;</a>';
        }
        echo ' <span style="background:none;color:var(--muted);border:0">of ' . $pages . '</span></div>';
    }
    layout_end();
}

function struct_constraints($t, $self, $canEdit)
{
    global $DB, $TABLES;
    $fks = array();
    $chks = null;
    try {
        $fks = $DB->foreignKeys($t);
    } catch (Exception $e) {
    }
    try {
        $chks = $DB->checks($t);
    } catch (Exception $e) {
    }
    $cols = $DB->columns($t);
    $acts = array('NO ACTION', 'RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT');
    $ef = gv('ef');
    $ek = gv('ek');
    $tbls = array();
    foreach ($TABLES as $x) {
        if ($x['type'] === 'table') {
            $tbls[] = $x['name'];
        }
    }

    // ----- foreign keys -----
    echo '<h3 id="fk">Foreign keys</h3><div class="scroll"><table><tr><th>Name</th><th>Columns</th><th>References</th><th>ON UPDATE</th><th>ON DELETE</th><th></th></tr>';
    if (!$fks) {
        echo '<tr><td colspan="6" class="muted">No foreign keys.</td></tr>';
    }
    foreach ($fks as $f) {
        $rt = preg_replace('/^.*\./', '', trim($f['rtable'], '"'));
        echo '<tr><td>' . ($f['name'] !== '' ? h($f['name']) : '<span class="muted">(unnamed)</span>') . '</td><td>' . h(implode(', ', $f['cols'])) . '</td><td>'
            . h($f['rtable']) . ' (' . ($f['rcols'] ? h(implode(', ', $f['rcols'])) : 'primary key') . ')</td><td>' . h($f['upd']) . '</td><td>' . h($f['del']) . '</td><td class="act" style="white-space:nowrap">';
        if ($canEdit) {
            if (in_array($rt, $tbls, true) && $f['rcols']) {
                echo '<a class="btn s" href="' . h(url($self + array('ef' => $f['key'])) . '#fkform') . '">Edit</a> ';
            }
            echo '<form method="post" style="display:inline" action="' . h(url($self)) . '" onsubmit="return confirm(\'Drop this foreign key?\')">' . csrf_field()
                . '<input type="hidden" name="do" value="drop_fk"><input type="hidden" name="old" value="' . h($f['key']) . '"><button class="s d">Drop</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
    if ($canEdit) {
        $efk = null;
        if ($ef !== '') {
            foreach ($fks as $f) {
                if ($f['key'] === $ef) {
                    $efk = $f;
                }
            }
        }
        $rt = $efk ? preg_replace('/^.*\./', '', trim($efk['rtable'], '"')) : gv('rt');
        echo '<div class="card" id="fkform"><b>' . ($efk ? 'Edit foreign key' : 'Add foreign key') . '</b>';
        if (!$efk) {
            echo '<div class="row" style="margin:8px 0 0"><span>Referenced table</span><select onchange="location=' . h(json_encode(url($self) . '&rt=')) . '+encodeURIComponent(this.value)+\'#fkform\'"><option value="">Choose...</option>';
            foreach ($tbls as $n) {
                echo '<option' . ((string)$n === $rt ? ' selected' : '') . '>' . h($n) . '</option>';
            }
            echo '</select></div>';
        }
        if ($rt !== '' && in_array($rt, $tbls, true)) {
            $rcl = $DB->columns($rt);
            $selL = $efk ? $efk['cols'] : array();
            $selR = $efk ? $efk['rcols'] : pk_names($rcl);
            echo '<form method="post" style="margin-top:8px" action="' . h(url($self)) . '">' . csrf_field() . '<input type="hidden" name="do" value="' . ($efk ? 'edit_fk' : 'add_fk') . '">'
                . '<input type="hidden" name="old" value="' . h($efk ? $efk['key'] : '') . '"><input type="hidden" name="rtable" value="' . h($rt) . '">'
                . '<div class="row"><input type="text" name="cname" value="' . h($efk ? $efk['name'] : '') . '" placeholder="Constraint name (optional)">'
                . '<label>Columns <select name="fcols[]" multiple size="4">';
            foreach ($cols as $c) {
                echo '<option' . (in_array($c['name'], $selL, true) ? ' selected' : '') . '>' . h($c['name']) . '</option>';
            }
            echo '</select></label><label>References ' . h($rt) . ' <select name="rcols[]" multiple size="4">';
            foreach ($rcl as $c) {
                echo '<option' . (in_array($c['name'], $selR, true) ? ' selected' : '') . '>' . h($c['name']) . '</option>';
            }
            echo '</select></label></div><div class="row">';
            foreach (array('upd' => 'ON UPDATE', 'del' => 'ON DELETE') as $k => $lbl) {
                echo '<label>' . $lbl . ' <select name="' . $k . '"><option value="">(default)</option>';
                foreach ($acts as $a) {
                    echo '<option' . (($efk && $efk[$k] === $a) ? ' selected' : '') . '>' . $a . '</option>';
                }
                echo '</select></label>';
            }
            echo '</div><div class="row" style="margin:0"><button class="p">' . ($efk ? 'Save foreign key' : 'Add foreign key') . '</button>'
                . ($efk ? '<a href="' . h(url($self)) . '">Cancel</a>' : '') . '<span class="muted">Local and referenced columns are paired in table order.</span></div></form>';
        }
        echo '</div>';
    }

    // ----- check constraints -----
    echo '<h3 id="chk">Check constraints</h3><div class="scroll"><table><tr><th>Name</th><th>Expression</th><th></th></tr>';
    if (!$chks) {
        echo '<tr><td colspan="3" class="muted">' . ($chks === null ? 'Could not be read on this server (MySQL needs 8.0.16+, MariaDB 10.2+).' : 'No check constraints.') . '</td></tr>';
    }
    foreach ((array)$chks as $c) {
        echo '<tr><td>' . ($c['name'] !== '' ? h($c['name']) : '<span class="muted">(unnamed)</span>') . '</td><td><code>' . h($c['expr']) . '</code></td><td class="act" style="white-space:nowrap">';
        if ($canEdit) {
            echo '<a class="btn s" href="' . h(url($self + array('ek' => $c['key'])) . '#chkform') . '">Edit</a> '
                . '<form method="post" style="display:inline" action="' . h(url($self)) . '" onsubmit="return confirm(\'Drop this check constraint?\')">' . csrf_field()
                . '<input type="hidden" name="do" value="drop_check"><input type="hidden" name="old" value="' . h($c['key']) . '"><button class="s d">Drop</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
    if ($canEdit) {
        $ec = null;
        foreach ((array)$chks as $c) {
            if ($ek !== '' && $c['key'] === $ek) {
                $ec = $c;
            }
        }
        echo '<div class="card" id="chkform"><b>' . ($ec ? 'Edit check constraint' : 'Add check constraint') . '</b>'
            . '<form method="post" class="row" style="margin:8px 0 0" action="' . h(url($self)) . '">' . csrf_field()
            . '<input type="hidden" name="do" value="' . ($ec ? 'edit_check' : 'add_check') . '"><input type="hidden" name="old" value="' . h($ec ? $ec['key'] : '') . '">'
            . '<input type="text" name="cname" value="' . h($ec ? $ec['name'] : '') . '" placeholder="Name (optional)">'
            . '<input type="text" name="expr" value="' . h($ec ? $ec['expr'] : '') . '" placeholder="e.g. price &gt;= 0 AND qty &lt; 1000" style="flex:1;min-width:260px" required>'
            . '<button class="p">' . ($ec ? 'Save check' : 'Add check') . '</button>' . ($ec ? '<a href="' . h(url($self)) . '">Cancel</a>' : '') . '</form>'
            . ($DB->driver === 'sqlite' ? '<p class="muted" style="margin:8px 0 0">SQLite rebuilds the table to change constraints; existing rows must satisfy the new rule.</p>' : '') . '</div>';
    }
}

function page_struct()
{
    global $DB, $curDb;
    $t = gv('t');
    $info = need_table($t);
    $cols = $DB->columns($t);
    $idx = $DB->indexes($t);
    $ro = cfg('readonly');
    $isT = $info['type'] === 'table';
    $canEdit = ($isT && !$ro);
    $names = array();
    foreach ($cols as $c) {
        $names[] = $c['name'];
    }
    $self = array('a' => 'struct', 'db' => $curDb, 't' => $t);
    $ec = gv('ec');
    $ei = gv('ei');
    layout_start($t);
    tabs('struct');
    echo '<h3>Columns</h3><div class="scroll"><table><tr><th>#</th><th>Name</th><th>Type</th><th>Nullable</th><th>Default</th><th>Extra</th><th></th></tr>';
    foreach ($cols as $i => $c) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . ($c['pk'] ? '&#128273; ' : '') . '<b>' . h($c['name']) . '</b></td><td>' . h($c['type']) . '</td><td>' . ($c['null'] ? 'yes' : 'no') . '</td>';
        echo ($c['default'] === null ? '<td class="null">NULL</td>' : '<td>' . h($c['default']) . '</td>') . '<td>' . h($c['extra']) . '</td><td class="act" style="white-space:nowrap">';
        if ($canEdit) {
            echo '<a class="btn s" href="' . h(url($self + array('ec' => $c['name'])) . '#edit') . '">Edit</a> ';
            echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Drop this column?\')">' . csrf_field() . '<input type="hidden" name="do" value="drop_column"><input type="hidden" name="name" value="' . h($c['name']) . '"><button class="s d">Drop</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';

    if ($canEdit && $ec !== '') {
        $cur = null;
        foreach ($cols as $c) {
            if ($c['name'] === $ec) {
                $cur = $c;
            }
        }
        if ($cur) {
            $isText = (bool)preg_match('/char|text|enum|set/i', $cur['type']);
            echo '<h3 id="edit">Edit column: ' . h($ec) . '</h3><div class="card"><form method="post" action="' . h(url($self)) . '">' . csrf_field()
                . '<input type="hidden" name="do" value="alter_column"><input type="hidden" name="old" value="' . h($ec) . '">'
                . '<div class="row"><input type="text" name="name" value="' . h($cur['name']) . '" placeholder="Name" required>'
                . '<input type="text" name="type" list="types" value="' . h($cur['type']) . '" placeholder="Type" required>'
                . '<input type="text" name="def" value="' . h($cur['default'] === null ? '' : $cur['default']) . '" placeholder="Default">'
                . '<label><input type="checkbox" name="null" value="1"' . ($cur['null'] ? ' checked' : '') . '> Nullable</label></div>';
            if ($DB->driver === 'mysql') {
                echo '<div class="row"><label><input type="checkbox" name="ai" value="1"' . (stripos($cur['extra'], 'auto_increment') !== false ? ' checked' : '') . '> Auto-increment</label>'
                    . '<input type="text" name="comment" value="' . h($cur['comment']) . '" placeholder="Comment">'
                    . ($isText ? collation_select('collation', (string)$cur['collation']) : '')
                    . '<select name="after" title="Position"><option value="">(keep position)</option><option value="__first">First</option>';
                foreach ($names as $n) {
                    if ($n !== $ec) {
                        echo '<option value="' . h($n) . '">After ' . h($n) . '</option>';
                    }
                }
                echo '</select></div>';
            }
            echo '<div class="row" style="margin:0"><button class="p" type="submit">Save column</button><a href="' . h(url($self)) . '">Cancel</a>'
                . '<span class="muted">Defaults like <code>now()</code> are used as expressions; other text becomes a string.'
                . ($DB->driver === 'sqlite' ? ' SQLite rebuilds the table for type, null or default changes.' : '') . '</span></div></form></div>';
        }
    }

    if ($isT) {
        echo '<h3>Indexes</h3><div class="scroll"><table><tr><th>Name</th><th>Columns</th><th>Type</th><th></th></tr>';
        if (!$idx) {
            echo '<tr><td colspan="4" class="muted">No indexes.</td></tr>';
        }
        foreach ($idx as $x) {
            $plain = !empty($x['plain']);
            if ($plain) {
                foreach (explode(',', $x['columns']) as $tok) {
                    if (!in_array(trim($tok, " \t\""), $names, true)) {
                        $plain = false;
                    }
                }
            }
            echo '<tr><td>' . h($x['name']) . '</td><td>' . h($x['columns']) . '</td><td>' . ($x['primary'] ? 'PRIMARY' : ($x['unique'] ? 'UNIQUE' : 'INDEX')) . '</td><td class="act" style="white-space:nowrap">';
            if (!$x['primary'] && !$ro) {
                if ($plain) {
                    echo '<a class="btn s" href="' . h(url($self + array('ei' => $x['name'])) . '#editidx') . '">Edit</a> ';
                }
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Drop this index?\')">' . csrf_field() . '<input type="hidden" name="do" value="drop_index"><input type="hidden" name="name" value="' . h($x['name']) . '"><button class="s d">Drop</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</table></div>';
    }

    if ($canEdit && $ei !== '') {
        foreach ($idx as $x) {
            if ($x['name'] === $ei && !$x['primary']) {
                $sel = array_map('trim', explode(',', $x['columns']));
                foreach ($sel as $k => $v) {
                    $sel[$k] = trim($v, '"');
                }
                echo '<h3 id="editidx">Edit index: ' . h($ei) . '</h3><div class="card"><form method="post" class="row" style="margin:0" action="' . h(url($self)) . '">' . csrf_field()
                    . '<input type="hidden" name="do" value="edit_index"><input type="hidden" name="old" value="' . h($ei) . '">'
                    . '<input type="text" name="iname" value="' . h($x['name']) . '" placeholder="Index name"><select name="icols[]" multiple size="4">';
                foreach ($cols as $c) {
                    echo '<option' . (in_array($c['name'], $sel, true) ? ' selected' : '') . '>' . h($c['name']) . '</option>';
                }
                echo '</select><label><input type="checkbox" name="iunique" value="1"' . ($x['unique'] ? ' checked' : '') . '> Unique</label>'
                    . '<button class="p">Save index</button><a href="' . h(url($self)) . '">Cancel</a><span class="muted">Column order follows the table order.</span></form></div>';
            }
        }
    }

    if ($isT) {
        struct_constraints($t, $self, $canEdit);
    }
    echo '<h3>Definition</h3><pre>' . h($DB->createSql($t, $info['type'])) . '</pre>';
    if ($canEdit) {
        $curpk = pk_names($cols);
        echo '<h3>Primary key</h3><div class="card"><form method="post" class="row" style="margin:0" action="' . h(url($self)) . '">' . csrf_field() . '<input type="hidden" name="do" value="set_pk"><select name="pkcols[]" multiple size="4">';
        foreach ($cols as $c) {
            echo '<option' . (in_array($c['name'], $curpk, true) ? ' selected' : '') . '>' . h($c['name']) . '</option>';
        }
        echo '</select><button class="p">Set primary key</button><span class="muted">Select nothing to remove it. Ctrl/Cmd+click for several columns.'
            . ($DB->driver === 'sqlite' ? ' SQLite rebuilds the table.' : '') . '</span></form></div>';
        echo '<h3>Add column</h3><div class="card"><form method="post" class="row" style="margin:0">' . csrf_field() . '<input type="hidden" name="do" value="add_column">'
            . '<input type="text" name="cname" placeholder="Name"><input type="text" name="ctype" list="types" value="VARCHAR(255)"><input type="text" name="cdef" placeholder="Default">'
            . '<label><input type="checkbox" name="cnull" value="1" checked> Nullable</label><button class="p">Add</button></form></div>';
        echo '<h3>Add index</h3><div class="card"><form method="post" class="row" style="margin:0">' . csrf_field() . '<input type="hidden" name="do" value="add_index">'
            . '<input type="text" name="iname" placeholder="Index name (optional)"><select name="icols[]" multiple size="4">';
        foreach ($cols as $c) {
            echo '<option>' . h($c['name']) . '</option>';
        }
        echo '</select><label><input type="checkbox" name="iunique" value="1"> Unique</label><button class="p">Create</button><span class="muted">Ctrl/Cmd+click for several columns</span></form></div>';
        echo '<h3>Table</h3><div class="card"><form method="post" class="row" action="' . h(url($self)) . '">' . csrf_field() . '<input type="hidden" name="do" value="rename_table">'
            . '<input type="text" name="newname" value="' . h($t) . '" required><button class="p">Rename table</button></form>';
        if ($DB->driver === 'mysql') {
            echo '<form method="post" class="row" style="margin:0" action="' . h(url($self)) . '">' . csrf_field() . '<input type="hidden" name="do" value="table_options">'
                . engine_select('engine', $info['engine'], '(unchanged)') . collation_select('collation', isset($info['collation']) ? $info['collation'] : '', '(unchanged)')
                . '<button class="p">Apply</button><span class="muted">Changing the collation converts the table and all its text columns.</span></form>';
        }
        echo '</div>';
        echo '<h3>Danger zone</h3><form method="post" class="row"><input type="hidden" name="_t" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="tables[]" value="' . h($t) . '">'
            . '<button class="d" name="do" value="truncate_tables" onclick="return confirm(\'Empty this table?\')">Empty table</button>'
            . '<button class="d" name="do" value="drop_tables" onclick="return confirm(\'Drop this table?\')">Drop table</button></form>';
        echo types_datalist();
    }
    layout_end();
}

function types_datalist()
{
    $o = '<datalist id="types">';
    foreach (array('INT', 'BIGINT', 'SMALLINT', 'VARCHAR(255)', 'TEXT', 'BOOLEAN', 'DECIMAL(10,2)', 'FLOAT', 'DATE', 'DATETIME', 'TIMESTAMP', 'JSON', 'BLOB') as $x) {
        $o .= '<option value="' . $x . '">';
    }
    return $o . '</datalist>';
}

function page_edit()
{
    global $DB, $curDb;
    $t = gv('t');
    $info = need_table($t);
    if ($info['type'] !== 'table') {
        throw new Exception('Views are read-only.');
    }
    no_ro();
    $cols = $DB->columns($t);
    $k = gv('k');
    $row = null;
    if ($k !== '') {
        $key = rk_dec($k);
        if (!$key) {
            throw new Exception('Invalid row key.');
        }
        $params = array();
        $w = where_from_key($key, $params);
        $rows = $DB->all('SELECT ' . select_list($cols, false) . ' FROM ' . $DB->q($t) . ' WHERE ' . $w . ' LIMIT 1', $params);
        if (!$rows) {
            throw new Exception('Row not found.');
        }
        $row = $rows[0];
    }
    layout_start($t);
    tabs($k !== '' ? 'table' : 'edit');
    echo '<h3>' . ($row ? 'Edit row' : 'Insert row') . '</h3><form method="post" enctype="multipart/form-data" action="' . h(url(array('a' => 'table', 'db' => $curDb, 't' => $t))) . '">' . csrf_field()
        . '<input type="hidden" name="do" value="save_row"><input type="hidden" name="k" value="' . h($k) . '"><input type="hidden" name="ret" value="' . h(gv('ret')) . '">';
    echo '<div class="scroll"><table class="form">';
    foreach ($cols as $c) {
        $n = $c['name'];
        $v = $row ? $row[$n] : null;
        if (is_resource($v)) {
            $v = stream_get_contents($v);
        }
        echo '<tr><td>' . h($n) . '<div class="muted" style="font-weight:400;font-size:12px">' . h($c['type']) . ($c['pk'] ? ' &middot; PK' : '') . '</div></td><td class="fv">';
        if ($c['binary']) {
            if ($row && $v !== null) {
                echo '<span class="muted">' . h(hsize($v)) . '</span> <a href="' . h(url(array('a' => 'blob', 'db' => $curDb, 't' => $t, 'c' => $n, 'k' => $k))) . '">Download</a> ';
            } elseif ($row) {
                echo '<span class="muted">NULL</span> ';
            }
            echo '<input class="in" type="file" name="blob[' . h($n) . ']">';
            if ($c['null']) {
                echo ' <label class="nb"><input type="checkbox" name="null[' . h($n) . ']" value="1"' . (($row && $v === null) ? ' checked' : '') . '> NULL</label>';
            }
            echo '<div class="muted" style="font-size:12px">' . ($row ? 'Choose a file to replace the value, or leave empty to keep it.' : 'Choose a file to upload.') . '</div>';
        } else {
            $sv = ($v === null) ? '' : val_str($v);
            $ph = (!$row && $c['hasdef']) ? ($c['auto'] ? 'auto' : 'default: ' . (string)$c['default']) : '';
            $long = ($c['type'] !== '' && preg_match('/text|json|xml|clob/i', $c['type'])) || strlen($sv) > 100 || strpos($sv, "\n") !== false;
            $kind = $c['kind'];
            $opts = $c['opts'];
            if ($kind === '' && $DB->driver === 'pgsql') {
                $opts = $DB->pgEnum($c['type']);
                $kind = $opts ? 'enum' : '';
            }
            if ($kind === 'enum') {
                echo '<select class="in" name="f[' . h($n) . ']">';
                if (!$row && ($c['hasdef'] || $c['null'])) {
                    echo '<option value="">' . ($c['hasdef'] ? '(' . h($ph !== '' ? $ph : 'default') . ')' : '') . '</option>';
                }
                if ($row && $v !== null && $sv === '' && !in_array('', $opts, true)) {
                    echo '<option value="" selected></option>';
                }
                if ($row && $sv !== '' && !in_array($sv, $opts, true)) {
                    echo '<option selected>' . h($sv) . '</option>';
                }
                foreach ($opts as $o) {
                    echo '<option' . (($row && $o === $sv) ? ' selected' : '') . '>' . h($o) . '</option>';
                }
                echo '</select>';
            } elseif ($kind === 'set') {
                $cur = ($row && $sv !== '') ? explode(',', $sv) : array();
                foreach ($opts as $o) {
                    echo '<label class="chk"><input class="in" type="checkbox" name="f[' . h($n) . '][]" value="' . h($o) . '"' . (in_array($o, $cur, true) ? ' checked' : '') . '> ' . h($o) . '</label>';
                }
                if (!$row && $c['hasdef']) {
                    echo ' <span class="muted">(none checked = default)</span>';
                }
            } elseif ($long) {
                echo '<textarea class="in" name="f[' . h($n) . ']" rows="4" placeholder="' . h($ph) . '">' . h($sv) . '</textarea>';
            } else {
                echo '<input class="in" type="text" name="f[' . h($n) . ']" value="' . h($sv) . '" placeholder="' . h($ph) . '" style="width:min(520px,100%)">';
            }
            if ($c['null']) {
                echo ' <label class="nb"><input type="checkbox" name="null[' . h($n) . ']" value="1"' . (($row && $v === null) ? ' checked' : '') . '> NULL</label>';
            }
        }
        echo '</td></tr>';
    }
    echo '</table></div><div class="row"><button class="p" type="submit">Save</button>';
    if (!$row) {
        echo '<button type="submit" name="again" value="1">Save &amp; add another</button>';
    }
    echo '<a href="' . h(gv('ret') !== '' && gv('ret')[0] === '?' ? gv('ret') : url(array('a' => 'table', 'db' => $curDb, 't' => $t))) . '">Cancel</a></div></form>';
    layout_end();
}

function page_blob()
{
    global $DB;
    $t = gv('t');
    $c = gv('c');
    need_table($t);
    $found = false;
    foreach ($DB->columns($t) as $x) {
        if ($x['name'] === $c) {
            $found = true;
        }
    }
    if (!$found) {
        throw new Exception('Column not found.');
    }
    $key = rk_dec(gv('k'));
    if (!$key) {
        throw new Exception('Invalid row key.');
    }
    $params = array();
    $w = where_from_key($key, $params);
    $rows = $DB->all('SELECT ' . $DB->q($c) . ' FROM ' . $DB->q($t) . ' WHERE ' . $w . ' LIMIT 1', $params);
    if (!$rows) {
        throw new Exception('Row not found.');
    }
    if ($rows[0][$c] === null) {
        throw new Exception('The value is NULL.');
    }
    $data = val_str($rows[0][$c]);
    $parts = array($t, $c);
    foreach ($key as $kv) {
        if (is_scalar($kv)) {
            $parts[] = cut((string)$kv, 20);
        }
    }
    $fn = trim(substr(preg_replace('/[^A-Za-z0-9_-]+/', '_', implode('_', $parts)), 0, 100), '_');
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . ($fn !== '' ? $fn : 'blob') . '"');
    header('Content-Length: ' . strlen($data));
    header('X-Content-Type-Options: nosniff');
    echo $data;
    exit;
}

function page_create()
{
    global $curDb, $DB;
    no_ro();
    layout_start('New table');
    echo '<h2>Create table</h2><form method="post" action="' . h(url(array('db' => $curDb))) . '">' . csrf_field() . '<input type="hidden" name="do" value="create_table">';
    echo '<div class="row"><input type="text" name="name" placeholder="Table name" required>'
        . ($DB->driver === 'mysql' ? engine_select('engine') . collation_select('collation') : '') . '</div>';
    echo '<div class="scroll"><table><tr><th>Name</th><th>Type</th><th>Default</th><th>Null</th><th>PK</th><th>Auto-inc</th><th></th></tr><tbody id="cols">';
    $first = true;
    for ($i = 0; $i < 3; $i++) {
        echo '<tr><td><input type="text" name="c[' . $i . '][name]" value="' . ($i === 0 ? 'id' : '') . '" data-def=""></td>'
            . '<td><input type="text" name="c[' . $i . '][type]" list="types" value="' . ($i === 0 ? 'INT' : 'VARCHAR(255)') . '" data-def="VARCHAR(255)"></td>'
            . '<td><input type="text" name="c[' . $i . '][def]" data-def=""></td>'
            . '<td><input type="checkbox" name="c[' . $i . '][null]" value="1"' . ($i === 0 ? '' : ' checked') . '></td>'
            . '<td><input type="checkbox" name="c[' . $i . '][pk]" value="1"' . ($i === 0 ? ' checked' : '') . '></td>'
            . '<td><input type="checkbox" name="c[' . $i . '][ai]" value="1"' . ($i === 0 ? ' checked' : '') . '></td><td><button type="button" class="s d rm" title="Remove column">&times;</button></td></tr>';
    }
    echo '</tbody></table></div><div class="row"><button type="button" id="addcol">+ Column</button><button class="p" type="submit">Create table</button><span class="muted">Rows with an empty name are ignored.</span></div></form>' . types_datalist();
    layout_end();
}

function page_sql()
{
    global $DB, $curDb;
    $sql = '';
    $results = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && pv('do') === 'sql') {
        csrf_check();
        $sql = pv('sql');
        $hist = isset($_SESSION['hist']) ? $_SESSION['hist'] : array();
        array_unshift($hist, $sql);
        $_SESSION['hist'] = array_slice(array_values(array_unique($hist)), 0, 15);
        $max = (int)cfg('max_result_rows');
        foreach (split_sql($sql, $DB->driver) as $stmt) {
            $r = array('sql' => $stmt, 'err' => '', 'cols' => array(), 'rows' => array(), 'aff' => 0, 'more' => false, 'time' => 0);
            try {
                if (cfg('readonly') && !is_read_sql($stmt)) {
                    throw new Exception('Read-only mode: only SELECT-type statements are allowed.');
                }
                $t0 = microtime(true);
                $st = $DB->pdo->query($stmt);
                if ($st->columnCount() > 0) {
                    for ($i = 0; $i < $st->columnCount(); $i++) {
                        $m = $st->getColumnMeta($i);
                        $r['cols'][] = $m ? $m['name'] : 'col' . $i;
                    }
                    while (($row = $st->fetch(PDO::FETCH_NUM)) !== false) {
                        if (count($r['rows']) >= $max) {
                            $r['more'] = true;
                            break;
                        }
                        $r['rows'][] = $row;
                    }
                } else {
                    $r['aff'] = $st->rowCount();
                }
                $st->closeCursor();
                $r['time'] = microtime(true) - $t0;
            } catch (Exception $e) {
                $r['err'] = $e->getMessage();
            }
            $results[] = $r;
            if ($r['err'] !== '') {
                break;
            }
        }
        if (!$results) {
            $results[] = array('sql' => '', 'err' => 'Nothing to execute.', 'cols' => array(), 'rows' => array(), 'aff' => 0, 'more' => false, 'time' => 0);
        }
    }
    layout_start('SQL');
    echo '<h2>SQL command</h2><form method="post" action="' . h(url(array('a' => 'sql', 'db' => $curDb))) . '">' . csrf_field() . '<input type="hidden" name="do" value="sql">';
    echo '<textarea id="sql" name="sql" rows="9" spellcheck="false" placeholder="SELECT * FROM ...">' . h($sql) . '</textarea>';
    echo '<div class="row" style="margin-top:8px"><button class="p" type="submit">Execute</button><span class="muted">Ctrl/Cmd + Enter &middot; several statements separated by ;</span></div></form>';
    foreach ($results as $r) {
        echo '<div class="card"><pre>' . h(cut($r['sql'], 600)) . '</pre>';
        if ($r['err'] !== '') {
            echo '<div class="msg err" style="margin:10px 0 0">' . h($r['err']) . '</div></div>';
            continue;
        }
        echo '<p class="muted" style="margin:8px 0 0">' . ($r['cols'] ? count($r['rows']) . ' row(s)' . ($r['more'] ? ' (limited)' : '') : (int)$r['aff'] . ' row(s) affected') . ' &middot; ' . round($r['time'] * 1000, 1) . ' ms</p>';
        if ($r['cols']) {
            echo '<div class="scroll" style="margin:10px 0 0"><table><tr>';
            foreach ($r['cols'] as $c) {
                echo '<th>' . h($c) . '</th>';
            }
            echo '</tr>';
            foreach ($r['rows'] as $row) {
                echo '<tr>';
                foreach ($row as $v) {
                    echo cell($v);
                }
                echo '</tr>';
            }
            echo '</table></div>';
        }
        echo '</div>';
    }
    if (!empty($_SESSION['hist'])) {
        echo '<h3>History</h3><div class="hist">';
        foreach ($_SESSION['hist'] as $hq) {
            echo '<a href="#" data-sql="' . h($hq) . '">' . h(cut(preg_replace('/\s+/', ' ', $hq), 140)) . '</a>';
        }
        echo '</div>';
    }
    layout_end();
}

function page_export()
{
    global $curDb, $TABLES;
    layout_start('Export');
    $pre = gv('t');
    echo '<h2>Export</h2><form method="post" action="' . h(url(array('a' => 'export', 'db' => $curDb))) . '">' . csrf_field() . '<input type="hidden" name="do" value="export">';
    echo '<div class="scroll"><table><tr><th class="act"><input type="checkbox" data-all' . ($pre === '' ? ' checked' : '') . '></th><th>Table</th><th>Type</th></tr>';
    foreach ($TABLES as $x) {
        echo '<tr><td class="act"><input type="checkbox" name="tables[]" value="' . h($x['name']) . '"' . (($pre === '' || $pre === $x['name']) ? ' checked' : '') . '></td><td>' . h($x['name']) . '</td><td>' . h($x['type']) . '</td></tr>';
    }
    echo '</table></div><div class="card"><div class="row"><label><input type="radio" name="format" value="sql" checked> SQL</label>'
        . '<label><input type="radio" name="format" value="csv"> CSV (first selected table only)</label></div>'
        . '<div class="row" style="margin:0"><label><input type="checkbox" name="structure" value="1" checked> Structure</label>'
        . '<label><input type="checkbox" name="data" value="1" checked> Data</label>'
        . '<label><input type="checkbox" name="drop" value="1" checked> Add DROP statements</label></div></div>'
        . '<button class="p" type="submit">Download</button></form>';
    layout_end();
}

function upload_text($field)
{
    if (isset($_FILES[$field]) && is_array($_FILES[$field]) && isset($_FILES[$field]['error']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $x = (string)file_get_contents($_FILES[$field]['tmp_name']);
        return (substr($x, 0, 3) === "\xEF\xBB\xBF") ? substr($x, 3) : $x;
    }
    return null;
}

function import_sql()
{
    global $DB;
    $sql = upload_text('file');
    if ($sql === null) {
        $sql = pv('sql');
    }
    $pdo = $DB->pdo;
    $tx = pv('tx') !== '';
    $cont = pv('cont') !== '';
    $log = array('msg' => '', 'errors' => array());
    $stmts = split_sql($sql, $DB->driver);
    if (!$stmts) {
        $log['msg'] = 'Nothing executed.';
        $log['errors'][] = 'No SQL statements found.';
        return $log;
    }
    $ok = 0;
    $skipped = 0;
    if ($tx) {
        $pdo->beginTransaction();
    }
    foreach ($stmts as $i => $st) {
        if ($tx && preg_match('/^(BEGIN|START\s+TRANSACTION|COMMIT|END|ROLLBACK)\b/i', $st)) {
            $skipped++;
            continue;
        }
        try {
            $pdo->exec($st);
            $ok++;
        } catch (Exception $e) {
            $log['errors'][] = 'Statement #' . ($i + 1) . ': ' . $e->getMessage() . "\n  " . cut(preg_replace('/\s+/', ' ', $st), 160);
            if ($tx || !$cont || count($log['errors']) >= 20) {
                break;
            }
        }
    }
    if ($tx) {
        if ($log['errors']) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $log['msg'] = 'Import failed and was rolled back' . ($DB->driver === 'mysql' ? ' (MySQL cannot undo DDL such as CREATE/ALTER/DROP).' : '.');
        } else {
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $log['msg'] = $ok . ' statement(s) executed in one transaction.';
        }
        if ($skipped) {
            $log['msg'] .= ' ' . $skipped . ' transaction-control statement(s) skipped.';
        }
    } else {
        $log['msg'] = $ok . ' statement(s) executed.';
    }
    return $log;
}

function import_csv()
{
    global $DB, $TABLES;
    $data = upload_text('csv');
    if ($data === null) {
        throw new Exception('Choose a CSV file to upload.');
    }
    $dm = array('comma' => ',', 'semi' => ';', 'tab' => "\t", 'pipe' => '|');
    $d = isset($dm[pv('delim')]) ? $dm[pv('delim')] : ',';
    $hdr = pv('header') !== '';
    $emptyNull = pv('emptynull') !== '';
    $new = trim(pv('newtable'));
    $target = pv('target');
    $pdo = $DB->pdo;
    $gen = csv_rows($data, $d);
    if (!$gen->valid()) {
        throw new Exception('The CSV file is empty.');
    }
    $first = $gen->current();
    $created = false;
    if ($new !== '') {
        $names = array();
        foreach ($first as $i => $v) {
            $v = $hdr ? trim($v) : '';
            if ($v === '') {
                $v = 'col' . ($i + 1);
            }
            $base = $v;
            $z = 2;
            while (in_array($v, $names, true)) {
                $v = $base . '_' . $z++;
            }
            $names[] = $v;
        }
        $defs = array();
        foreach ($names as $nm) {
            $defs[] = $DB->q($nm) . ' TEXT';
        }
        $pdo->exec('CREATE TABLE ' . $DB->q($new) . " (\n  " . implode(",\n  ", $defs) . "\n)");
        $created = true;
        $table = $new;
        $cols = $names;
    } else {
        $map = tbl_map();
        if (!isset($map[$target]) || $map[$target]['type'] !== 'table') {
            throw new Exception('Choose a target table or enter a new table name.');
        }
        $table = $target;
        $all = array();
        foreach ($DB->columns($table) as $c) {
            $all[] = $c['name'];
        }
        if ($hdr) {
            $cols = array();
            foreach ($first as $v) {
                $v = trim($v);
                if (!in_array($v, $all, true)) {
                    throw new Exception('Header column "' . $v . '" does not exist in table ' . $table . '.');
                }
                $cols[] = $v;
            }
        } else {
            $cols = $all;
        }
    }
    if ($hdr) {
        $gen->next();
    }
    $nc = count($cols);
    $cnt = 0;
    $rec = $hdr ? 1 : 0;
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO ' . $DB->q($table) . ' (' . implode(', ', array_map(array($DB, 'q'), $cols)) . ') VALUES (' . implode(', ', array_fill(0, $nc, '?')) . ')');
        while ($gen->valid()) {
            $row = $gen->current();
            $gen->next();
            $rec++;
            if (count($row) > $nc) {
                throw new Exception('Record #' . $rec . ' has ' . count($row) . ' fields but only ' . $nc . ' columns are mapped.');
            }
            $vals = array();
            for ($i = 0; $i < $nc; $i++) {
                $v = isset($row[$i]) ? $row[$i] : null;
                $vals[] = ($v === '' && $emptyNull) ? null : $v;
            }
            $st->execute($vals);
            $cnt++;
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($created) {
            try {
                $pdo->exec('DROP TABLE ' . $DB->q($table));
            } catch (Exception $e2) {
            }
        }
        return array('msg' => 'CSV import failed and was rolled back.', 'errors' => array($e->getMessage()));
    }
    return array('msg' => $cnt . ' row(s) imported into ' . $table . ($created ? ' (new table, all columns TEXT)' : '') . '.', 'errors' => array());
}

function page_import()
{
    global $DB, $curDb, $TABLES;
    $log = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (pv('do') === 'import' || pv('do') === 'import_csv')) {
        csrf_check();
        no_ro();
        @set_time_limit(0);
        try {
            $log = (pv('do') === 'import') ? import_sql() : import_csv();
        } catch (Exception $e) {
            $log = array('msg' => 'Import failed.', 'errors' => array($e->getMessage()));
        }
        $TABLES = $DB->tables();
    }
    layout_start('Import');
    echo '<h2>Import</h2>';
    if ($log) {
        echo '<div class="msg ' . ($log['errors'] ? 'err' : 'ok') . '">' . h($log['msg']) . ($log['errors'] ? "\n" . h(implode("\n", $log['errors'])) : '') . '</div>';
    }
    if (cfg('readonly')) {
        echo '<p class="muted">Read-only mode is enabled.</p>';
        layout_end();
        return;
    }
    $act = h(url(array('a' => 'import', 'db' => $curDb)));
    echo '<h3>SQL file</h3><form method="post" enctype="multipart/form-data" action="' . $act . '">' . csrf_field() . '<input type="hidden" name="do" value="import">'
        . '<div class="card"><p style="margin-top:0"><input type="file" name="file" accept=".sql,.txt"> <span class="muted">max ' . h(ini_get('upload_max_filesize')) . '</span></p>'
        . '<textarea name="sql" rows="7" spellcheck="false" placeholder="...or paste SQL here"></textarea></div>'
        . '<div class="row"><label><input type="checkbox" name="tx" value="1" checked> Run in a single transaction (all or nothing)</label>'
        . '<label><input type="checkbox" name="cont" value="1"> Continue on errors <span class="muted">(only without transaction)</span></label><button class="p" type="submit">Execute</button></div></form>';
    if ($DB->driver === 'mysql') {
        echo '<p class="muted">MySQL commits CREATE/ALTER/DROP automatically, so those cannot be rolled back. <code>DELIMITER</code> blocks are supported.</p>';
    }
    echo '<h3>CSV file</h3><form method="post" enctype="multipart/form-data" action="' . $act . '">' . csrf_field() . '<input type="hidden" name="do" value="import_csv">'
        . '<div class="card"><div class="row"><input type="file" name="csv" accept=".csv,.tsv,.txt"><select name="delim"><option value="comma">Comma ,</option><option value="semi">Semicolon ;</option><option value="tab">Tab</option><option value="pipe">Pipe |</option></select></div>'
        . '<div class="row"><select name="target"><option value="">Import into existing table...</option>';
    foreach ($TABLES as $x) {
        if ($x['type'] === 'table') {
            echo '<option>' . h($x['name']) . '</option>';
        }
    }
    echo '</select><span class="muted">or</span><input type="text" name="newtable" placeholder="New table name (TEXT columns)"></div>'
        . '<div class="row" style="margin:0"><label><input type="checkbox" name="header" value="1" checked> First row is a header (matched to column names)</label>'
        . '<label><input type="checkbox" name="emptynull" value="1"> Empty values as NULL</label></div></div>'
        . '<button class="p" type="submit">Import CSV</button> <span class="muted">Always one transaction.</span></form>';
    layout_end();
}

// ============================== MAIN ==============================
$STARTED = false;
$DB = null;
$auth = null;
$curDb = '';
$TABLES = array();

function main()
{
    global $DB, $auth, $curDb, $TABLES;
    $auth = isset($_SESSION['auth']) ? $_SESSION['auth'] : null;

    if (gv('a') === 'logout') {
        csrf_check(gv('_t'));
        $_SESSION = array();
        session_regenerate_id(true);
        redirect('?');
    }

    if (!$auth) {
        $err = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && pv('do') === 'login') {
            try {
                csrf_check();
                $d = pv('driver');
                if (!in_array($d, cfg('drivers'), true) || !in_array($d, PDO::getAvailableDrivers(), true)) {
                    throw new Exception('Unsupported driver.');
                }
                if (cfg('access_password') !== '' && !hash_equals((string)cfg('access_password'), pv('gate'))) {
                    usleep(800000);
                    throw new Exception('Wrong access password.');
                }
                $srv = trim(pv('server')) !== '' ? trim(pv('server')) : cfg('default_server');
                $pw = pv('pass');
                if ($pw === '' && cfg('default_pass') !== '' && pv('user') === cfg('default_user') && $srv === cfg('default_server')) {
                    $pw = cfg('default_pass');
                }
                $a = array('driver' => $d, 'server' => $srv, 'user' => pv('user'), 'pass' => $pw, 'db' => trim(pv('db')), 'path' => '');
                if ($d === 'sqlite') {
                    $in = trim(pv('db'));
                    if ($in === '' || strpos($in, "\0") !== false) {
                        throw new Exception('Enter the SQLite file path.');
                    }
                    $bd = (string)cfg('sqlite_basedir');
                    $a['path'] = ($bd !== '') ? rtrim($bd, '/\\') . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $in)) : $in;
                }
                $x = new LiteDB_Db();
                try {
                    $x->connect($a, '');
                } catch (Exception $e) {
                    usleep(800000);
                    throw $e;
                }
                session_regenerate_id(true);
                $_SESSION['auth'] = $a;
                redirect('?');
            } catch (Exception $e) {
                $err = $e->getMessage();
            }
        }
        render_login($err);
        return;
    }

    $DB = new LiteDB_Db();
    $sqlite = ($auth['driver'] === 'sqlite');
    $curDb = $sqlite ? 'main' : gv('db');
    if (!$sqlite && !$_GET && $auth['db'] !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        redirect(url(array('db' => $auth['db'])));
    }
    try {
        $DB->connect($auth, $sqlite ? '' : $curDb);
    } catch (Exception $e) {
        $auth = null;
        layout_start('Connection error');
        echo '<div class="msg err" style="margin:16px">' . h($e->getMessage()) . '</div><p style="margin:16px"><a href="'
            . h(url(array('a' => 'logout', '_t' => $_SESSION['csrf']))) . '">Log out</a> &middot; <a href="?db=">Server</a></p>';
        layout_end();
        return;
    }
    if ($auth['driver'] === 'pgsql' && $curDb !== '') {
        if (isset($_GET['sc'])) {
            $_SESSION['pgschema'][$curDb] = gv('sc');
            redirect(url(array('db' => $curDb)));
        }
        if (!empty($_SESSION['pgschema'][$curDb])) {
            $DB->pdo->exec('SET search_path TO ' . $DB->q($_SESSION['pgschema'][$curDb]));
        }
    }
    $TABLES = ($curDb !== '') ? $DB->tables() : array();

    $a = gv('a');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && !empty($_SERVER['CONTENT_LENGTH'])) {
        throw new Exception('The upload is larger than PHP allows (post_max_size = ' . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . ').');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = pv('do');
        if ($do === 'export') {
            do_export();
        } elseif ($do !== 'sql' && $do !== 'import' && $do !== 'import_csv') {
            try {
                handle_post();
            } catch (Exception $e) {
                flash($e->getMessage(), 'err');
                redir_ret('?' . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''));
            }
        }
    }

    if ($curDb === '' && !in_array($a, array('sql'), true)) {
        page_server();
        return;
    }
    switch ($a) {
        case 'table':
            page_browse();
            break;
        case 'struct':
            page_struct();
            break;
        case 'blob':
            page_blob();
            break;
        case 'edit':
            page_edit();
            break;
        case 'create':
            page_create();
            break;
        case 'sql':
            page_sql();
            break;
        case 'export':
            page_export();
            break;
        case 'import':
            page_import();
            break;
        default:
            page_tables();
    }
}

try {
    main();
} catch (Exception $e) {
    if (!$STARTED) {
        layout_start('Error');
    }
    echo '<div class="msg err" style="margin:16px">' . h($e->getMessage()) . '</div>';
    if ($auth) {
        echo '<p style="margin:16px"><a href="?">&laquo; Back</a></p>';
    }
    layout_end();
} catch (Throwable $e) {
    if (!$STARTED) {
        layout_start('Error');
    }
    echo '<div class="msg err" style="margin:16px">' . h($e->getMessage()) . '</div>';
    layout_end();
}
