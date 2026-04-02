<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Portal extends CI_Controller
{
    public function admin()
    {
        redirect('/admin.html', 'refresh');
    }

    public function teacher()
    {
        redirect('/teacher.html', 'refresh');
    }

    public function student()
    {
        redirect('/dashboard.html', 'refresh');
    }

    public function parent()
    {
        redirect('/parent.html', 'refresh');
    }
}
