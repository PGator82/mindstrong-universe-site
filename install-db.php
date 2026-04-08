<?php
// One-time DB importer — delete this file after use
define('IMPORT_KEY', 'MS-Install-2026');

if (($_GET['key'] ?? '') !== IMPORT_KEY) {
    http_response_code(403);
    exit('Forbidden. Add ?key=MS-Install-2026 to the URL.');
}

$db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if ($db_url && strpos($db_url, '${{') === false && strpos($db_url, '@') !== false) {
    $u    = parse_url($db_url);
    $host = $u['host'] ?? 'localhost';
    $port = (int)($u['port'] ?? 3306);
    $user = isset($u['user']) ? urldecode($u['user']) : 'root';
    $pass = isset($u['pass']) ? urldecode($u['pass']) : '';
    $name = ltrim($u['path'] ?? '/railway', '/');
} else {
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = (int)(getenv('MYSQLPORT') ?: 3306);
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';
    $name = getenv('MYSQLDATABASE') ?: 'railway';
}

ini_set('default_socket_timeout', 10);
$conn = new mysqli($host, $user, $pass, $name, $port);
if ($conn->connect_error) {
    http_response_code(500);
    exit('DB connection failed: ' . $conn->connect_error);
}

$sql = file_get_contents(__DIR__ . '/myschool.sql');
if (!$sql) {
    exit('Could not read myschool.sql');
}

$conn->multi_query($sql);
$errors = [];
$done = 0;
do {
    $done++;
    if ($conn->errno) $errors[] = "Query $done: " . $conn->error;
} while ($conn->next_result());

echo '<pre>';
echo "Connected to: $host:$port / $name\n\n";
if (empty($errors)) {
    echo "✅ Import complete — $done statements executed.\n";
} else {
    echo "⚠️ Completed with " . count($errors) . " error(s):\n";
    foreach ($errors as $e) echo "  - $e\n";
}

$r = $conn->query("SHOW TABLES");
echo "\nTables now in database:\n";
while ($row = $r->fetch_row()) echo "  " . $row[0] . "\n";
echo '</pre>';
