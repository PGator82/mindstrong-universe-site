<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'login';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// ── API Routes ──
$route['api/login']                = 'api/login';
$route['api/logout']               = 'api/logout';

$route['api/student/stats']        = 'api/student_stats';
$route['api/student/courses']      = 'api/student_courses';
$route['api/student/assignments']  = 'api/student_assignments';
$route['api/student/schedule']     = 'api/student_schedule';

$route['api/parent/child']         = 'api/parent_child';
$route['api/parent/grades']        = 'api/parent_grades';
$route['api/parent/attendance']    = 'api/parent_attendance';
$route['api/parent/fees']          = 'api/parent_fees';

$route['api/teacher/stats']        = 'api/teacher_stats';
$route['api/teacher/students']     = 'api/teacher_students';

$route['api/admin/stats']          = 'api/admin_stats';
$route['api/admin/users']          = 'api/admin_users';
$route['api/admin/activity']       = 'api/admin_activity';
