<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Learning extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        require_once APPPATH . '..' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'learning-common.php';

        header('Content-Type: application/json; charset=UTF-8');

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

    private function json($data, $code = 200)
    {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    private function requireSession($roleKey)
    {
        if ($this->session->userdata($roleKey) != '1') {
            $this->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function teacher_curriculum_catalog()
    {
        $this->json(['courses' => ms_learning_catalog()]);
    }

    public function student_learning()
    {
        $this->requireSession('student_login');
        $studentId = (int)($this->session->userdata('student_id') ?: $this->session->userdata('user_id'));
        $this->json(['courses' => ms_learning_bundle_for_student($studentId)]);
    }

    public function parent_courses()
    {
        $this->requireSession('parent_login');
        $studentId = (int)($this->input->get('student_id', true) ?: 0);
        if ($studentId < 1) {
            $this->json(['courses' => []]);
        }
        $this->json(['student_id' => $studentId, 'courses' => ms_learning_bundle_for_student($studentId)]);
    }
}
