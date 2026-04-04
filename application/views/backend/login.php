<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$login_html_path = FCPATH . 'login.html';
$login_error = $this->session->flashdata('login_error') ?: '';
$reset_success = $this->session->flashdata('reset_success') ?: '';
$reset_error = $this->session->flashdata('reset_error') ?: '';

if (!is_file($login_html_path)) {
    echo '<!DOCTYPE html><html><body><h1>Login page unavailable</h1></body></html>';
    return;
}

$html = file_get_contents($login_html_path);
if ($html === false) {
    echo '<!DOCTYPE html><html><body><h1>Login page unavailable</h1></body></html>';
    return;
}

$inject = "<script>\n";

if ($login_error !== '') {
    $inject .= 'window.__LEGACY_LOGIN_ERROR = ' . json_encode('Error: ' . $login_error) . ";\n";
}
if ($reset_success !== '') {
    $inject .= 'window.__LEGACY_RESET_SUCCESS = ' . json_encode('Success: ' . $reset_success) . ";\n";
}
if ($reset_error !== '') {
    $inject .= 'window.__LEGACY_RESET_ERROR = ' . json_encode('Error: ' . $reset_error) . ";\n";
}

$inject .= <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  if (window.__LEGACY_LOGIN_ERROR) {
    var loginAlert = document.getElementById('login-alert');
    if (loginAlert) {
      loginAlert.textContent = window.__LEGACY_LOGIN_ERROR;
      loginAlert.classList.add('show');
    }
  }

  if (window.__LEGACY_RESET_SUCCESS || window.__LEGACY_RESET_ERROR) {
    if (typeof switchForm === 'function') {
      switchForm('forgot');
    }

    var forgotSuccess = document.getElementById('forgot-success');
    var forgotError = document.getElementById('forgot-email-err');

    if (window.__LEGACY_RESET_SUCCESS && forgotSuccess) {
      forgotSuccess.textContent = window.__LEGACY_RESET_SUCCESS;
      forgotSuccess.style.display = 'flex';
      forgotSuccess.classList.add('show');
    }

    if (window.__LEGACY_RESET_ERROR && forgotError) {
      forgotError.textContent = window.__LEGACY_RESET_ERROR;
      forgotError.classList.add('show');
    }
  }
});
JS;

$inject .= "\n</script>";

if (stripos($html, '</body>') !== false) {
    echo str_ireplace('</body>', $inject . "\n</body>", $html);
    return;
}

echo $html . $inject;
