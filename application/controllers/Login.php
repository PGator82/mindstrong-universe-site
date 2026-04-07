<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/*
 *  MindStrong Universe — Login Controller
 *  Auth: bcrypt with transparent SHA-1 migration for legacy passwords.
 *  Brute-force: session-based rate-limit (5 attempts / 15 min per IP).
 */

class Login extends CI_Controller {

    function __construct() {
        parent::__construct();
        /* cache control */
        $this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header("Expires: Mon, 26 Jul 2010 05:00:00 GMT");
    }

    private function _ensure_session() {
        if (!isset($this->session)) {
            $this->load->library('session');
        }
    }

    private function _ensure_db() {
        if (!isset($this->db)) {
            $this->load->database();
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Verify a submitted password against a stored hash.
     * Supports bcrypt (password_hash) and legacy SHA-1.
     * Returns true on match; also returns $upgraded_hash if the hash was
     * upgraded to bcrypt (so caller can persist it).
     */
    private function _verify_password($submitted, $stored, &$upgraded_hash = null) {
        $upgraded_hash = null;
        if (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0) {
            return password_verify($submitted, $stored);
        }
        // Legacy SHA-1 path
        if ($stored === sha1($submitted)) {
            $upgraded_hash = password_hash($submitted, PASSWORD_BCRYPT);
            return true;
        }
        return false;
    }

    /**
     * Check and update the rate-limit counter for the current IP.
     * Returns false if the request should be blocked.
     */
    private function _check_rate_limit() {
        $this->_ensure_session();
        $ip      = $this->input->ip_address();
        $key     = 'lr_' . md5($ip);
        $rate    = $this->session->userdata($key) ?: ['c' => 0, 'ts' => time()];

        // Reset window after 15 minutes
        if (time() - $rate['ts'] > 900) {
            $rate = ['c' => 0, 'ts' => time()];
        }

        if ($rate['c'] >= 5) {
            $wait = ceil((900 - (time() - $rate['ts'])) / 60);
            $this->session->set_flashdata('login_error',
                'Too many login attempts. Please wait ' . $wait . ' minute(s) and try again.');
            redirect(site_url('login'), 'refresh');
            return false;
        }

        $rate['c']++;
        $this->session->set_userdata($key, $rate);
        return true;
    }

    private function _clear_rate_limit() {
        $this->_ensure_session();
        $ip  = $this->input->ip_address();
        $key = 'lr_' . md5($ip);
        $this->session->unset_userdata($key);
    }

    // ─────────────────────────────────────────────────────────────────────────

    // Default: redirect already-logged-in users to their dashboard
    public function index() {
        $this->_ensure_session();
        if ($this->session->userdata('admin_login') == 1)
            redirect(site_url('admin/dashboard'), 'refresh');
        if ($this->session->userdata('teacher_login') == 1)
            redirect(site_url('teacher/dashboard'), 'refresh');
        if ($this->session->userdata('student_login') == 1)
            redirect(site_url('student/dashboard'), 'refresh');
        if ($this->session->userdata('parent_login') == 1)
            redirect(site_url('parents/dashboard'), 'refresh');
        if ($this->session->userdata('librarian_login') == 1)
            redirect(site_url('librarian/dashboard'), 'refresh');
        if ($this->session->userdata('accountant_login') == 1)
            redirect(site_url('accountant/dashboard'), 'refresh');

        $this->load->view('backend/login');
    }

    // Validate login from form POST (with rate-limit + bcrypt migration)
    function validate_login() {
        if (!$this->_check_rate_limit()) return;

        $this->_ensure_db();
        $this->_ensure_session();
        $email    = $this->input->post('email');
        $password = $this->input->post('password');
        $role_hint = strtolower(trim((string) $this->input->post('role')));

        $roles = [
            'admin'      => ['session_key' => 'admin_login',      'id_col' => 'admin_id',      'redirect' => 'admin/dashboard'],
            'teacher'    => ['session_key' => 'teacher_login',    'id_col' => 'teacher_id',    'redirect' => 'teacher/dashboard'],
            'student'    => ['session_key' => 'student_login',    'id_col' => 'student_id',    'redirect' => 'student/dashboard'],
            'parent'     => ['session_key' => 'parent_login',     'id_col' => 'parent_id',     'redirect' => 'parents/dashboard'],
            'librarian'  => ['session_key' => 'librarian_login',  'id_col' => 'librarian_id',  'redirect' => 'librarian/dashboard'],
            'accountant' => ['session_key' => 'accountant_login', 'id_col' => 'accountant_id', 'redirect' => 'accountant/dashboard'],
        ];

        if ($role_hint !== '' && isset($roles[$role_hint])) {
            $roles = [$role_hint => $roles[$role_hint]] + $roles;
        }

        foreach ($roles as $table => $cfg) {
            $query = $this->db->limit(1)->get_where($table, array('email' => $email));
            if ($query->num_rows() === 0) continue;

            $row     = $query->row();
            $new_hash = null;
            if (!$this->_verify_password($password, $row->password, $new_hash)) continue;

            // Upgrade legacy SHA-1 hash to bcrypt in-place
            if ($new_hash !== null) {
                $this->db->where('email', $email);
                $this->db->update($table, array('password' => $new_hash));
            }

            $this->_clear_rate_limit();
            $this->session->set_userdata($cfg['session_key'], '1');
            $this->session->set_userdata($cfg['id_col'], $row->{$cfg['id_col']});
            $this->session->set_userdata('login_user_id', $row->{$cfg['id_col']});
            $this->session->set_userdata('name', $row->name);
            $this->session->set_userdata('login_type', $table);
            redirect(site_url($cfg['redirect']), 'refresh');
            return;
        }

        // No matching credential found
        $this->session->set_flashdata('login_error', get_phrase('invalid_login'));
        redirect(site_url('login'), 'refresh');
    }

    // 404 page
    function four_zero_four() {
        $this->load->view('four_zero_four');
    }

    // Show forgot-password form (re-uses the toggle panel on the login view)
    function forgot_password() {
        $this->_ensure_session();
        $this->load->view('backend/login');
    }

    // Handle password-reset POST: find email across all roles, generate new password, email it
    function reset_password() {
        $this->_ensure_db();
        $this->_ensure_session();
        $email = $this->input->post('email');

        $tables = ['admin', 'student', 'teacher', 'parent', 'librarian', 'accountant'];

        foreach ($tables as $table) {
            $query = $this->db->get_where($table, array('email' => $email));
            if ($query->num_rows() === 0) continue;

            // Generate a secure random password (8 chars)
            $new_password = substr(bin2hex(random_bytes(6)), 0, 8);
            $new_hash     = password_hash($new_password, PASSWORD_BCRYPT);

            $this->db->where('email', $email);
            $this->db->update($table, array('password' => $new_hash));

            $this->load->model('email_model');
            $this->email_model->password_reset_email($new_password, $table, $email);

            $this->session->set_flashdata('reset_success',
                get_phrase('please_check_your_email_for_new_password'));
            redirect(site_url('login'), 'refresh');
            return;
        }

        $this->session->set_flashdata('reset_error',
            get_phrase('password_reset_was_failed'));
        redirect(site_url('login'), 'refresh');
    }

    // Logout
    function logout() {
        $this->_ensure_session();
        $this->session->sess_destroy();
        $this->session->set_flashdata('logout_notification', 'logged_out');
        redirect(site_url('login'), 'refresh');
    }

}
