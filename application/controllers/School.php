<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class School extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
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

    private function json($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    private function requireSession($role_key) {
        if ($this->session->userdata($role_key) != '1') {
            $this->json(['error' => 'Unauthorized'], 401);
        }
    }

    private function getPosted($key) {
        $val = $this->input->post($key, TRUE);
        return ($val !== FALSE && $val !== null) ? trim($val) : '';
    }

    private function getJsonInput() {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function tableColumns($table) {
        static $cache = [];
        if (!isset($cache[$table])) {
            $cache[$table] = array_map(function ($field) {
                return $field->name;
            }, $this->db->field_data($table));
        }
        return $cache[$table];
    }

    private function filterPayloadForTable($table, $payload) {
        $columns = $this->tableColumns($table);
        return array_intersect_key($payload, array_flip($columns));
    }

    private function firstRow($table, $where) {
        return $this->db->get_where($table, $where)->row_array();
    }

    public function student_stats() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $student = $this->db->get_where('student', ['student_id' => $student_id])->row_array();
        if (!$student) { $this->json(['error' => 'Student not found'], 404); }

        $enroll = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;

        $course_count = $class_id
            ? $this->db->get_where('subject', ['class_id' => $class_id])->num_rows()
            : 0;

        $marks = $this->db->get_where('mark', ['student_id' => $student_id])->result_array();
        $scores = [];
        foreach ($marks as $m) {
            if ($m['mark_total'] > 0) {
                $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
            }
        }
        $avg_score = count($scores) ? round(array_sum($scores) / count($scores), 1) : 0;

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
                if ($m['mark_total'] > 0) {
                    $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
                }
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

    public function student_schedule() {
        $this->requireSession('student_login');
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $enroll   = $this->db->get_where('enroll', ['student_id' => $student_id])->row_array();
        $class_id = $enroll ? $enroll['class_id'] : null;
        $section_id = $enroll ? ($enroll['section_id'] ?? null) : null;

        if ($class_id) {
            $this->db->where('class_id', $class_id);
            if ($section_id) {
                $this->db->where('section_id', $section_id);
            }
        }
        $this->db->order_by('day', 'ASC');
        $this->db->order_by('start_time', 'ASC');
        $routines = $class_id ? $this->db->get('class_routine')->result_array() : [];

        $schedule = [];
        foreach ($routines as $r) {
            $sub = $this->db->get_where('subject', ['subject_id' => $r['subject_id']])->row_array();
            $teacher = (!empty($sub['teacher_id']))
                ? $this->db->get_where('teacher', ['teacher_id' => $sub['teacher_id']])->row_array()
                : null;
            $schedule[] = [
                'time'    => isset($r['start_time']) ? $r['start_time'] : '',
                'subject' => $sub ? $sub['name'] : 'Class',
                'room'    => isset($r['room_number']) ? $r['room_number'] : '',
                'day'     => $r['day'] ?? '',
                'teacher' => $teacher['name'] ?? '',
            ];
        }
        $this->json(['schedule' => $schedule]);
    }

    public function parent_child() {
        $this->requireSession('parent_login');
        $parent_id = $this->session->userdata('parent_id')
                  ?: $this->session->userdata('user_id');

        $parent = $this->db->get_where('parent', ['parent_id' => $parent_id])->row_array();
        if (!$parent) { $this->json(['error' => 'Parent not found'], 404); }

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
            $status = 'pending';
            if (!empty($inv['status']) && strtolower($inv['status']) === 'paid') {
                $status = 'paid';
            } elseif (!empty($inv['payment_timestamp'])) {
                $status = 'paid';
            } elseif (!empty($inv['due']) && (int)$inv['due'] < time()) {
                $status = 'overdue';
            }

            $fees[] = [
                'invoice_id' => (int)$inv['invoice_id'],
                'name'     => $inv['title'],
                'amount'   => $inv['amount'],
                'due_date' => !empty($inv['due']) ? date('M j, Y', (int)$inv['due']) : '',
                'status'   => $status,
            ];
        }
        $this->json(['fees' => $fees]);
    }

    public function parent_schedule() {
        $this->requireSession('parent_login');
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = (int)($this->input->get('student_id', TRUE) ?: 0);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? (int)$child['student_id'] : 0;
        }
        if (!$student_id) {
            $this->json(['schedule' => []]);
        }

        $enroll = $this->firstRow('enroll', ['student_id' => $student_id]);
        if (!$enroll) {
            $this->json(['schedule' => []]);
        }

        $this->db->where('class_id', $enroll['class_id']);
        if (!empty($enroll['section_id'])) {
            $this->db->where('section_id', $enroll['section_id']);
        }
        $this->db->order_by('day', 'ASC');
        $this->db->order_by('start_time', 'ASC');
        $routines = $this->db->get('class_routine')->result_array();

        $schedule = [];
        foreach ($routines as $routine) {
            $subject = $this->firstRow('subject', ['subject_id' => $routine['subject_id']]);
            $teacher = (!empty($subject['teacher_id']))
                ? $this->firstRow('teacher', ['teacher_id' => $subject['teacher_id']])
                : null;
            $schedule[] = [
                'day' => $routine['day'] ?? '',
                'time' => $routine['start_time'] ?? '',
                'subject' => $subject['name'] ?? 'Class',
                'teacher' => $teacher['name'] ?? 'Teacher',
                'room' => $routine['room_number'] ?? '',
            ];
        }

        $this->json(['schedule' => $schedule]);
    }

    public function parent_fees_pay() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('parent_login');
        $parent_id = $this->session->userdata('parent_id')
                  ?: $this->session->userdata('user_id');
        $body = $this->getJsonInput();
        $invoice_id = (int)($body['invoice_id'] ?? $this->getPosted('invoice_id'));

        if (!$invoice_id) {
            $this->json(['error' => 'Invoice is required'], 400);
        }

        $invoice = $this->firstRow('invoice', ['invoice_id' => $invoice_id]);
        if (!$invoice) {
            $this->json(['error' => 'Invoice not found'], 404);
        }

        $student = $this->firstRow('student', ['student_id' => $invoice['student_id']]);
        if (!$student || (int)($student['parent_id'] ?? 0) !== (int)$parent_id) {
            $this->json(['error' => 'Unauthorized'], 403);
        }

        $payload = [
            'status' => 'paid',
            'payment_timestamp' => time(),
        ];
        $payload = $this->filterPayloadForTable('invoice', $payload);
        $this->db->where('invoice_id', $invoice_id)->update('invoice', $payload);

        $this->json([
            'success' => true,
            'invoice_id' => $invoice_id,
            'status' => 'paid',
        ]);
    }

    public function parent_teachers() {
        $this->requireSession('parent_login');
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = (int)($this->input->get('student_id', TRUE) ?: 0);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? (int)$child['student_id'] : 0;
        }
        if (!$student_id) {
            $this->json(['teachers' => []]);
        }

        $enroll = $this->firstRow('enroll', ['student_id' => $student_id]);
        if (!$enroll) {
            $this->json(['teachers' => []]);
        }

        $subjects = $this->db->get_where('subject', ['class_id' => $enroll['class_id']])->result_array();
        $teachers = [];
        foreach ($subjects as $subject) {
            if (empty($subject['teacher_id'])) {
                continue;
            }
            $teacher = $this->firstRow('teacher', ['teacher_id' => $subject['teacher_id']]);
            if (!$teacher) {
                continue;
            }
            $teachers[$teacher['teacher_id']] = [
                'teacher_id' => (int)$teacher['teacher_id'],
                'name' => $teacher['name'],
                'subject' => $subject['name'],
            ];
        }

        $this->json(['teachers' => array_values($teachers)]);
    }

    public function teacher_stats() {
        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');

        $subjects  = $this->db->get_where('subject', ['teacher_id' => $teacher_id])->result_array();
        $class_ids = array_unique(array_column($subjects, 'class_id'));

        $student_count = 0;
        $all_scores    = [];
        foreach ($class_ids as $cid) {
            $enrolls = $this->db->get_where('enroll', ['class_id' => $cid])->result_array();
            $student_count += count($enrolls);
            foreach ($enrolls as $e) {
                $marks = $this->db->get_where('mark', ['student_id' => $e['student_id']])->result_array();
                foreach ($marks as $m) {
                    if ($m['mark_total'] > 0) {
                        $all_scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
                    }
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
                if ($m['mark_total'] > 0) {
                    $scores[] = round($m['mark_obtained'] / $m['mark_total'] * 100);
                }
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

    public function teacher_classes() {
        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');

        $subjects = $this->db->get_where('subject', ['teacher_id' => $teacher_id])->result_array();
        $classes = [];
        foreach ($subjects as $subject) {
            $class_id = (int)$subject['class_id'];
            if (isset($classes[$class_id])) {
                continue;
            }
            $class = $this->firstRow('class', ['class_id' => $class_id]);
            $enrolls = $this->db->get_where('enroll', ['class_id' => $class_id])->result_array();
            $classes[$class_id] = [
                'class_id' => $class_id,
                'subject' => $subject['name'],
                'class_name' => $class['name'] ?? ('Class ' . $class_id),
                'student_count' => count($enrolls),
                'section_id' => (int)($subject['section_id'] ?? 0),
            ];
        }

        $this->json(['classes' => array_values($classes)]);
    }

    public function teacher_schedule() {
        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');

        $subjects = $this->db->select('subject_id, name')
            ->where('teacher_id', $teacher_id)
            ->get('subject')
            ->result_array();
        $subject_ids = array_map('intval', array_column($subjects, 'subject_id'));

        if (!$subject_ids) {
            $this->json(['schedule' => []]);
        }

        $this->db->where_in('subject_id', $subject_ids);
        $this->db->order_by('day', 'ASC');
        $this->db->order_by('start_time', 'ASC');
        $rows = $this->db->get('class_routine')->result_array();

        $schedule = [];
        foreach ($rows as $row) {
            $subject = $this->firstRow('subject', ['subject_id' => $row['subject_id']]);
            $class = $this->firstRow('class', ['class_id' => $row['class_id']]);
            $schedule[] = [
                'class_id' => (int)$row['class_id'],
                'subject' => $subject['name'] ?? 'Class',
                'class_name' => $class['name'] ?? '',
                'day' => $row['day'] ?? '',
                'time' => $row['start_time'] ?? '',
                'room' => $row['room_number'] ?? '',
            ];
        }

        $this->json(['schedule' => $schedule]);
    }
}
