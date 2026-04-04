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

$route['admin/dashboard']      = 'portal/admin';
$route['admin/(:any)']         = 'portal/admin';
$route['admin/(:any)/(:any)']  = 'portal/admin';
$route['teacher/dashboard']    = 'portal/teacher';
$route['teacher/(:any)']       = 'portal/teacher';
$route['teacher/(:any)/(:any)'] = 'portal/teacher';
$route['student/dashboard']    = 'portal/student';
$route['student/(:any)']       = 'portal/student';
$route['student/(:any)/(:any)'] = 'portal/student';
$route['parents/dashboard']    = 'portal/parent';
$route['parents/(:any)']       = 'portal/parent';
$route['parents/(:any)/(:any)'] = 'portal/parent';

// ── API Routes ──
$route['api/login']                = 'auth/login';
$route['api/logout']               = 'auth/logout';
$route['login/validate_login']     = 'auth/validate_login';
$route['api/progress']             = 'api/progress';
$route['api/progress/save']        = 'api/progress_save';

$route['api/student/stats']        = 'school/student_stats';
$route['api/student/courses']      = 'school/student_courses';
$route['api/student/learning']     = 'learning/student_learning';
$route['api/student/assignments']  = 'api/student_assignments';
$route['api/student/schedule']     = 'school/student_schedule';

$route['api/parent/child']         = 'school/parent_child';
$route['api/parent/courses']       = 'learning/parent_courses';
$route['api/parent/grades']        = 'school/parent_grades';
$route['api/parent/attendance']    = 'school/parent_attendance';
$route['api/parent/fees']          = 'school/parent_fees';
$route['api/parent/fees/pay']      = 'school/parent_fees_pay';
$route['api/parent/schedule']      = 'school/parent_schedule';
$route['api/parent/teachers']      = 'school/parent_teachers';
$route['api/parent/messages']      = 'api/parent_messages';
$route['api/parent/messages/send'] = 'api/parent_messages_send';

$route['api/teacher/stats']        = 'school/teacher_stats';
$route['api/teacher/students']     = 'school/teacher_students';
$route['api/teacher/classes']      = 'school/teacher_classes';
$route['api/teacher/schedule']     = 'school/teacher_schedule';
$route['api/teacher/course-catalog'] = 'api/teacher_course_catalog';
$route['api/teacher/curriculum-catalog'] = 'learning/teacher_curriculum_catalog';
$route['api/teacher/assignments']  = 'api/teacher_assignments';
$route['api/teacher/assignments/create'] = 'api/teacher_assignments_create';
$route['api/teacher/student-courses/create'] = 'api/teacher_student_courses_create';
$route['api/teacher/student-lessons/create'] = 'api/teacher_student_lessons_create';
$route['api/teacher/attendance']   = 'api/teacher_attendance_save';

$route['api/admin/stats']          = 'api/admin_stats';
$route['api/admin/users']          = 'api/admin_users';
$route['api/admin/users/create']   = 'api/admin_users_create';
$route['api/admin/courses']        = 'api/admin_courses';
$route['api/admin/courses/create'] = 'api/admin_courses_create';
$route['api/admin/lessons']        = 'api/admin_lessons';
$route['api/admin/lessons/create'] = 'api/admin_lessons_create';
$route['api/admin/course-lessons/create'] = 'api/admin_course_lessons_create';
$route['api/admin/teacher-courses/create'] = 'api/admin_teacher_courses_create';
$route['api/admin/student-courses/create'] = 'api/admin_student_courses_create';
$route['api/admin/activity']       = 'api/admin_activity';
