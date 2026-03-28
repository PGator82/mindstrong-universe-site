<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

function adminer_object() {
    class AdminerSecure extends Adminer {
        function login($login, $password) { return true; }
        function credentials() {
            global $DB_SERVER, $DB_USER, $DB_PASS;
            return [$DB_SERVER, $DB_USER, $DB_PASS];
        }
        function database() { global $DB_NAME; return $DB_NAME; }
        function name() { return 'MindStrong Admin'; }
        function permanentLogin() { return true; }
    }
    return new AdminerSecure();
}

include __DIR__ . '/admin-db.php';
