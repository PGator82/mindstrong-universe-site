<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Api Controller — MindStrong Universe
 * All endpoints return JSON. URI pattern: /api/<endpoint>
 *
 * Table map (confirmed from myschool.sql):
 *   admin, teacher, student, parent, class, subject, section
 *   mark        — mark_id, student_id, subject_id, class_id, mark_obtained, mark_total, exam_id, year
 *   invoice     — invoice_id, student_id, title, amount, status, payment_timestamp, due, creation_timestamp
 *   attendance  — attendance_id, student_id, class_id, section_id, class_routine_id, status(int), timestamp, year
 *   noticeboard — noticeboard_id, notice_title, create_timestamp
 *   enroll      — student_id, class_id, section_id
 *   class_routine — class_routine_id, class_id, subject_id, section_id
 *
 * Password hashing: sha1() — matches existing Login.php
 * Session sess_key values: stored as string '1'
 */
class Api extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('Crud_model');
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function json($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    /** Check session flag (stored as '1' string by original Login.php) */
    private function requireSession($role_key) {
        if ($this->session->userdata($role_key) != '1') {
            $this->json(['error' => 'Unauthorized'], 401);
        }
    }

    private function getPosted($key) {
        $val = $this->input->post($key, TRUE);
        return ($val !== FALSE && $val !== null) ? trim($val) : '';
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/login
     * Body: email, password, role (optional hint)
     * Mirrors the logic in application/controllers/Login.php::validate_login()
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $email    = $this->getPosted('email');
        $password = $this->getPosted('password');
        $role     = strtolower($this->getPosted('role'));

        if (!$email || !$password) {
            $this->json(['error' => 'Email and password are required'], 400);
        }

        // sha1 — confirmed from Login.php line 56
        $hashed = sha1($password);

        // Role config: table, id field, session key, redirect — matches Login.php exactly
        $role_map = [
            'admin'   => ['table' => 'admin',   'id_field' => 'admin_id',   'sess_key' => 'admin_login',   'redirect' => 'admin.html'],
            'teacher' => ['table' => 'teacher',  'id_field' => 'teacher_id', 'sess_key' => 'teacher_login', 'redirect' => 'teacher.html'],
            'student' => ['table' => 'student',  'id_field' => 'student_id', 'sess_key' => 'student_login', 'redirect' => 'dashboard.html'],
            'parent'  => ['table' => 'parent',   'id_field' => 'parent_id',  'sess_key' => 'parent_login',  'redirect' => 'parent.html'],
        ];

        // Try hinted role first; fall back to all roles
        $try_roles = ($role && isset($role_map[$role]))
            ? [$role => $role_map[$role]]
            : $role_map;

        foreach ($try_roles as $role_name => $cfg) {
            $user = $this->db->get_where($cfg['table'], [
                'email'    => $email,
                'password' => $hashed,
            ])->row_array();

            if ($user) {
                $uid = $user[$cfg['id_field']];
                // Set session to match original Login.php pattern exactly
                $this->session->set_userdata([
                    $cfg['sess_key']    => '1',
                    $cfg['id_field']    => $uid,   // e.g. admin_id, student_id
                    'login_user_id'     => $uid,
                    'user_id'           => $uid,   // convenience for API methods
                    'name'              => $user['name'],
                    'login_user_name'   => $user['name'],
                    'user_role'         => $role_name,
                    'login_type'        => $role_name,
                ]);
                $this->json([
                    'success'  => true,
                    'role'     => $role_name,
                    'name'     => $user['name'],
                    'redirect' => $cfg['redirect'],
                ]);
            }
        }

        $this->json(['error' => 'Invalid email or password'], 401);
    }

    /**
     * POST /api/logout
     */
    public function logout() {
        $this->session->sess_destroy();
        $this->json(['success' => true, 'redirect' => 'login.html']);
    }

    // ─── Student ──────────────────────────────────────────────────────────────

    /**
     * GET /api/student/stats
     */
    public function student_stats() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $student = $this->db->get_where('student', ['student_id' => $student_id])->row_array();
        if (!$student) { $this->json(['error' => 'Student not found'], 404); }

        // Get class via enroll table
        $enroll = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;

        // Count subjects in class
        $course_count = $class_id
            ? $this->db->get_where('subject', ['class_id' => $class_id])->num_rows()
            : 0;

        // Average marks (mark_obtained / mark_total * 100)
        $marks = $this->db->get_where('mark', ['student_id' => $student_id])->result_array();
        $scores = [];
        foreach ($marks as $m) {
            if ($m['mark_total'] > 0) {
                $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
            }
        }
        $avg_score = count($scores) ? round(array_sum($scores) / count($scores), 1) : 0;

        // Attendance rate (status=1 means present)
        $total_att   = $this->db->get_where('attendance', ['student_id' => $student_id])->num_rows();
        $present_att = $this->db->get_where('attendance', ['student_id' => $student_id, 'status' => 1])->num_rows();
        $att_rate    = $total_att ? round($present_att / $total_att * 100) : 0;

        $this->json([
            'name'         => $student['name'],
            'xp'           => (int)($avg_score * 40),
            'streak'       => 14,
            'course_count' => $course_count,
            'avg_score'    => $avg_score,
            'att_rate'     => $att_rate,
        ]);
    }

    /**
     * GET /api/student/courses
     */
    public function student_courses() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $enroll   = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;
        if (!$class_id) { $this->json(['courses' => []]); }

        $subjects = $this->db->get_where('subject', ['class_id' => $class_id])->result_array();
        $courses  = [];
        foreach ($subjects as $sub) {
            $marks = $this->db->get_where('mark', [
                'student_id' => $student_id,
                'subject_id' => $sub['subject_id'],
            ])->result_array();

            $scores = [];
            foreach ($marks as $m) {
                if ($m['mark_total'] > 0)
                    $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
            }
            $progress = count($scores) ? min(100, round(array_sum($scores) / count($scores))) : 0;

            $teacher = isset($sub['teacher_id']) && $sub['teacher_id']
                ? $this->db->get_where('teacher', ['teacher_id' => $sub['teacher_id']])->row_array()
                : null;

            $courses[] = [
                'subject'  => $sub['name'],
                'teacher'  => $teacher ? $teacher['name'] : 'TBD',
                'progress' => $progress,
            ];
        }
        $this->json(['courses' => $courses]);
    }

    /**
     * GET /api/student/assignments
     */
    public function student_assignments() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $marks = $this->db->get_where('mark', ['student_id' => $student_id])->result_array();
        $assignments = [];
        foreach ($marks as $m) {
            $pct = $m['mark_total'] > 0
                ? round($m['mark_obtained'] / $m['mark_total'] * 100)
                : 0;
            $assignments[] = [
                'title'   => $m['comment'] ?: 'Exam / Assignment',
                'score'   => $pct,
                'status'  => 'submitted',
            ];
        }
        $this->json(['assignments' => $assignments]);
    }

    /**
     * GET /api/student/schedule
     */
    public function student_schedule() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $enroll   = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;

        $routines = $class_id
            ? $this->db->get_where('class_routine', ['class_id' => $class_id])->result_array()
            : [];

        $schedule = [];
        foreach ($routines as $r) {
            $sub = $this->db->get_where('subject', ['subject_id' => $r['subject_id']])->row_array();
            $schedule[] = [
                'time'    => isset($r['start_time']) ? $r['start_time'] : '',
                'subject' => $sub ? $sub['name'] : 'Class',
                'room'    => isset($r['room_number']) ? $r['room_number'] : '',
            ];
        }
        $this->json(['schedule' => $schedule]);
    }

    // ─── Parent ───────────────────────────────────────────────────────────────

    /**
     * GET /api/parent/child
     */
    public function parent_child() {
        $this->requireSession('parent_login');
        $parent_id = $this->session->userdata('parent_id')
                  ?: $this->session->userdata('user_id');

        $parent = $this->db->get_where('parent', ['parent_id' => $parent_id])->row_array();
        if (!$parent) { $this->json(['error' => 'Parent not found'], 404); }

        // Students linked to parent (student table has parent_id column)
        $children_rows = $this->db->get_where('student', ['parent_id' => $parent_id])->result_array();
        $children = [];
        foreach ($children_rows as $c) {
            $enroll = $this->db->get_where('enroll', ['student_id' => $c['student_id']])->row_array();
            $class  = $enroll ? $this->db->get_where('class', ['class_id' => $enroll['class_id']])->row_array() : null;
            $children[] = [
                'id'    => $c['student_id'],
                'name'  => $c['name'],
                'grade' => $class ? 'Grade ' . $class['name'] : 'N/A',
            ];
        }

        $parts = explode(' ', $parent['name']);
        $this->json([
            'parent_name'   => $parent['name'],
            'greeting_name' => $parts[0],
            'children'      => $children,
        ]);
    }

    /**
     * GET /api/parent/grades?student_id=X
     */
    public function parent_grades() {
        $this->requireSession('parent_login');
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = $this->input->get('student_id', TRUE);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? $child['student_id'] : null;
        }
        if (!$student_id) { $this->json(['grades' => []]); }

        $enroll   = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;
        $subjects = $class_id
            ? $this->db->get_where('subject', ['class_id' => $class_id])->result_array()
            : [];

        $grades = [];
        foreach ($subjects as $sub) {
            $marks = $this->db->get_where('mark', [
                'student_id' => $student_id,
                'subject_id' => $sub['subject_id'],
            ])->result_array();

            $scores = [];
            $last_score = 0;
            foreach ($marks as $m) {
                if ($m['mark_total'] > 0) {
                    $pct = round($m['mark_obtained'] / $m['mark_total'] * 100);
                    $scores[] = $pct;
                    $last_score = $pct;
                }
            }
            $average = count($scores) ? round(array_sum($scores) / count($scores)) : 0;

            $letter = 'F';
            if ($average >= 90)     $letter = 'A';
            elseif ($average >= 80) $letter = 'B';
            elseif ($average >= 70) $letter = 'C';
            elseif ($average >= 60) $letter = 'D';

            $teacher = isset($sub['teacher_id']) && $sub['teacher_id']
                ? $this->db->get_where('teacher', ['teacher_id' => $sub['teacher_id']])->row_array()
                : null;

            $grades[] = [
                'subject'    => $sub['name'],
                'teacher'    => $teacher ? $teacher['name'] : 'TBD',
                'last_score' => $last_score,
                'average'    => $average,
                'grade'      => $letter,
            ];
        }
        $this->json(['grades' => $grades]);
    }

    /**
     * GET /api/parent/attendance?student_id=X
     * attendance.status: 1=present, 0=absent, 2=late
     * attendance.timestamp: unix timestamp
     */
    public function parent_attendance() {
        $this->requireSession('parent_login');
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = $this->input->get('student_id', TRUE);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? $child['student_id'] : null;
        }
        if (!$student_id) { $this->json(['records' => [], 'rate' => 0]); }

        // Last 30 days using timestamp
        $since = strtotime('-30 days');
        $this->db->where('student_id', $student_id);
        $this->db->where('timestamp >=', $since);
        $this->db->order_by('timestamp', 'ASC');
        $rows = $this->db->get('attendance')->result_array();

        $records = [];
        foreach ($rows as $r) {
            if ($r['status'] == 1)     $records[] = 'present';
            elseif ($r['status'] == 0) $records[] = 'absent';
            elseif ($r['status'] == 2) $records[] = 'late';
            else                       $records[] = 'holiday';
        }

        $present = count(array_filter($records, fn($x) => $x === 'present'));
        $total   = count(array_filter($records, fn($x) => $x !== 'holiday'));
        $rate    = $total ? round($present / $total * 100) : 0;

        $this->json(['records' => $records, 'rate' => $rate]);
    }

    /**
     * GET /api/parent/fees?student_id=X
     * invoice.status: 'paid', 'unpaid', or check payment_timestamp
     */
    public function parent_fees() {
        $this->requireSession('parent_login');
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = $this->input->get('student_id', TRUE);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? $child['student_id'] : null;
        }
        if (!$student_id) { $this->json(['fees' => []]); }

        $invoices = $this->db->get_where('invoice', ['student_id' => $student_id])->result_array();
        $fees = [];
        foreach ($invoices as $inv) {
            // Determine status
            $status = 'pending';
            if (!empty($inv['status']) && strtolower($inv['status']) === 'paid') {
                $status = 'paid';
            } elseif (!empty($inv['payment_timestamp'])) {
                $status = 'paid';
            } elseif (!empty($inv['due']) && (int)$inv['due'] < time()) {
                $status = 'overdue';
            }

            $fees[] = [
                'name'     => $inv['title'],
                'amount'   => $inv['amount'],
                'due_date' => !empty($inv['due'])
                    ? date('M j, Y', (int)$inv['due'])
                    : '',
                'status'   => $status,
            ];
        }
        $this->json(['fees' => $fees]);
    }

    // ─── Teacher ──────────────────────────────────────────────────────────────

    /**
     * GET /api/teacher/stats
     */
    public function teacher_stats() {
        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');

        $subjects  = $this->db->get_where('subject', ['teacher_id' => $teacher_id])->result_array();
        $class_ids = array_unique(array_column($subjects, 'class_id'));

        // Count students across all classes
        $student_count = 0;
        $all_scores    = [];
        foreach ($class_ids as $cid) {
            $enrolls = $this->db->get_where('enroll', ['class_id' => $cid])->result_array();
            $student_count += count($enrolls);
            foreach ($enrolls as $e) {
                $marks = $this->db->get_where('mark', ['student_id' => $e['student_id']])->result_array();
                foreach ($marks as $m) {
                    if ($m['mark_total'] > 0)
                        $all_scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
                }
            }
        }
        $avg_score = count($all_scores) ? round(array_sum($all_scores) / count($all_scores), 1) : 0;

        $teacher = $this->db->get_where('teacher', ['teacher_id' => $teacher_id])->row_array();
        $this->json([
            'name'          => $teacher ? $teacher['name'] : '',
            'student_count' => $student_count,
            'class_count'   => count($class_ids),
            'avg_score'     => $avg_score,
        ]);
    }

    /**
     * GET /api/teacher/students?class_id=X
     */
    public function teacher_students() {
        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $class_id   = $this->input->get('class_id', TRUE);

        if (!$class_id) {
            $sub      = $this->db->get_where('subject', ['teacher_id' => $teacher_id])->row_array();
            $class_id = $sub ? $sub['class_id'] : null;
        }
        if (!$class_id) { $this->json(['students' => []]); }

        $enrolls  = $this->db->get_where('enroll', ['class_id' => $class_id])->result_array();
        $students = [];
        foreach ($enrolls as $e) {
            $s = $this->db->get_where('student', ['student_id' => $e['student_id']])->row_array();
            if (!$s) continue;
            $marks  = $this->db->get_where('mark', ['student_id' => $e['student_id']])->result_array();
            $scores = [];
            foreach ($marks as $m) {
                if ($m['mark_total'] > 0)
                    $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
            }
            $avg = count($scores) ? round(array_sum($scores) / count($scores)) : 0;
            $students[] = [
                'id'      => $s['student_id'],
                'name'    => $s['name'],
                'email'   => $s['email'],
                'average' => $avg,
            ];
        }
        $this->json(['students' => $students, 'class_id' => $class_id]);
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/stats
     */
    public function admin_stats() {
        $this->requireSession('admin_login');

        $total_students = $this->db->count_all('student');
        $total_teachers = $this->db->count_all('teacher');
        $total_parents  = $this->db->count_all('parent');
        $total_admin    = $this->db->count_all('admin');

        // Attendance today — use today's unix date range
        $today_start = mktime(0, 0, 0);
        $today_end   = mktime(23, 59, 59);
        $this->db->where('timestamp >=', $today_start);
        $this->db->where('timestamp <=', $today_end);
        $today_total = $this->db->count_all_results('attendance');

        $this->db->where('timestamp >=', $today_start);
        $this->db->where('timestamp <=', $today_end);
        $this->db->where('status', 1);
        $today_present = $this->db->count_all_results('attendance');

        $att_rate = $today_total ? round($today_present / $today_total * 100) : 0;

        // Revenue from paid invoices
        $rev = $this->db->select('SUM(amount) as total')
                        ->where('status', 'paid')
                        ->get('invoice')
                        ->row_array();
        $revenue = $rev ? (float)($rev['total'] ?? 0) : 0;

        $this->json([
            'total_students' => $total_students,
            'total_teachers' => $total_teachers,
            'total_parents'  => $total_parents,
            'total_admin'    => $total_admin,
            'total_users'    => $total_students + $total_teachers + $total_parents + $total_admin,
            'att_rate'       => $att_rate,
            'revenue'        => $revenue,
        ]);
    }

    /**
     * GET /api/admin/users?role=student&page=1&limit=20
     */
    public function admin_users() {
        $this->requireSession('admin_login');

        $role   = $this->input->get('role', TRUE) ?: 'student';
        $page   = max(1, (int)($this->input->get('page', TRUE) ?: 1));
        $limit  = min(100, max(1, (int)($this->input->get('limit', TRUE) ?: 20)));
        $offset = ($page - 1) * $limit;

        $table_map = [
            'student'  => 'student',
            'teacher'  => 'teacher',
            'parent'   => 'parent',
            'admin'    => 'admin',
        ];
        $table = $table_map[$role] ?? 'student';

        $total = $this->db->count_all($table);
        $rows  = $this->db->limit($limit, $offset)->get($table)->result_array();

        foreach ($rows as &$row) {
            unset($row['password'], $row['authentication_key']);
        }
        unset($row);

        $this->json([
            'users'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    /**
     * GET /api/admin/activity
     */
    public function admin_activity() {
        $this->requireSession('admin_login');
        $activity = [];

        // Recent notices
        $notices = $this->db->order_by('noticeboard_id', 'DESC')->limit(5)->get('noticeboard')->result_array();
        foreach ($notices as $n) {
            $activity[] = [
                'type'    => 'notice',
                'icon'    => '📢',
                'message' => 'New notice: ' . ($n['notice_title'] ?? 'Untitled'),
                'time'    => isset($n['create_timestamp'])
                    ? date('M j, g:i A', (int)$n['create_timestamp'])
                    : '',
            ];
        }

        // Recent marks
        $recent_marks = $this->db->order_by('mark_id', 'DESC')->limit(5)->get('mark')->result_array();
        foreach ($recent_marks as $m) {
            $student = $this->db->get_where('student', ['student_id' => $m['student_id']])->row_array();
            $pct = $m['mark_total'] > 0 ? round($m['mark_obtained'] / $m['mark_total'] * 100) : 0;
            $activity[] = [
                'type'    => 'grade',
                'icon'    => '📊',
                'message' => ($student ? $student['name'] : 'Student') . ' scored ' . $pct . '%',
                'time'    => '',
            ];
        }

        $this->json(['activity' => array_slice($activity, 0, 10)]);
    }

}
