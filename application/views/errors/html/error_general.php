<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Error · MindStrong Universe</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Syne:wght@400;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg0:#070A12;--bg1:#0B1020;--brand:#7C3AED;--brand2:#22D3EE;--warn:#FBBF24;--txt:rgba(255,255,255,.92);--muted:rgba(255,255,255,.55);--stroke:rgba(255,255,255,.10);}
html,body{height:100%;background:var(--bg0);color:var(--txt);font-family:'Syne',sans-serif;}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;text-align:center;}
.glow{position:fixed;inset:0;pointer-events:none;background:radial-gradient(700px 500px at 50% 20%,rgba(251,191,36,.07),transparent 70%),radial-gradient(400px 300px at 80% 80%,rgba(124,58,237,.08),transparent 70%);}
.badge{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;padding:6px 16px;border-radius:999px;border:1px solid rgba(251,191,36,.3);color:var(--warn);margin-bottom:24px;display:inline-block;}
.icon{font-size:56px;margin-bottom:16px;}
h2{font-family:'Orbitron',sans-serif;font-size:clamp(18px,3vw,26px);font-weight:700;letter-spacing:-.3px;margin-bottom:16px;}
.msg{font-size:14px;line-height:1.7;color:var(--muted);max-width:50ch;margin:0 auto 32px;font-family:'JetBrains Mono',monospace;}
.btn{display:inline-block;padding:12px 28px;border-radius:999px;background:linear-gradient(135deg,var(--brand),#6d28d9);color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:14px;text-decoration:none;transition:opacity .2s;}
.btn:hover{opacity:.85;}
</style>
</head>
<body>
<div class="glow"></div>
<span class="badge">System Error</span>
<div class="icon">⚠️</div>
<h2><?php echo $heading; ?></h2>
<div class="msg"><?php echo $message; ?></div>
<a class="btn" href="/">&#8592; Back to Base</a>
</body>
</html>
