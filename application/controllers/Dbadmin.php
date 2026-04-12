<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dbadmin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
    }

    public function index()
    {
        if ((string)$this->session->userdata('admin_login') !== '1') {
            redirect(site_url('login.html?error=' . rawurlencode('Admin login required.')), 'refresh');
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        [$DB_SERVER, $DB_USER, $DB_PASS, $DB_NAME] = $this->dbConfigFromEnv();

        define('MS_DB_ADMIN', true);
        include FCPATH . 'admin-db.php';
    }

    private function dbConfigFromEnv()
    {
        $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';

        if ($dbUrl && strpos($dbUrl, '${{') === false && strpos($dbUrl, 'mysql') === 0) {
            $parts = parse_url($dbUrl);
            $host = $parts['host'] ?? 'localhost';
            $port = (int)($parts['port'] ?? 3306);
            $user = isset($parts['user']) ? urldecode($parts['user']) : 'root';
            $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            $name = ltrim($parts['path'] ?? '/railway', '/');
        } else {
            $host = $this->cleanEnv(getenv('MYSQLHOST') ?: '') ?: 'localhost';
            $port = (int)($this->cleanEnv(getenv('MYSQLPORT') ?: '') ?: 3306);
            $user = $this->cleanEnv(getenv('MYSQLUSER') ?: '') ?: 'root';
            $pass = $this->cleanEnv(getenv('MYSQLPASSWORD') ?: '') ?: '';
            $name = $this->cleanEnv(getenv('MYSQLDATABASE') ?: '') ?: 'railway';
        }

        return [$host . ':' . $port, $user, $pass, $name];
    }

    private function cleanEnv($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (strpos($value, ' ') !== false) {
            $value = explode(' ', $value, 2)[0];
        }
        if (strpos($value, '=') !== false && strpos($value, '.') === false) {
            $value = explode('=', $value, 2)[1];
        }
        return trim($value);
    }
}
