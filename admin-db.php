<?php
/**
 * MindStrong DB Admin — lightweight MySQL browser, PHP 8.x compatible
 * Called from dbview.php which defines MS_DB_ADMIN and sets $DB_SERVER/$DB_USER/$DB_PASS/$DB_NAME
 */
if (!defined('MS_DB_ADMIN')) { http_response_code(403); exit('Forbidden'); }

[$host, $port] = array_pad(explode(':', $DB_SERVER, 2), 2, '3306');
$port = (int)$port;

$error = '';
$conn = null;
try {
    $conn = new mysqli($host, $DB_USER, $DB_PASS, $DB_NAME, $port);
    if ($conn->connect_error) throw new Exception($conn->connect_error);
} catch (Exception $e) {
    $error = $e->getMessage();
}

$action  = $_GET['action'] ?? 'tables';
$table   = $_GET['table']  ?? '';
$sql     = $_POST['sql']   ?? '';
$qresult = null; $qerror = ''; $qcols = []; $qrows = [];

if ($sql && $conn) {
    $res = $conn->query($sql);
    if ($res === false) { $qerror = $conn->error; }
    elseif ($res === true) { $qresult = "OK — affected rows: " . $conn->affected_rows; }
    else { $qcols = array_column($res->fetch_fields(), 'name'); while ($r = $res->fetch_row()) $qrows[] = $r; }
}

$tables = [];
if ($conn) { $r = $conn->query("SHOW TABLES"); if ($r) while ($row = $r->fetch_row()) $tables[] = $row[0]; }

$rows = []; $cols = [];
if ($conn && $table && $action === 'browse') {
    $res = $conn->query("SELECT * FROM `" . $conn->real_escape_string($table) . "` LIMIT 200");
    if ($res) { $cols = array_column($res->fetch_fields(), 'name'); while ($r = $res->fetch_assoc()) $rows[] = $r; }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MindStrong DB Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#05050f;color:#e0e0ff;font-family:'Segoe UI',system-ui,sans-serif;display:flex;min-height:100vh}
#sidebar{width:220px;min-height:100vh;background:#0a0a20;border-right:1px solid #1e1e3f;padding:16px;flex-shrink:0;overflow-y:auto}
#sidebar h2{font-size:.75rem;color:#888aaa;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}
#sidebar a{display:block;padding:6px 10px;border-radius:7px;color:#c0c0ee;text-decoration:none;font-size:.82rem;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#sidebar a:hover,#sidebar a.active{background:#1a1a3f;color:#fff}
.sep{margin:10px 0;border-top:1px solid #1e1e3f}
#main{flex:1;padding:24px;overflow:auto}
h1{font-size:1.2rem;margin-bottom:16px;color:#fff}
.card{background:#0f0f2a;border:1px solid #1e1e3f;border-radius:12px;padding:20px;margin-bottom:20px}
.badge{display:inline-block;background:#1e1e3f;border-radius:5px;padding:2px 8px;font-size:.72rem;color:#888aaa;margin-left:6px}
textarea{width:100%;padding:10px;background:#0a0a20;border:1px solid #1e1e3f;border-radius:8px;color:#e0e0ff;font-family:monospace;font-size:.85rem;resize:vertical;min-height:80px;outline:none}
textarea:focus{border-color:#6366f1}
button{padding:8px 18px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:8px;color:#fff;font-weight:700;cursor:pointer;font-size:.85rem}
.err{color:#ef4444;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:10px;margin-top:10px;font-size:.85rem}
.ok{color:#4ade80;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);border-radius:8px;padding:10px;margin-top:10px;font-size:.85rem}
.tbl{width:100%;border-collapse:collapse;font-size:.82rem}
.tbl th{background:#1a1a3f;color:#888aaa;text-align:left;padding:7px 10px;font-size:.72rem;text-transform:uppercase;position:sticky;top:0}
.tbl td{padding:6px 10px;border-bottom:1px solid #1a1a3f;color:#c0c0ee;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tbl tr:hover td{background:#0a0a20}
.conn-ok{color:#4ade80;font-size:.8rem;margin-bottom:14px}
.conn-err{color:#ef4444;font-size:.8rem;margin-bottom:14px;background:rgba(239,68,68,.08);padding:10px;border-radius:8px}
</style>
</head>
<body>
<div id="sidebar">
  <div style="font-weight:900;font-size:.95rem;color:#fff;margin-bottom:2px">MindStrong</div>
  <div style="font-size:.72rem;color:#444;margin-bottom:14px">Database Admin</div>
  <h2>Tables</h2>
  <?php foreach ($tables as $t): ?>
  <a href="?action=browse&table=<?= urlencode($t) ?>"<?= ($table===$t&&$action==='browse')?' class="active"':'' ?>><?= htmlspecialchars($t) ?></a>
  <?php endforeach; ?>
  <?php if (!$tables && !$error): ?>
  <div style="color:#444;font-size:.78rem;padding:6px 10px">No tables yet</div>
  <?php endif; ?>
  <div class="sep"></div>
  <a href="?action=sql"<?= $action==='sql'?' class="active"':'' ?>>&#9654; SQL Query</a>
  <a href="?action=tables"<?= $action==='tables'?' class="active"':'' ?>>&#9654; Table List</a>
  <div class="sep"></div>
  <a href="/super-admin.html">&#8592; Dashboard</a>
  <a href="/login.html">&#8592; Login</a>
</div>
<div id="main">
  <h1>
    Database: <span style="color:#6366f1"><?= htmlspecialchars($DB_NAME) ?></span>
    <span class="badge"><?= htmlspecialchars($host) ?>:<?= $port ?></span>
  </h1>
  <?php if ($error): ?>
    <div class="conn-err">&#10006; Connection failed: <?= htmlspecialchars($error) ?></div>
  <?php else: ?>
    <div class="conn-ok">&#10003; Connected as <strong><?= htmlspecialchars($DB_USER) ?></strong> &mdash; <?= count($tables) ?> tables</div>
  <?php endif; ?>

  <?php if ($action === 'tables'): ?>
  <div class="card">
    <table class="tbl">
      <tr><th>Table</th><th>Rows</th><th></th></tr>
      <?php foreach ($tables as $t):
        $cnt = 0;
        if ($conn) { $cr = $conn->query("SELECT COUNT(*) FROM `".$conn->real_escape_string($t)."`"); if ($cr) $cnt = (int)$cr->fetch_row()[0]; }
      ?>
      <tr>
        <td><?= htmlspecialchars($t) ?></td>
        <td><?= number_format($cnt) ?></td>
        <td><a href="?action=browse&table=<?= urlencode($t) ?>" style="color:#6366f1">Browse</a>
            &nbsp;<a href="?action=sql&sql=<?= urlencode("SELECT * FROM `$t` LIMIT 50") ?>" style="color:#8b5cf6">SQL</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$tables): ?>
      <tr><td colspan="3" style="color:#444;text-align:center;padding:20px">
        Database is empty — import myschool.sql to set up tables
      </td></tr>
      <?php endif; ?>
    </table>
  </div>

  <?php elseif ($action === 'sql'): ?>
  <div class="card">
    <form method="POST" action="?action=sql">
      <textarea name="sql" placeholder="SELECT * FROM admin LIMIT 10"><?= htmlspecialchars($sql) ?></textarea>
      <button type="submit" style="margin-top:10px">&#9654; Run</button>
    </form>
    <?php if ($qerror): ?><div class="err"><?= htmlspecialchars($qerror) ?></div><?php endif; ?>
    <?php if ($qresult): ?><div class="ok"><?= htmlspecialchars($qresult) ?></div><?php endif; ?>
    <?php if ($qrows): ?>
    <div style="overflow:auto;margin-top:14px"><table class="tbl">
      <tr><?php foreach ($qcols as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?></tr>
      <?php foreach ($qrows as $r): ?><tr><?php foreach ($r as $v): ?><td title="<?= htmlspecialchars((string)$v) ?>"><?= htmlspecialchars((string)($v ?? 'NULL')) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </div>

  <?php elseif ($action === 'browse' && $table): ?>
  <div class="card">
    <div style="margin-bottom:12px">
      <strong><?= htmlspecialchars($table) ?></strong>
      <span class="badge"><?= count($rows) ?> rows shown</span>
      <a href="?action=sql&sql=<?= urlencode("SELECT * FROM `$table` LIMIT 50") ?>" style="color:#6366f1;font-size:.8rem;margin-left:12px">Edit in SQL</a>
    </div>
    <?php if ($cols): ?>
    <div style="overflow:auto"><table class="tbl">
      <tr><?php foreach ($cols as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?></tr>
      <?php foreach ($rows as $r): ?><tr><?php foreach ($cols as $c): $v = $r[$c] ?? null; ?><td title="<?= htmlspecialchars((string)$v) ?>"><?= htmlspecialchars((string)($v ?? 'NULL')) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </table></div>
    <?php else: ?><div style="color:#555">Table is empty.</div><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
