<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * One-time SQL import tool — DELETE THIS FILE after import is done.
 */

// ── Connect ──────────────────────────────────────────────────────────────────
$db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if ($db_url) {
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

$conn = new mysqli($host, $user, $pass, $name, $port);
$conn_ok = !$conn->connect_error;
$conn_err = $conn->connect_error ?? '';

// ── Handle Upload ─────────────────────────────────────────────────────────────
$results = [];
$import_done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sqlfile']) && $conn_ok) {
    $sql = file_get_contents($_FILES['sqlfile']['tmp_name']);

    // Split on semicolons outside of quoted strings (simple approach for phpMyAdmin dumps)
    $conn->multi_query($sql);
    $ok = 0; $fail = 0;
    do {
        if ($conn->errno) {
            $results[] = ['err', $conn->error];
            $fail++;
        } else {
            $ok++;
        }
        if ($conn->more_results()) $conn->next_result();
        else break;
    } while (true);

    // Flush remaining
    while ($conn->more_results()) { $conn->next_result(); }

    $import_done = true;
    $results[] = ['ok', "Import finished: $ok statements OK, $fail errors."];
}

// ── Table count ───────────────────────────────────────────────────────────────
$table_count = 0;
if ($conn_ok) {
    $r = $conn->query("SHOW TABLES");
    if ($r) $table_count = $r->num_rows;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SQL Import — MindStrong</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#05050f;color:#e0e0ff;font-family:'Segoe UI',system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.box{background:#0f0f2a;border:1px solid #1e1e3f;border-radius:16px;padding:36px;width:500px;max-width:100%}
h1{font-size:1.2rem;margin-bottom:6px}
p{color:#888aaa;font-size:.85rem;margin-bottom:24px}
.conn{font-size:.82rem;padding:10px 14px;border-radius:8px;margin-bottom:20px}
.conn.ok{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80}
.conn.err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#ef4444}
label{display:block;font-size:.75rem;font-weight:700;color:#888aaa;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px}
input[type=file]{width:100%;padding:10px;background:#0a0a20;border:1px solid #1e1e3f;border-radius:8px;color:#e0e0ff;font-size:.85rem;cursor:pointer}
button{width:100%;margin-top:16px;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:10px;color:#fff;font-weight:800;font-size:.95rem;cursor:pointer}
.result{margin-top:16px;font-size:.82rem;padding:10px 14px;border-radius:8px}
.result.ok{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80}
.result.err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#ef4444}
.warn{margin-top:20px;padding:12px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;font-size:.78rem;color:#fbbf24;line-height:1.6}
.links{margin-top:20px;display:flex;gap:12px;font-size:.82rem}
.links a{color:#6366f1;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <h1>SQL Import Tool</h1>
  <p>Upload <strong>myschool.sql</strong> to populate the Railway database.</p>

  <?php if ($conn_ok): ?>
  <div class="conn ok">&#10003; Connected to <strong><?= htmlspecialchars($name) ?></strong> on <?= htmlspecialchars($host) ?>:<?= $port ?> &mdash; <?= $table_count ?> tables</div>
  <?php else: ?>
  <div class="conn err">&#10006; Connection failed: <?= htmlspecialchars($conn_err) ?></div>
  <?php endif; ?>

  <?php if (!$import_done): ?>
  <form method="POST" enctype="multipart/form-data">
    <label>SQL File</label>
    <input type="file" name="sqlfile" accept=".sql">
    <button type="submit">&#9654; Import SQL</button>
  </form>
  <?php endif; ?>

  <?php foreach ($results as [$type, $msg]): ?>
  <div class="result <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>

  <?php if ($import_done): ?>
  <div class="warn">
    &#9888; Import complete. <strong>Delete sql-import.php from your repo now</strong> — it has no auth protection.
  </div>
  <?php endif; ?>

  <div class="links">
    <a href="/dbview.php">&#8594; DB Admin</a>
    <a href="/login.html">&#8594; Login</a>
    <a href="/super-admin.html">&#8594; Dashboard</a>
  </div>
</div>
</body>
</html>
