<?php
if (($_GET['key'] ?? '') !== 'MS-Install-2026') { http_response_code(403); exit('Forbidden'); }
$db_url = getenv('DATABASE_URL') ?: '';
if ($db_url && strpos($db_url, '@') !== false) {
    $u = parse_url($db_url);
    $host = $u['host'] ?? 'localhost'; $port = (int)($u['port'] ?? 3306);
    $user = isset($u['user']) ? urldecode($u['user']) : 'root';
    $pass = isset($u['pass']) ? urldecode($u['pass']) : '';
    $name = ltrim($u['path'] ?? '/railway', '/');
} else {
    $host = getenv('MYSQLHOST') ?: 'localhost'; $port = (int)(getenv('MYSQLPORT') ?: 3306);
    $user = getenv('MYSQLUSER') ?: 'root'; $pass = getenv('MYSQLPASSWORD') ?: '';
    $name = getenv('MYSQLDATABASE') ?: 'railway';
}
ini_set('default_socket_timeout', 10);
$conn = new mysqli($host, $user, $pass, $name, $port);
if ($conn->connect_error) { http_response_code(500); exit('DB failed: ' . $conn->connect_error); }
$sql = file_get_contents(__DIR__ . '/myschool.sql');
$conn->multi_query($sql);
$errors = []; $done = 0;
do { $done++; if ($conn->errno) $errors[] = "Q$done: " . $conn->error; } while ($conn->next_result());
echo '<pre>Connected: ' . $host . ':' . $port . '/' . $name . "\n";
echo empty($errors) ? "✅ Done — $done statements.\n" : "⚠️ " . count($errors) . " errors:\n" . implode("\n", $errors) . "\n";
$r = $conn->query('SHOW TABLES'); echo "\nTables:\n";
while ($row = $r->fetch_row()) echo '  ' . $row[0] . "\n";
echo '</pre>';
