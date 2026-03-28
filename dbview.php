<?php
// ── Auto-connect using Railway DB credentials ──
$mysql_url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
if ($mysql_url) {
    $u = parse_url($mysql_url);
    $DB_SERVER = ($u['host'] ?? 'localhost') . ':' . ($u['port'] ?? 3306);
    $DB_USER   = $u['user'] ?? 'root';
    $DB_PASS   = $u['pass'] ?? '';
    $DB_NAME   = ltrim($u['path'] ?? '/railway', '/');
} else {
    $DB_SERVER = (getenv('MYSQLHOST') ?: 'localhost') . ':' . (getenv('MYSQLPORT') ?: 3306);
    $DB_USER   = getenv('MYSQLUSER') ?: 'root';
    $DB_PASS   = getenv('MYSQLPASSWORD') ?: '';
    $DB_NAME   = getenv('MYSQLDATABASE') ?: 'railway';
}

define('MS_DB_ADMIN', true);
include __DIR__ . '/admin-db.php';
