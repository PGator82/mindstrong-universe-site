<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| MindStrong Universe — Railway MySQL connection
|
| Primary:  DATABASE_URL  (set on main service as ${{MySQL.MYSQL_URL}})
| Fallback: individual MYSQLHOST / MYSQLPORT / MYSQLUSER / MYSQLPASSWORD / MYSQLDATABASE
*/

$active_group = 'default';
$query_builder = TRUE;

// Parse DATABASE_URL / MYSQL_URL if available (most reliable on Railway)
$_db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if ($_db_url && strpos($_db_url, 'mysql') !== false) {
    $_u = parse_url($_db_url);
    $_host = $_u['host'] ?? 'localhost';
    $_port = $_u['port'] ?? 3306;
    $_user = isset($_u['user']) ? urldecode($_u['user']) : 'root';
    $_pass = isset($_u['pass']) ? urldecode($_u['pass']) : '';
    $_name = ltrim($_u['path'] ?? '/railway', '/');
} else {
    $_host = getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost';
    $_port = getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: 3306;
    $_user = getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root';
    $_pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';
    $_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'railway';
}

$db['default'] = array(
    'dsn'          => '',
    'hostname'     => $_host,
    'port'         => (int)$_port,
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
    'failover'     => array(),
    'save_queries' => (ENVIRONMENT !== 'production'),
);
