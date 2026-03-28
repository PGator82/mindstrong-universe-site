<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| MindStrong Universe — Railway MySQL connection
|
| Railway injects these environment variables automatically:
|   MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
|
| For local development, copy .env.example to .env and set values.
*/

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'dsn'          => '',
    'hostname'     => getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost',
    'port'         => getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: 3306,
    'username'     => getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root',
    'password'     => getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '',
    'database'     => getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'mindstrong',
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
