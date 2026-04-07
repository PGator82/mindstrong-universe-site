<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Strip Railway Raw Editor corruption: "host MYSQLUSER=root" → "host"
function _db_clean($val) {
    if (strpos($val, ' ') !== false) $val = explode(' ', $val, 2)[0];
    if (strpos($val, '=') !== false && strpos($val, '.') === false) $val = explode('=', $val, 2)[1];
    return trim($val);
}

$active_group = 'default';
$query_builder = TRUE;

$_db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';

// Only use URL if it's a real resolved connection string (no unresolved ${{ }} tokens)
if ($_db_url && strpos($_db_url, '${{') === false && strpos($_db_url, '@') !== false) {
    $_u    = parse_url($_db_url);
    $_host = $_u['host'] ?? 'localhost';
    $_port = (int)($_u['port'] ?? 3306);
    $_user = isset($_u['user']) ? urldecode($_u['user']) : 'root';
    $_pass = isset($_u['pass']) ? urldecode($_u['pass']) : '';
    $_name = ltrim($_u['path'] ?? '/railway', '/');
} else {
    $_host = _db_clean(getenv('MYSQLHOST')     ?: '') ?: 'localhost';
    $_port = (int)(_db_clean(getenv('MYSQLPORT') ?: '') ?: 3306);
    $_user = _db_clean(getenv('MYSQLUSER')     ?: '') ?: 'root';
    $_pass = _db_clean(getenv('MYSQLPASSWORD') ?: '') ?: '';
    $_name = _db_clean(getenv('MYSQLDATABASE') ?: '') ?: 'railway';
}

$db['default'] = array(
    'dsn'          => '',
    'hostname'     => $_host,
    'port'         => $_port,
    'username'     => $_user,
    'password'     => $_pass,
    'database'     => $_name,
    'dbdriver'     => 'mysqli',
    'conn_timeout' => 5,
    'dbprefix'     => '',
    'pconnect'     => FALSE,
    'db_debug'     => (ENVIRONMENT !== 'production'),
    'cache_on'     => FALSE,
    'cachedir'     => '',
    'char_set'     => 'utf8mb4',
    'dbcollat'     => 'utf8mb4_unicode_ci',
    'swap_pre'     => '',
    'encrypt'      => FALSE,
    'compress'     => FALSE,
    'stricton'     => FALSE,
    'failover'     => array(),
    'save_queries' => (ENVIRONMENT !== 'production'),
);
