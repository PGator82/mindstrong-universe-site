<?php
declare(strict_types=1);

function env_value(string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value !== false && $value !== null && $value !== '') ? trim((string)$value) : $default;
}

function db_config(): array {
    $host = env_value('MYSQLHOST', 'localhost');
    $port = (int)env_value('MYSQLPORT', '3306');
    $user = env_value('MYSQLUSER', 'root');
    $pass = env_value('MYSQLPASSWORD', '');
    $name = env_value('MYSQLDATABASE', 'railway');

    if (env_value('CI_ENV', 'development') === 'production' && str_contains($host, 'rlwy.net')) {
        $host = 'mysql';
        $port = 3306;
    }

    return compact('host', 'port', 'user', 'pass', 'name');
}

function fetch_all_assoc(mysqli $db, string $sql): array {
    $result = $db->query($sql);
    if (!$result) {
        throw new RuntimeException($db->error);
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}

$cfg = db_config();
$db = mysqli_init();
mysqli_report(MYSQLI_REPORT_OFF);
mysqli_options($db, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (!@mysqli_real_connect($db, $cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port'])) {
    fwrite(STDERR, "Could not connect to MySQL at {$cfg['host']}:{$cfg['port']} ({$cfg['name']})\n");
    fwrite(STDERR, mysqli_connect_error() . "\n");
    exit(1);
}

$db->set_charset('utf8mb4');
$database = $db->real_escape_string($cfg['name']);
$tables = ['admin', 'teacher', 'student', 'parent', 'librarian', 'accountant'];

echo "SIS identity index audit for `{$cfg['name']}`\n\n";

foreach ($tables as $table) {
    $safeTable = $db->real_escape_string($table);
    $indexes = fetch_all_assoc(
        $db,
        "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = '{$safeTable}'
         ORDER BY INDEX_NAME, SEQ_IN_INDEX"
    );
    $columns = fetch_all_assoc(
        $db,
        "SELECT COLUMN_NAME, COLUMN_KEY
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = '{$safeTable}'
         ORDER BY ORDINAL_POSITION"
    );

    $emailIndexes = array_values(array_filter($indexes, static function (array $row): bool {
        return strtolower($row['COLUMN_NAME']) === 'email';
    }));
    $hasEmailColumn = (bool)array_filter($columns, static function (array $row): bool {
        return strtolower($row['COLUMN_NAME']) === 'email';
    });

    echo "[{$table}]\n";
    echo '  email column: ' . ($hasEmailColumn ? 'yes' : 'no') . "\n";

    if (!$hasEmailColumn) {
        echo "  status: skip (no email column)\n\n";
        continue;
    }

    if (!$emailIndexes) {
        echo "  status: MISSING email index\n";
        echo "  suggested SQL: CREATE INDEX idx_{$table}_email ON `{$table}` (`email`);\n\n";
        continue;
    }

    $indexLabels = array_map(static function (array $row): string {
        return $row['INDEX_NAME'] . ($row['NON_UNIQUE'] === '0' ? ' [unique]' : '');
    }, $emailIndexes);

    echo '  status: OK' . "\n";
    echo '  indexes: ' . implode(', ', array_unique($indexLabels)) . "\n\n";
}

$db->close();
