<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Strip Raw Editor corruption from host/port-like values only.
function _db_clean($val, $allowEquals = true) {
    $val = trim((string)$val);
    if (strpos($val, ' ') !== false) $val = explode(' ', $val, 2)[0];
    if (!$allowEquals && strpos($val, '=') !== false && strpos($val, '.') === false) $val = explode('=', $val, 2)[1];
    return trim($val);
}

function _db_candidates($preferred_host) {
    $candidates = [];
    $push = function ($host) use (&$candidates) {
        $host = _db_clean((string)$host, false);
        if ($host === '' || in_array($host, $candidates, true)) return;
        $candidates[] = $host;
    };

    $push($preferred_host);
    $push(getenv('MYSQLHOST'));
    $push('mysql');
    $push('mysql.railway.internal');
    $push('127.0.0.1');
    $push('localhost');

    return $candidates;
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
    $_host = _db_clean(getenv('MYSQLHOST')     ?: '', false) ?: 'localhost';
    $_port = (int)(_db_clean(getenv('MYSQLPORT') ?: '', false) ?: 3306);
    $_user = trim((string)(getenv('MYSQLUSER')     ?: '')) ?: 'root';
    $_pass = (string)(getenv('MYSQLPASSWORD') ?: '');
    $_name = trim((string)(getenv('MYSQLDATABASE') ?: '')) ?: 'railway';
}

$_hosts = _db_candidates($_host);
$_host = $_hosts[0] ?? 'localhost';
$_failover = [];
foreach (array_slice($_hosts, 1) as $_fallback_host) {
    $_failover[] = array(
        'dsn'          => '',
        'hostname'     => $_fallback_host,
        'port'         => $_port,
        'username'     => $_user,
        'password'     => $_pass,
        'database'     => $_name,
        'dbdriver'     => 'mysqli',
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
        'save_queries' => (ENVIRONMENT !== 'production'),
    );
}

if (!headers_sent()) {
    @ini_set('mysql.connect_timeout', '5');
    @ini_set('default_socket_timeout', '5');
}

$db['default'] = array(
    'dsn'          => '',
    'hostname'     => $_host,
    'port'         => $_port,
    'username'     => $_user,
    'password'     => $_pass,
    'database'     => $_name,
    'dbdriver'     => 'mysqli',
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
    'failover'     => $_failover,
    'save_queries' => (ENVIRONMENT !== 'production'),
);
