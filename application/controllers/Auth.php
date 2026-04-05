<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('session');

        $allowed = array_filter([
            getenv('CORS_ORIGIN') ?: null,
            'https://mindstrong-universe-school-production.up.railway.app',
            'https://pgator82.github.io',
        ]);
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif ($origin !== '') {
            header('Access-Control-Allow-Origin: https://mindstrong-universe-school-production.up.railway.app');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Vary: Origin');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    private function roleMap() {
        return [
            'admin' => [
                'table' => 'admin',
                'id_field' => 'admin_id',
                'session_key' => 'admin_login',
                'redirect' => 'admin/dashboard',
            ],
            'teacher' => [
                'table' => 'teacher',
                'id_field' => 'teacher_id',
                'session_key' => 'teacher_login',
                'redirect' => 'teacher.html',
            ],
            'student' => [
                'table' => 'student',
                'id_field' => 'student_id',
                'session_key' => 'student_login',
                'redirect' => 'dashboard.html',
            ],
            'parent' => [
                'table' => 'parent',
                'id_field' => 'parent_id',
                'session_key' => 'parent_login',
                'redirect' => 'parent.html',
            ],
            'librarian' => [
                'table' => 'librarian',
                'id_field' => 'librarian_id',
                'session_key' => 'librarian_login',
                'redirect' => 'librarian/dashboard',
            ],
            'accountant' => [
                'table' => 'accountant',
                'id_field' => 'accountant_id',
                'session_key' => 'accountant_login',
                'redirect' => 'accountant/dashboard',
            ],
        ];
    }

    private function json($data, $code = 200) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    private function post($key) {
        $val = $this->input->post($key, TRUE);
        return ($val !== FALSE && $val !== null) ? trim($val) : '';
    }

    private function verifyPassword($submitted, $stored, &$upgraded_hash = null) {
        $upgraded_hash = null;
        if (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$2b$') === 0) {
            return password_verify($submitted, $stored);
        }
        if ($stored === sha1($submitted)) {
            $upgraded_hash = password_hash($submitted, PASSWORD_BCRYPT);
            return true;
        }
        return false;
    }

    private function dbClean($val) {
        $val = trim((string)$val);
        if (strpos($val, ' ') !== false) {
            $val = explode(' ', $val, 2)[0];
        }
        if (strpos($val, '=') !== false && strpos($val, '.') === false) {
            $val = explode('=', $val, 2)[1];
        }
        return trim($val);
    }

    private function authDbConfig() {
        $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
        if ($dbUrl && strpos($dbUrl, '${{') === false && strpos($dbUrl, '@') !== false) {
            $parts = parse_url($dbUrl);
            $host = $parts['host'] ?? 'localhost';
            $port = (int)($parts['port'] ?? 3306);
            $user = isset($parts['user']) ? urldecode($parts['user']) : 'root';
            $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            $name = ltrim($parts['path'] ?? '/railway', '/');
        } else {
            $host = $this->dbClean(getenv('MYSQLHOST') ?: '') ?: 'localhost';
            $port = (int)($this->dbClean(getenv('MYSQLPORT') ?: '') ?: 3306);
            $user = $this->dbClean(getenv('MYSQLUSER') ?: '') ?: 'root';
            $pass = $this->dbClean(getenv('MYSQLPASSWORD') ?: '') ?: '';
            $name = $this->dbClean(getenv('MYSQLDATABASE') ?: '') ?: 'railway';
        }

        if ((getenv('CI_ENV') ?: ENVIRONMENT) === 'production') {
            if (strpos($host, '.proxy.rlwy.net') !== false || strpos($host, 'rlwy.net') !== false) {
                $host = 'mysql';
                $port = 3306;
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'name' => $name,
        ];
    }

    private function authDb() {
        static $conn = null;

        if ($conn instanceof mysqli) {
            return $conn;
        }

        $cfg = $this->authDbConfig();
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_init();
        if (!$conn) {
            return null;
        }

        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 5);
        }

        $ok = @mysqli_real_connect(
            $conn,
            $cfg['host'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['name'],
            $cfg['port']
        );

        if (!$ok) {
            return null;
        }

        mysqli_set_charset($conn, 'utf8mb4');
        return $conn;
    }

    private function fetchUserByEmail($conn, $table, $email) {
        $sql = "SELECT * FROM `{$table}` WHERE `email` = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    private function updatePasswordHash($conn, $table, $idField, $idValue, $hash) {
        $sql = "UPDATE `{$table}` SET `password` = ? WHERE `{$idField}` = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'si', $hash, $idValue);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function tryLogin($email, $password, $role = '') {
        $conn = $this->authDb();
        if (!$conn) {
            return ['error' => 'Login service is temporarily unavailable.'];
        }

        $role_map = $this->roleMap();
        $role = strtolower(trim((string)$role));
        $try_roles = ($role !== '' && isset($role_map[$role]))
            ? [$role => $role_map[$role]]
            : $role_map;

        foreach ($try_roles as $role_name => $cfg) {
            $user = $this->fetchUserByEmail($conn, $cfg['table'], $email);
            if (!$user || empty($user['password'])) {
                continue;
            }

            $new_hash = null;
            if (!$this->verifyPassword($password, $user['password'], $new_hash)) {
                continue;
            }

            if ($new_hash !== null) {
                $this->updatePasswordHash($conn, $cfg['table'], $cfg['id_field'], (int)$user[$cfg['id_field']], $new_hash);
            }

            $this->session->set_userdata($cfg['session_key'], '1');
            $this->session->set_userdata($cfg['id_field'], $user[$cfg['id_field']]);
            $this->session->set_userdata('login_user_id', $user[$cfg['id_field']]);
            $this->session->set_userdata('name', isset($user['name']) ? $user['name'] : '');
            $this->session->set_userdata('login_type', $role_name);

            return [
                'success' => true,
                'role' => $role_name,
                'redirect' => $cfg['redirect'],
            ];
        }

        return ['error' => 'Incorrect email or password. Please try again.'];
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $email = $this->post('email');
        $password = $this->post('password');
        $role = $this->post('role');

        if ($email === '' || $password === '') {
            $this->json(['error' => 'Email and password are required'], 400);
        }

        $result = $this->tryLogin($email, $password, $role);
        if (!empty($result['success'])) {
            $this->json($result);
        }

        $status = ($result['error'] ?? '') === 'Login service is temporarily unavailable.' ? 503 : 401;
        $this->json(['error' => $result['error'] ?? 'Incorrect email or password. Please try again.'], $status);
    }

    public function validate_login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(site_url('login'), 'refresh');
            return;
        }

        $email = $this->post('email');
        $password = $this->post('password');
        $role = $this->post('role');

        if ($email === '' || $password === '') {
            $this->session->set_flashdata('login_error', get_phrase('invalid_login'));
            redirect(site_url('login'), 'refresh');
            return;
        }

        $result = $this->tryLogin($email, $password, $role);
        if (!empty($result['success'])) {
            redirect(site_url($result['redirect']), 'refresh');
            return;
        }

        $this->session->set_flashdata('login_error', $result['error'] ?? get_phrase('invalid_login'));
        redirect(site_url('login'), 'refresh');
    }

    public function logout() {
        $this->session->sess_destroy();
        $this->json(['success' => true, 'redirect' => 'login.html']);
    }
}
