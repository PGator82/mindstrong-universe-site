<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Portal extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
    }

    public function admin()
    {
        if ($this->session->userdata('admin_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }
        redirect('/admin.html', 'refresh');
    }

    public function teacher()
    {
        if ($this->session->userdata('teacher_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }
        redirect('/teacher.html', 'refresh');
    }

    public function student()
    {
        if ($this->session->userdata('student_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }
        redirect('/dashboard.html', 'refresh');
    }

    public function parent()
    {
        if ($this->session->userdata('parent_login') != 1) {
            redirect(site_url('login'), 'refresh');
        }
        redirect('/parent.html', 'refresh');
    }
}
