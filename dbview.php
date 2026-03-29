<?php
// ── Auto-connect using Railway DB credentials ──
function ms_clean_env(string $val): string {
    if (strpos($val, ' ') !== false) $val = explode(' ', $val, 2)[0];
    if (strpos($val, '=') !== false && strpos($val, '.') === false) $val = explode('=', $val, 2)[1];
    return trim($val);
}

$db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if ($db_url && strpos($db_url, '${{') === false && strpos($db_url, 'mysql') === 0) {
    $u         = parse_url($db_url);
    $DB_SERVER = ($u['host'] ?? 'localhost') . ':' . ($u['port'] ?? 3306);
    $DB_USER   = isset($u['user']) ? urldecode($u['user']) : 'root';
    $DB_PASS   = isset($u['pass']) ? urldecode($u['pass']) : '';
    $DB_NAME   = ltrim($u['path'] ?? '/railway', '/');
} else {
    $DB_SERVER = (ms_clean_env(getenv('MYSQLHOST') ?: '') ?: 'localhost')
               . ':' . (ms_clean_env(getenv('MYSQLPORT') ?: '') ?: 3306);
    $DB_USER   = ms_clean_env(getenv('MYSQLUSER') ?: '') ?: 'root';
    $DB_PASS   = ms_clean_env(getenv('MYSQLPASSWORD') ?: '') ?: '';
    $DB_NAME   = ms_clean_env(getenv('MYSQLDATABASE') ?: '') ?: 'railway';
}

define('MS_DB_ADMIN', true);
include __DIR__ . '/admin-db.php';
