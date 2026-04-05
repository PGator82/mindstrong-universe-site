<?php
$system_name  = $this->db->get_where('settings', array('type' => 'system_name'))->row()->description;
$header_logo  = $this->frontend_model->get_frontend_general_settings('header_logo');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Login · MindStrong Universe</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
  <script src="<?php echo base_url('assets/js/toastr.js'); ?>"></script>
  <link rel="stylesheet" href="<?php echo base_url('assets/css/custom.css'); ?>">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--bg0:#070A12;--brand:#7C3AED;--brand2:#22D3EE;--txt:rgba(255,255,255,.92);--muted:rgba(255,255,255,.55);--stroke:rgba(255,255,255,.10);--card:rgba(255,255,255,.05);}
    html,body{min-height:100vh;background:var(--bg0);color:var(--txt);font-family:'Syne',sans-serif;}
    body{display:flex;align-items:center;justify-content:center;padding:24px;}
    .glow{position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(700px 600px at 30% 20%,rgba(124,58,237,.12),transparent 70%),radial-gradient(500px 400px at 80% 80%,rgba(34,211,238,.07),transparent 70%);}
    .card{position:relative;z-index:1;width:100%;max-width:440px;background:rgba(11,16,32,.85);border:1px solid var(--stroke);border-radius:24px;padding:40px 36px;backdrop-filter:blur(16px);box-shadow:0 24px 80px rgba(0,0,0,.5);}
    .brand{display:flex;align-items:center;gap:10px;margin-bottom:32px;}
    .dot{width:12px;height:12px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand2));box-shadow:0 0 18px rgba(124,58,237,.6);}
    .brand-name{font-family:'Orbitron',sans-serif;font-size:14px;font-weight:700;letter-spacing:.5px;}
    h2{font-family:'Orbitron',sans-serif;font-size:22px;font-weight:700;margin-bottom:6px;}
    .sub{font-size:13px;color:var(--muted);margin-bottom:28px;}
    label{display:block;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;}
    input[type=email],input[type=password]{width:100%;padding:12px 16px;background:rgba(255,255,255,.06);border:1px solid var(--stroke);border-radius:10px;color:var(--txt);font-family:'Syne',sans-serif;font-size:14px;margin-bottom:18px;transition:border-color .2s;}
    input:focus{outline:none;border-color:var(--brand);}
    .btn{width:100%;padding:13px;border-radius:12px;background:linear-gradient(135deg,var(--brand),#6d28d9);color:#fff;font-family:'Syne',sans-serif;font-weight:700;font-size:15px;border:none;cursor:pointer;transition:opacity .2s;margin-top:4px;}
    .btn:hover{opacity:.88;}
    .links{display:flex;justify-content:space-between;margin-top:20px;font-size:13px;}
    .links a{color:var(--muted);text-decoration:none;transition:color .2s;}
    .links a:hover{color:var(--txt);}
    #forgot_password_area{display:none;}
  </style>
</head>
<body>
<div class="glow"></div>
<div class="card">
  <div class="brand">
    <span class="dot"></span>
    <span class="brand-name">MindStrong Universe</span>
  </div>

  <!-- Login Form -->
  <div id="login_area">
    <h2><?php echo get_phrase('login'); ?></h2>
    <p class="sub">Welcome back. Enter your credentials to continue.</p>
    <form action="<?php echo site_url('login/validate_login'); ?>" method="post">
      <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
      <label for="email"><?php echo get_phrase('email'); ?></label>
      <input id="email" type="email" name="email" placeholder="you@example.com" required/>
      <label for="Password"><?php echo get_phrase('password'); ?></label>
      <input id="Password" type="password" name="password" placeholder="••••••••" required/>
      <button type="submit" class="btn"><?php echo get_phrase('login'); ?></button>
    </form>
    <div class="links">
      <a href="<?php echo base_url(); ?>">&#8592; <?php echo get_phrase('back_to_website'); ?></a>
      <a href="#" id="forgot_password_button" onclick="toggleView(this)"><?php echo get_phrase('forgot_password'); ?>?</a>
    </div>
  </div>

  <!-- Forgot Password Form -->
  <div id="forgot_password_area">
    <h2><?php echo get_phrase('forgot_password'); ?></h2>
    <p class="sub">Enter your email and we'll send a new password.</p>
    <form action="<?php echo site_url('login/reset_password'); ?>" method="post">
      <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
      <label for="forgot_password_email"><?php echo get_phrase('email'); ?></label>
      <input id="forgot_password_email" type="email" name="email" placeholder="you@example.com" required/>
      <button type="submit" class="btn"><?php echo get_phrase('send_mail'); ?></button>
    </form>
    <div class="links">
      <a href="#" id="login_button" onclick="toggleView(this)">&#8592; <?php echo get_phrase('back_to_login'); ?></a>
    </div>
  </div>
</div>

<script src="<?php echo base_url('assets/login/jquery-3.3.1.min.js'); ?>"></script>
<script>
function toggleView(elem) {
  if (elem.id === 'forgot_password_button') {
    document.getElementById('login_area').style.display = 'none';
    document.getElementById('forgot_password_area').style.display = 'block';
  } else if (elem.id === 'login_button') {
    document.getElementById('login_area').style.display = 'block';
    document.getElementById('forgot_password_area').style.display = 'none';
  }
}
</script>
<?php if ($this->session->flashdata('flash_message')): ?>
<script>toastr.success('<?php echo $this->session->flashdata("flash_message"); ?>');</script>
<?php endif; ?>
<?php if ($this->session->flashdata('error_message')): ?>
<script>toastr.error('<?php echo $this->session->flashdata("error_message"); ?>');</script>
<?php endif; ?>
<?php if ($this->session->flashdata('login_error')): ?>
<script>toastr.error('<?php echo $this->session->flashdata("login_error"); ?>');</script>
<?php endif; ?>
<?php if ($this->session->flashdata('reset_success')): ?>
<script>toastr.success('<?php echo $this->session->flashdata("reset_success"); ?>');</script>
<?php endif; ?>
<?php if ($this->session->flashdata('reset_error')): ?>
<script>toastr.error('<?php echo $this->session->flashdata("reset_error"); ?>');</script>
<?php endif; ?>
</body>
</html>
