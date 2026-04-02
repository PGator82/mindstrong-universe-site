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

        // Explicit CORS allowlist — no wildcard in production
        $allowed = array_filter([
            getenv('CORS_ORIGIN') ?: null,
            'https://mindstrong-universe-school-production.up.railway.app',
            'https://pgator82.github.io',
        ]);
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif ($origin === '') {
            // Same-origin or server-side request — no CORS header needed
        } else {
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

    private function getJsonInput() {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function currentProgressOwner($token = '') {
        $roles = [
            'student' => 'student_id',
            'teacher' => 'teacher_id',
            'parent'  => 'parent_id',
            'admin'   => 'admin_id',
        ];

        foreach ($roles as $role => $field) {
            if ($this->session->userdata($role . '_login') == '1') {
                $user_id = (int)($this->session->userdata($field) ?: $this->session->userdata('user_id'));
                return [
                    'owner_key' => $role . ':' . $user_id,
                    'role' => $role,
                    'user_id' => $user_id,
                    'token' => null,
                ];
            }
        }

        $token = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$token);
        if ($token === '') {
            $token = substr(bin2hex(random_bytes(18)), 0, 36);
        }

        return [
            'owner_key' => 'token:' . $token,
            'role' => 'guest',
            'user_id' => null,
            'token' => $token,
        ];
    }

    private function ensureProgressTable() {
        static $ensured = false;
        if ($ensured) return;

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_progress_state (
            progress_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_key VARCHAR(80) NOT NULL,
            user_role VARCHAR(20) NOT NULL DEFAULT 'guest',
            user_id INT NULL,
            progress_token VARCHAR(64) NULL,
            progress_json LONGTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (progress_id),
            UNIQUE KEY uniq_owner_key (owner_key),
            KEY idx_user_id (user_id),
            KEY idx_progress_token (progress_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ensured = true;
    }

    private function ensureWorkflowTables() {
        static $ensured = false;
        if ($ensured) return;

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_assignments (
            assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            section_id INT NULL,
            title VARCHAR(191) NOT NULL,
            description TEXT NULL,
            type VARCHAR(80) NOT NULL DEFAULT 'Homework',
            due_date DATE NULL,
            max_score DECIMAL(10,2) NOT NULL DEFAULT 100,
            status VARCHAR(40) NOT NULL DEFAULT 'published',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (assignment_id),
            KEY idx_teacher_class (teacher_id, class_id),
            KEY idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_parent_messages (
            message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            sender_role VARCHAR(20) NOT NULL,
            sender_name VARCHAR(191) NOT NULL,
            message_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id),
            KEY idx_thread (parent_id, student_id, teacher_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_courses (
            course_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            description TEXT NULL,
            subject_area VARCHAR(80) NOT NULL DEFAULT 'General',
            class_id INT NULL,
            section_id INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_by_admin_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (course_id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_class_section (class_id, section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_lessons (
            lesson_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            lesson_type VARCHAR(30) NOT NULL DEFAULT 'lesson',
            subject_area VARCHAR(80) NOT NULL DEFAULT 'General',
            description TEXT NULL,
            lesson_url VARCHAR(255) NOT NULL,
            game_url VARCHAR(255) NULL,
            estimated_minutes INT NOT NULL DEFAULT 20,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (lesson_id),
            UNIQUE KEY uniq_lesson_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_course_lessons (
            course_lesson_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT NOT NULL,
            lesson_id INT NOT NULL,
            module_title VARCHAR(191) NULL,
            position INT NOT NULL DEFAULT 1,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (course_lesson_id),
            UNIQUE KEY uniq_course_lesson (course_id, lesson_id),
            KEY idx_course_position (course_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_teacher_courses (
            teacher_course_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            course_id INT NOT NULL,
            class_id INT NULL,
            section_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (teacher_course_id),
            UNIQUE KEY uniq_teacher_course (teacher_id, course_id, class_id, section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_student_courses (
            student_course_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            assigned_by_role VARCHAR(20) NOT NULL DEFAULT 'admin',
            assigned_by_id INT NULL,
            teacher_id INT NULL,
            class_id INT NULL,
            section_id INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_course_id),
            UNIQUE KEY uniq_student_course (student_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_student_lesson_assignments (
            student_lesson_assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            lesson_id INT NOT NULL,
            teacher_id INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'assigned',
            due_date DATE NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_lesson_assignment_id),
            UNIQUE KEY uniq_student_lesson (student_id, course_id, lesson_id),
            KEY idx_student_status (student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ms_lesson_progress (
            lesson_progress_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            lesson_id INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'not_started',
            completion_percent INT NOT NULL DEFAULT 0,
            score DECIMAL(5,2) NULL,
            xp_earned INT NOT NULL DEFAULT 0,
            last_activity_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (lesson_progress_id),
            UNIQUE KEY uniq_progress (student_id, course_id, lesson_id),
            KEY idx_student_course (student_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ensured = true;
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

    private function teacherClassIds($teacher_id) {
        $subjects = $this->db->select('class_id')
            ->where('teacher_id', $teacher_id)
            ->get('subject')
            ->result_array();

        $class_ids = array_values(array_unique(array_map('intval', array_column($subjects, 'class_id'))));
        return array_values(array_filter($class_ids));
    }

    private function teacherOwnsClass($teacher_id, $class_id) {
        return in_array((int)$class_id, $this->teacherClassIds($teacher_id), true);
    }

    private function firstRow($table, $where) {
        return $this->db->get_where($table, $where)->row_array();
    }

    private function currentDayBounds($date = null) {
        $ts = $date ? strtotime($date . ' 00:00:00') : mktime(0, 0, 0);
        return [
            'start' => $ts,
            'end' => $ts + 86399,
        ];
    }

    private function gradeLetter($average) {
        if ($average >= 90) return 'A';
        if ($average >= 80) return 'B';
        if ($average >= 70) return 'C';
        if ($average >= 60) return 'D';
        return 'F';
    }

    private function avatarForRole($role) {
        $map = [
            'admin' => '🛡️',
            'teacher' => '👩‍🏫',
            'student' => '🎓',
            'parent' => '👪',
        ];
        return $map[$role] ?? '👤';
    }

    private function slugify($text) {
        $text = strtolower(trim((string)$text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string)$text, '-');
        return $text ?: 'item-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function ensureUniqueSlug($table, $slug_field, $base_slug) {
        $slug = $base_slug;
        $suffix = 2;
        while ($this->db->get_where($table, [$slug_field => $slug])->row_array()) {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    private function coursePayload($course) {
        if (!$course) return null;
        return [
            'course_id' => (int)$course['course_id'],
            'title' => $course['title'],
            'slug' => $course['slug'],
            'description' => $course['description'] ?? '',
            'subject_area' => $course['subject_area'] ?? 'General',
            'class_id' => isset($course['class_id']) ? (int)$course['class_id'] : null,
            'section_id' => isset($course['section_id']) ? (int)$course['section_id'] : null,
            'status' => $course['status'] ?? 'active',
        ];
    }

    private function lessonPayload($lesson) {
        if (!$lesson) return null;
        return [
            'lesson_id' => (int)$lesson['lesson_id'],
            'title' => $lesson['title'],
            'slug' => $lesson['slug'],
            'lesson_type' => $lesson['lesson_type'],
            'subject_area' => $lesson['subject_area'] ?? 'General',
            'description' => $lesson['description'] ?? '',
            'lesson_url' => $lesson['lesson_url'],
            'game_url' => $lesson['game_url'] ?? '',
            'estimated_minutes' => (int)($lesson['estimated_minutes'] ?? 20),
            'status' => $lesson['status'] ?? 'active',
        ];
    }

    private function courseLessons($course_id) {
        $rows = $this->db->order_by('position', 'ASC')
            ->get_where('ms_course_lessons', ['course_id' => $course_id])
            ->result_array();
        $items = [];
        foreach ($rows as $row) {
            $lesson = $this->firstRow('ms_lessons', ['lesson_id' => $row['lesson_id']]);
            if (!$lesson) continue;
            $item = $this->lessonPayload($lesson);
            $item['module_title'] = $row['module_title'] ?? '';
            $item['position'] = (int)$row['position'];
            $item['is_required'] = !empty($row['is_required']);
            $items[] = $item;
        }
        return $items;
    }

    private function curriculumCourseLabel($key) {
        $labels = [
            'foundations_math' => ['title' => 'Foundations Math', 'subject' => 'Mathematics', 'description' => 'Built lessons from the live Foundations math sequence.'],
            'foundations_science' => ['title' => 'Foundations Science', 'subject' => 'Science', 'description' => 'Built lessons from the live Foundations science sequence.'],
            'foundations_english' => ['title' => 'Foundations English', 'subject' => 'English', 'description' => 'Built lessons from the live Foundations English sequence.'],
            'pre_algebra' => ['title' => 'Pre-Algebra', 'subject' => 'Mathematics', 'description' => 'Built lessons from the pre-algebra path.'],
        ];
        return $labels[$key] ?? ['title' => 'MindStrong Course', 'subject' => 'General', 'description' => 'Built curriculum lessons.'];
    }

    private function inferCurriculumCourseKey($relative_path) {
        if (strpos($relative_path, '/foundations-science/') === 0) return 'foundations_science';
        if (strpos($relative_path, '/foundations-english/') === 0) return 'foundations_english';
        if (strpos($relative_path, '/pre-algebra/') === 0) return 'pre_algebra';
        return 'foundations_math';
    }

    private function titleFromCurriculumPath($relative_path) {
        $relative_path = trim(str_replace('\\', '/', $relative_path), '/');
        $relative_path = preg_replace('#^school/#', '', $relative_path);
        $parts = explode('/', $relative_path);
        if (count($parts) >= 2 && preg_match('/^lesson-(\d+)$/', basename($parts[count($parts) - 1], '.html'), $match)) {
            $group = $parts[count($parts) - 2];
            $group = str_replace(['foundations-science', 'foundations-english'], ['science', 'english'], $group);
            $group_title = ucwords(str_replace(['-', '_'], ' ', $group));
            return trim($group_title . ' Lesson ' . $match[1]);
        }

        $base = basename($relative_path, '.html');
        if ($base === 'index' && count($parts) > 1) {
            $base = $parts[count($parts) - 2];
        }

        $map = [
            'module-1' => 'Number Sense',
            'pre-algebra' => 'Pre-Algebra',
            'geometry' => 'Geometry Foundations',
            'fractions' => 'Fractions and Operations',
            'integers' => 'Integers and Negatives',
            'data' => 'Data and Statistics',
            'algebra' => 'Intro to Algebra',
            'linear' => 'Linear Equations and Slope',
            'functions' => 'Functions and Graphs',
            'foundations-science' => 'Foundations Science',
            'foundations-english' => 'Foundations English',
            'ratio' => 'Ratios',
            'proportions' => 'Proportions',
            'percents' => 'Percents',
            'expressions' => 'Expressions',
            'equations' => 'Equations',
            'angles' => 'Angles',
            'area' => 'Area',
            'circles' => 'Circles',
            'coordinate' => 'Coordinate Geometry',
            'polygons' => 'Polygons',
            'triangles' => 'Triangles',
            'central' => 'Central Tendency',
            'spread' => 'Spread and Variability',
            'meaning' => 'Meaning of Fractions',
            'equivalent' => 'Equivalent Fractions',
            'convert' => 'Converting Fractions',
            'add-sub' => 'Add and Subtract Fractions',
            'multiply' => 'Multiply Fractions',
            'divide' => 'Divide Fractions',
        ];
        return $map[$base] ?? ucwords(str_replace(['-', '_'], ' ', $base));
    }

    private function builtCurriculumDescriptors() {
        $school_path = rtrim(str_replace('\\', '/', FCPATH), '/') . '/school';
        if (!is_dir($school_path)) return [];

        $descriptors = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($school_path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') continue;
            $full_path = str_replace('\\', '/', $file->getPathname());
            $relative_path = str_replace(str_replace('\\', '/', rtrim(FCPATH, '\\/')), '', $full_path);
            $relative_path = str_replace('\\', '/', $relative_path);
            if (preg_match('#^/school/(index\.html|games\.html|math-league\.html)$#', $relative_path)) continue;
            if (preg_match('#/progress-dashboard\.html$#', $relative_path)) continue;
            if (preg_match('#^/school/(foundations|foundations-science|foundations-english)/index\.html$#', $relative_path)) continue;

            $course_key = $this->inferCurriculumCourseKey($relative_path);
            $title = $this->titleFromCurriculumPath($relative_path);
            $module_title = '';
            if (preg_match('#/school/foundations/([^/]+)/#', $relative_path, $match)) {
                $module_title = $this->titleFromCurriculumPath('/school/foundations/' . $match[1] . '/index.html');
            } elseif (preg_match('#^/school/(foundations-science|foundations-english)/#', $relative_path, $match)) {
                $module_title = $this->curriculumCourseLabel($course_key)['title'];
            } elseif (preg_match('#^/school/pre-algebra/([^/]+)/#', $relative_path, $match)) {
                $module_title = $this->titleFromCurriculumPath('/school/pre-algebra/' . $match[1] . '/index.html');
            }

            $descriptors[] = [
                'course_key' => $course_key,
                'title' => $title,
                'module_title' => $module_title,
                'lesson_url' => $relative_path,
                'subject_area' => $this->curriculumCourseLabel($course_key)['subject'],
                'lesson_type' => 'lesson',
                'estimated_minutes' => 20,
            ];
        }

        usort($descriptors, function ($a, $b) {
            return strcmp($a['lesson_url'], $b['lesson_url']);
        });
        return $descriptors;
    }

    private function ensureBuiltCurriculumCatalog() {
        static $seeded = false;
        if ($seeded) return;
        $this->ensureWorkflowTables();

        $descriptors = $this->builtCurriculumDescriptors();
        $course_ids = [];
        foreach ($descriptors as $descriptor) {
            $course_meta = $this->curriculumCourseLabel($descriptor['course_key']);
            if (!isset($course_ids[$descriptor['course_key']])) {
                $course = $this->db->get_where('ms_courses', ['slug' => $descriptor['course_key']])->row_array();
                if (!$course) {
                    $this->db->insert('ms_courses', [
                        'title' => $course_meta['title'],
                        'slug' => $descriptor['course_key'],
                        'description' => $course_meta['description'],
                        'subject_area' => $course_meta['subject'],
                        'status' => 'active',
                    ]);
                    $course = $this->db->get_where('ms_courses', ['course_id' => $this->db->insert_id()])->row_array();
                }
                $course_ids[$descriptor['course_key']] = (int)$course['course_id'];
            }

            $lesson_slug = trim($descriptor['course_key'] . '-' . $this->slugify($descriptor['lesson_url']), '-');
            $lesson = $this->db->get_where('ms_lessons', ['slug' => $lesson_slug])->row_array();
            if (!$lesson) {
                $this->db->insert('ms_lessons', [
                    'title' => $descriptor['title'],
                    'slug' => $lesson_slug,
                    'lesson_type' => $descriptor['lesson_type'],
                    'subject_area' => $descriptor['subject_area'],
                    'description' => $descriptor['module_title'] ?: $course_meta['description'],
                    'lesson_url' => $descriptor['lesson_url'],
                    'estimated_minutes' => $descriptor['estimated_minutes'],
                    'status' => 'active',
                ]);
                $lesson = $this->db->get_where('ms_lessons', ['lesson_id' => $this->db->insert_id()])->row_array();
            }

            $existing_link = $this->db->get_where('ms_course_lessons', [
                'course_id' => $course_ids[$descriptor['course_key']],
                'lesson_id' => $lesson['lesson_id'],
            ])->row_array();

            if (!$existing_link) {
                $position = (int)$this->db->where('course_id', $course_ids[$descriptor['course_key']])->count_all_results('ms_course_lessons') + 1;
                $this->db->insert('ms_course_lessons', [
                    'course_id' => $course_ids[$descriptor['course_key']],
                    'lesson_id' => (int)$lesson['lesson_id'],
                    'module_title' => $descriptor['module_title'] ?: null,
                    'position' => $position,
                    'is_required' => 1,
                ]);
            }
        }

        $seeded = true;
    }

    private function studentCourseBundle($student_id) {
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();
        $rows = $this->db->order_by('assigned_at', 'DESC')
            ->get_where('ms_student_courses', ['student_id' => $student_id, 'status' => 'active'])
            ->result_array();
        $courses = [];
        foreach ($rows as $row) {
            $course = $this->firstRow('ms_courses', ['course_id' => $row['course_id']]);
            if (!$course) continue;
            $lessons = $this->courseLessons($row['course_id']);
            $progress_rows = $this->db->get_where('ms_lesson_progress', [
                'student_id' => $student_id,
                'course_id' => $row['course_id'],
            ])->result_array();
            $progress_map = [];
            foreach ($progress_rows as $progress_row) {
                $progress_map[(int)$progress_row['lesson_id']] = $progress_row;
            }
            $lesson_items = [];
            $completed = 0;
            foreach ($lessons as $lesson) {
                $progress = $progress_map[$lesson['lesson_id']] ?? null;
                $status = $progress['status'] ?? 'assigned';
                if ($status === 'completed') $completed++;
                $lesson['progress_status'] = $status;
                $lesson['completion_percent'] = isset($progress['completion_percent']) ? (int)$progress['completion_percent'] : 0;
                $lesson['score'] = isset($progress['score']) ? (float)$progress['score'] : null;
                $lesson_items[] = $lesson;
            }
            $bundle = $this->coursePayload($course);
            $bundle['teacher_id'] = isset($row['teacher_id']) ? (int)$row['teacher_id'] : null;
            $teacher = !empty($row['teacher_id']) ? $this->firstRow('teacher', ['teacher_id' => $row['teacher_id']]) : null;
            $class = !empty($row['class_id']) ? $this->firstRow('class', ['class_id' => $row['class_id']]) : null;
            $section = !empty($row['section_id']) ? $this->firstRow('section', ['section_id' => $row['section_id']]) : null;
            $bundle['student_course_id'] = (int)$row['student_course_id'];
            $bundle['teacher_name'] = $teacher['name'] ?? '';
            $bundle['class_name'] = $class['name'] ?? '';
            $bundle['section_name'] = $section['name'] ?? '';
            $bundle['lesson_count'] = count($lesson_items);
            $bundle['completed_lessons'] = $completed;
            $bundle['progress_percent'] = $lesson_items ? (int)round($completed / count($lesson_items) * 100) : 0;
            $bundle['lessons'] = $lesson_items;
            $courses[] = $bundle;
        }
        return $courses;
    }

    private function assignStudentCourse($student_id, $course_id, $assigned_by_role, $assigned_by_id, $teacher_id = null, $class_id = null, $section_id = null) {
        $this->ensureWorkflowTables();

        $existing = $this->firstRow('ms_student_courses', [
            'student_id' => $student_id,
            'course_id' => $course_id,
        ]);

        $payload = [
            'student_id' => (int)$student_id,
            'course_id' => (int)$course_id,
            'assigned_by_role' => $assigned_by_role,
            'assigned_by_id' => $assigned_by_id ?: null,
            'teacher_id' => $teacher_id ?: null,
            'class_id' => $class_id ?: null,
            'section_id' => $section_id ?: null,
            'status' => 'active',
        ];

        if ($existing) {
            $this->db->where('student_course_id', $existing['student_course_id'])->update('ms_student_courses', $payload);
            $student_course_id = (int)$existing['student_course_id'];
        } else {
            $this->db->insert('ms_student_courses', $payload);
            $student_course_id = (int)$this->db->insert_id();
        }

        $lessons = $this->db->order_by('position', 'ASC')
            ->get_where('ms_course_lessons', ['course_id' => $course_id])
            ->result_array();

        foreach ($lessons as $lesson) {
            $assignment = $this->firstRow('ms_student_lesson_assignments', [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'lesson_id' => $lesson['lesson_id'],
            ]);

            if (!$assignment) {
                $this->db->insert('ms_student_lesson_assignments', [
                    'student_id' => (int)$student_id,
                    'course_id' => (int)$course_id,
                    'lesson_id' => (int)$lesson['lesson_id'],
                    'teacher_id' => $teacher_id ?: null,
                    'status' => 'assigned',
                ]);
            }

            $progress = $this->firstRow('ms_lesson_progress', [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'lesson_id' => $lesson['lesson_id'],
            ]);

            if (!$progress) {
                $this->db->insert('ms_lesson_progress', [
                    'student_id' => (int)$student_id,
                    'course_id' => (int)$course_id,
                    'lesson_id' => (int)$lesson['lesson_id'],
                    'status' => 'not_started',
                    'completion_percent' => 0,
                    'xp_earned' => 0,
                ]);
            }
        }

        return $student_course_id;
    }

    private function freshProgressState() {
        return [
            'version' => 2,
            'xp' => 0,
            'streak' => [
                'current' => 0,
                'best' => 0,
                'lastDate' => null,
            ],
            'lessons' => new stdClass(),
        ];
    }

    private function normalizeProgressState($state) {
        $fresh = $this->freshProgressState();
        if (!is_array($state)) return $fresh;

        $streak = isset($state['streak']) && is_array($state['streak']) ? $state['streak'] : [];
        $lessons = isset($state['lessons']) && is_array($state['lessons']) ? $state['lessons'] : [];
        $normalized_lessons = [];

        foreach ($lessons as $key => $lesson) {
            if (!is_array($lesson)) continue;
            $lesson_key = substr((string)($lesson['key'] ?? $key), 0, 120);
            if ($lesson_key === '') continue;
            $normalized_lessons[$lesson_key] = [
                'key' => $lesson_key,
                'module' => substr((string)($lesson['module'] ?? 'unknown'), 0, 120),
                'lessonNum' => isset($lesson['lessonNum']) && is_numeric($lesson['lessonNum']) ? (int)$lesson['lessonNum'] : 0,
                'title' => substr((string)($lesson['title'] ?? $lesson_key), 0, 255),
                'firstSeen' => isset($lesson['firstSeen']) && is_numeric($lesson['firstSeen']) ? (int)$lesson['firstSeen'] : round(microtime(true) * 1000),
                'completedAt' => isset($lesson['completedAt']) && is_numeric($lesson['completedAt']) ? (int)$lesson['completedAt'] : null,
                'practiceScore' => isset($lesson['practiceScore']) && is_numeric($lesson['practiceScore']) ? (int)$lesson['practiceScore'] : 0,
                'practiceTotal' => isset($lesson['practiceTotal']) && is_numeric($lesson['practiceTotal']) ? max(1, (int)$lesson['practiceTotal']) : 3,
                'practiceAttempts' => isset($lesson['practiceAttempts']) && is_numeric($lesson['practiceAttempts']) ? (int)$lesson['practiceAttempts'] : 0,
                'exitAttempts' => isset($lesson['exitAttempts']) && is_numeric($lesson['exitAttempts']) ? (int)$lesson['exitAttempts'] : 0,
                'bossAttempts' => isset($lesson['bossAttempts']) && is_numeric($lesson['bossAttempts']) ? (int)$lesson['bossAttempts'] : 0,
                'bossWon' => !empty($lesson['bossWon']),
                'bossBestTime' => isset($lesson['bossBestTime']) && is_numeric($lesson['bossBestTime']) ? (int)$lesson['bossBestTime'] : null,
                'bossWonAt' => isset($lesson['bossWonAt']) && is_numeric($lesson['bossWonAt']) ? (int)$lesson['bossWonAt'] : null,
                'xpEarned' => isset($lesson['xpEarned']) && is_numeric($lesson['xpEarned']) ? (int)$lesson['xpEarned'] : 0,
            ];
        }

        return [
            'version' => 2,
            'xp' => isset($state['xp']) && is_numeric($state['xp']) ? (int)$state['xp'] : 0,
            'streak' => [
                'current' => isset($streak['current']) && is_numeric($streak['current']) ? (int)$streak['current'] : 0,
                'best' => isset($streak['best']) && is_numeric($streak['best']) ? (int)$streak['best'] : 0,
                'lastDate' => isset($streak['lastDate']) ? (string)$streak['lastDate'] : null,
            ],
            'lessons' => $normalized_lessons,
        ];
    }

    private function readProgressRow($owner_key) {
        $this->ensureProgressTable();
        return $this->db->get_where('ms_progress_state', ['owner_key' => $owner_key])->row_array();
    }

    private function saveProgressRow($owner, $state) {
        $this->ensureProgressTable();
        $normalized = $this->normalizeProgressState($state);
        $encoded = json_encode($normalized);
        if (!$encoded) {
            $this->json(['error' => 'Could not encode progress state'], 400);
        }
        if (strlen($encoded) > 500000) {
            $this->json(['error' => 'Progress payload too large'], 413);
        }

        $payload = [
            'owner_key' => $owner['owner_key'],
            'user_role' => $owner['role'],
            'user_id' => $owner['user_id'],
            'progress_token' => $owner['token'],
            'progress_json' => $encoded,
        ];

        $existing = $this->readProgressRow($owner['owner_key']);
        if ($existing) {
            $this->db->where('owner_key', $owner['owner_key']);
            $this->db->update('ms_progress_state', $payload);
        } else {
            $this->db->insert('ms_progress_state', $payload);
        }

        return $normalized;
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

        // Role config: table, id field, session key, redirect — matches Login.php exactly
        $role_map = [
            'admin'   => ['table' => 'admin',   'id_field' => 'admin_id',   'sess_key' => 'admin_login',   'redirect' => 'admin/dashboard'],
            'teacher' => ['table' => 'teacher',  'id_field' => 'teacher_id', 'sess_key' => 'teacher_login', 'redirect' => 'teacher.html'],
            'student' => ['table' => 'student',  'id_field' => 'student_id', 'sess_key' => 'student_login', 'redirect' => 'dashboard.html'],
            'parent'  => ['table' => 'parent',   'id_field' => 'parent_id',  'sess_key' => 'parent_login',  'redirect' => 'parent.html'],
        ];

        // Try hinted role first; fall back to all roles
        $try_roles = ($role && isset($role_map[$role]))
            ? [$role => $role_map[$role]]
            : $role_map;

        foreach ($try_roles as $role_name => $cfg) {
            // Look up by email only, then verify password (supports bcrypt + legacy SHA1)
            $user = $this->db->get_where($cfg['table'], ['email' => $email])->row_array();
            if (!$user) continue;

            $stored = $user['password'];
            $matched = false;
            $new_hash = null;

            if (password_verify($password, $stored)) {
                // bcrypt match
                $matched = true;
            } elseif (sha1($password) === $stored) {
                // Legacy SHA1 — upgrade to bcrypt
                $matched  = true;
                $new_hash = password_hash($password, PASSWORD_BCRYPT);
            }

            if (!$matched) continue;

            // Upgrade legacy hash in DB if needed
            if ($new_hash) {
                $this->db->where('email', $email);
                $this->db->update($cfg['table'], ['password' => $new_hash]);
            }

            $uid = $user[$cfg['id_field']];
            $this->session->set_userdata([
                $cfg['sess_key']    => '1',
                $cfg['id_field']    => $uid,
                'login_user_id'     => $uid,
                'user_id'           => $uid,
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
     * GET /api/progress?token=<optional>
     */
    public function progress() {
        $token = $this->input->get('token', TRUE) ?: '';
        $owner = $this->currentProgressOwner($token);
        $row = $this->readProgressRow($owner['owner_key']);
        $state = $row ? json_decode($row['progress_json'], true) : $this->freshProgressState();
        $this->json([
            'success' => true,
            'owner' => [
                'key' => $owner['owner_key'],
                'role' => $owner['role'],
                'token' => $owner['token'],
            ],
            'progress' => $this->normalizeProgressState($state),
        ]);
    }

    /**
     * POST /api/progress/save
     * Body: JSON { token?: string, progress: {...} }
     */
    public function progress_save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $body = $this->getJsonInput();
        $token = isset($body['token']) ? (string)$body['token'] : $this->getPosted('token');
        $progress = isset($body['progress']) && is_array($body['progress']) ? $body['progress'] : [];

        if (!$progress) {
            $posted = $this->getPosted('progress');
            if ($posted) {
                $decoded = json_decode($posted, true);
                if (is_array($decoded)) $progress = $decoded;
            }
        }

        if (!$progress) {
            $this->json(['error' => 'Progress payload is required'], 400);
        }

        $owner = $this->currentProgressOwner($token);
        $saved = $this->saveProgressRow($owner, $progress);
        $this->json([
            'success' => true,
            'owner' => [
                'key' => $owner['owner_key'],
                'role' => $owner['role'],
                'token' => $owner['token'],
            ],
            'progress' => $saved,
        ]);
    }

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
        $this->ensureWorkflowTables();
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $shared_courses = $this->studentCourseBundle($student_id);
        if ($shared_courses) {
            $courses = [];
            foreach ($shared_courses as $course) {
                $courses[] = [
                    'subject' => $course['title'],
                    'teacher' => $course['teacher_name'] ?? 'MindStrong',
                    'progress' => (int)($course['progress_percent'] ?? 0),
                ];
            }
            $this->json(['courses' => $courses]);
        }

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
        $this->ensureWorkflowTables();
        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $enroll = $this->firstRow('enroll', ['student_id' => $student_id]);
        $class_id = $enroll ? (int)$enroll['class_id'] : 0;
        $section_id = $enroll ? (int)($enroll['section_id'] ?? 0) : 0;

        $assignments = [];
        if ($class_id) {
            $this->db->where('class_id', $class_id);
            $this->db->group_start();
            $this->db->where('section_id IS NULL', null, false);
            if ($section_id) {
                $this->db->or_where('section_id', $section_id);
            }
            $this->db->group_end();
            $this->db->order_by('due_date IS NULL', 'ASC', false);
            $this->db->order_by('due_date', 'ASC');
            $rows = $this->db->get('ms_assignments')->result_array();
            foreach ($rows as $row) {
                $teacher = $this->firstRow('teacher', ['teacher_id' => $row['teacher_id']]);
                $assignments[] = [
                    'id' => (int)$row['assignment_id'],
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'teacher' => $teacher['name'] ?? 'Teacher',
                    'due_date' => $row['due_date'],
                    'max_score' => (float)$row['max_score'],
                    'status' => $row['status'] ?: 'published',
                    'description' => $row['description'] ?? '',
                ];
            }
        }

        if (!$assignments) {
            $marks = $this->db->get_where('mark', ['student_id' => $student_id])->result_array();
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
                'invoice_id' => (int)$inv['invoice_id'],
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

    /**
     * GET /api/parent/schedule?student_id=X
     */
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

    /**
     * POST /api/parent/fees/pay
     */
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

    /**
     * GET /api/parent/teachers?student_id=X
     */
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

    /**
     * GET /api/parent/messages?student_id=X&teacher_id=Y
     */
    public function parent_messages() {
        $this->requireSession('parent_login');
        $this->ensureWorkflowTables();
        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = (int)($this->input->get('student_id', TRUE) ?: 0);
        $teacher_id = (int)($this->input->get('teacher_id', TRUE) ?: 0);

        if (!$student_id || !$teacher_id) {
            $this->json(['messages' => []]);
        }

        $rows = $this->db->order_by('created_at', 'ASC')->get_where('ms_parent_messages', [
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
        ])->result_array();

        $messages = array_map(function ($row) {
            return [
                'message_id' => (int)$row['message_id'],
                'sender_role' => $row['sender_role'],
                'sender_name' => $row['sender_name'],
                'message_text' => $row['message_text'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        $this->json(['messages' => $messages]);
    }

    /**
     * POST /api/parent/messages
     */
    public function parent_messages_send() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('parent_login');
        $this->ensureWorkflowTables();
        $parent_id = $this->session->userdata('parent_id')
                  ?: $this->session->userdata('user_id');
        $parent = $this->firstRow('parent', ['parent_id' => $parent_id]);
        $body = $this->getJsonInput();
        $student_id = (int)($body['student_id'] ?? $this->getPosted('student_id'));
        $teacher_id = (int)($body['teacher_id'] ?? $this->getPosted('teacher_id'));
        $message_text = trim((string)($body['message_text'] ?? $this->getPosted('message_text')));

        if (!$student_id || !$teacher_id || $message_text === '') {
            $this->json(['error' => 'Student, teacher, and message are required'], 400);
        }

        $student = $this->firstRow('student', ['student_id' => $student_id]);
        if (!$student || (int)($student['parent_id'] ?? 0) !== (int)$parent_id) {
            $this->json(['error' => 'Unauthorized'], 403);
        }

        $teacher = $this->firstRow('teacher', ['teacher_id' => $teacher_id]);
        if (!$teacher) {
            $this->json(['error' => 'Teacher not found'], 404);
        }

        $this->db->insert('ms_parent_messages', [
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'sender_role' => 'parent',
            'sender_name' => $parent['name'] ?? 'Parent',
            'message_text' => $message_text,
        ]);

        $this->json([
            'success' => true,
            'message_id' => (int)$this->db->insert_id(),
        ]);
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

    /**
     * GET /api/teacher/classes
     */
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

    /**
     * GET /api/teacher/schedule
     */
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

    /**
     * GET /api/teacher/assignments?class_id=X
     */
    public function teacher_assignments() {
        $this->requireSession('teacher_login');
        $this->ensureWorkflowTables();
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $class_id = (int)($this->input->get('class_id', TRUE) ?: 0);

        $this->db->where('teacher_id', $teacher_id);
        if ($class_id) {
            $this->db->where('class_id', $class_id);
        }
        $this->db->order_by('created_at', 'DESC');
        $rows = $this->db->get('ms_assignments')->result_array();

        $assignments = array_map(function ($row) {
            return [
                'assignment_id' => (int)$row['assignment_id'],
                'class_id' => (int)$row['class_id'],
                'title' => $row['title'],
                'description' => $row['description'] ?? '',
                'type' => $row['type'],
                'due_date' => $row['due_date'],
                'max_score' => (float)$row['max_score'],
                'status' => $row['status'],
            ];
        }, $rows);

        $this->json(['assignments' => $assignments]);
    }

    /**
     * POST /api/teacher/assignments
     */
    public function teacher_assignments_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('teacher_login');
        $this->ensureWorkflowTables();
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $body = $this->getJsonInput();

        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $title = trim((string)($body['title'] ?? $this->getPosted('title')));
        $description = trim((string)($body['description'] ?? $this->getPosted('description')));
        $type = trim((string)($body['type'] ?? $this->getPosted('type') ?: 'Homework'));
        $due_date = trim((string)($body['due_date'] ?? $this->getPosted('due_date')));
        $max_score = (float)($body['max_score'] ?? $this->getPosted('max_score') ?: 100);
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));

        if (!$class_id || $title === '') {
            $this->json(['error' => 'Class and title are required'], 400);
        }
        if (!$this->teacherOwnsClass($teacher_id, $class_id)) {
            $this->json(['error' => 'Unauthorized'], 403);
        }

        $this->db->insert('ms_assignments', [
            'teacher_id' => $teacher_id,
            'class_id' => $class_id,
            'section_id' => $section_id ?: null,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'due_date' => $due_date ?: null,
            'max_score' => $max_score > 0 ? $max_score : 100,
            'status' => 'published',
        ]);

        $this->json([
            'success' => true,
            'assignment_id' => (int)$this->db->insert_id(),
        ]);
    }

    /**
     * POST /api/teacher/attendance
     */
    public function teacher_attendance_save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('teacher_login');
        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $body = $this->getJsonInput();
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $attendance_date = trim((string)($body['date'] ?? $this->getPosted('date')));
        $records = isset($body['records']) && is_array($body['records']) ? $body['records'] : [];

        if (!$class_id || !$records) {
            $this->json(['error' => 'Class and attendance records are required'], 400);
        }
        if (!$this->teacherOwnsClass($teacher_id, $class_id)) {
            $this->json(['error' => 'Unauthorized'], 403);
        }

        $bounds = $this->currentDayBounds($attendance_date ?: null);
        $saved = 0;
        foreach ($records as $record) {
            $student_id = (int)($record['student_id'] ?? 0);
            $status_key = strtoupper((string)($record['status'] ?? 'P'));
            if (!$student_id) {
                continue;
            }

            $status = 1;
            if ($status_key === 'A') $status = 0;
            if ($status_key === 'L') $status = 2;

            $enroll = $this->firstRow('enroll', ['student_id' => $student_id, 'class_id' => $class_id]);
            if (!$enroll) {
                continue;
            }

            $this->db->where('student_id', $student_id);
            $this->db->where('class_id', $class_id);
            $this->db->where('timestamp >=', $bounds['start']);
            $this->db->where('timestamp <=', $bounds['end']);
            $existing = $this->db->get('attendance')->row_array();

            $payload = $this->filterPayloadForTable('attendance', [
                'student_id' => $student_id,
                'class_id' => $class_id,
                'section_id' => $enroll['section_id'] ?? null,
                'status' => $status,
                'timestamp' => $bounds['start'],
                'year' => date('Y', $bounds['start']),
            ]);

            if ($existing) {
                $this->db->where('attendance_id', $existing['attendance_id'])->update('attendance', $payload);
            } else {
                $this->db->insert('attendance', $payload);
            }
            $saved++;
        }

        $this->json([
            'success' => true,
            'saved' => $saved,
        ]);
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
     * POST /api/admin/users
     */
    public function admin_users_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $body = $this->getJsonInput();
        $role = strtolower(trim((string)($body['role'] ?? $this->getPosted('role') ?: 'student')));
        $first_name = trim((string)($body['first_name'] ?? $this->getPosted('first_name')));
        $last_name = trim((string)($body['last_name'] ?? $this->getPosted('last_name')));
        $name = trim((string)($body['name'] ?? $this->getPosted('name')));
        $email = trim((string)($body['email'] ?? $this->getPosted('email')));
        $password = (string)($body['password'] ?? $this->getPosted('password'));
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));
        $parent_id = (int)($body['parent_id'] ?? $this->getPosted('parent_id'));

        if ($name === '') {
            $name = trim($first_name . ' ' . $last_name);
        }
        if ($name === '' || $email === '' || $password === '') {
            $this->json(['error' => 'Name, email, role, and password are required'], 400);
        }

        $table_map = [
            'student' => ['table' => 'student', 'id_field' => 'student_id'],
            'teacher' => ['table' => 'teacher', 'id_field' => 'teacher_id'],
            'parent' => ['table' => 'parent', 'id_field' => 'parent_id'],
            'admin' => ['table' => 'admin', 'id_field' => 'admin_id'],
        ];
        if (!isset($table_map[$role])) {
            $this->json(['error' => 'Invalid role'], 400);
        }

        $cfg = $table_map[$role];
        $table = $cfg['table'];
        if ($this->db->get_where($table, ['email' => $email])->row_array()) {
            $this->json(['error' => 'Email already exists'], 409);
        }

        $payload = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'phone' => '',
            'address' => '',
            'birthday' => '',
            'sex' => '',
            'religion' => '',
            'blood_group' => '',
            'department' => '',
            'qualification' => '',
            'facebook_link' => '',
            'twitter_link' => '',
            'linkedin_link' => '',
            'parent_id' => $parent_id ?: null,
        ];
        $payload = $this->filterPayloadForTable($table, $payload);

        $this->db->insert($table, $payload);
        $user_id = (int)$this->db->insert_id();

        if ($role === 'student' && $class_id) {
            $enroll_payload = $this->filterPayloadForTable('enroll', [
                'student_id' => $user_id,
                'class_id' => $class_id,
                'section_id' => $section_id ?: null,
            ]);
            if ($enroll_payload) {
                $this->db->insert('enroll', $enroll_payload);
            }
        }

        $this->json([
            'success' => true,
            'role' => $role,
            'user_id' => $user_id,
            'name' => $name,
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

    /**
     * GET /api/admin/courses
     */
    public function admin_courses() {
        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();

        $rows = $this->db->order_by('updated_at', 'DESC')->get('ms_courses')->result_array();
        $courses = [];
        foreach ($rows as $row) {
            $course = $this->coursePayload($row);
            $class = !empty($row['class_id']) ? $this->firstRow('class', ['class_id' => $row['class_id']]) : null;
            $section = !empty($row['section_id']) ? $this->firstRow('section', ['section_id' => $row['section_id']]) : null;
            $course['class_name'] = $class['name'] ?? '';
            $course['section_name'] = $section['name'] ?? '';
            $course['lesson_count'] = (int)$this->db->where('course_id', $row['course_id'])->count_all_results('ms_course_lessons');
            $course['teacher_count'] = (int)$this->db->where('course_id', $row['course_id'])->count_all_results('ms_teacher_courses');
            $course['student_count'] = (int)$this->db->where('course_id', $row['course_id'])->count_all_results('ms_student_courses');
            $course['lessons'] = $this->courseLessons($row['course_id']);
            $courses[] = $course;
        }

        $this->json(['courses' => $courses]);
    }

    /**
     * POST /api/admin/courses/create
     */
    public function admin_courses_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();

        $body = $this->getJsonInput();
        $title = trim((string)($body['title'] ?? $this->getPosted('title')));
        $description = trim((string)($body['description'] ?? $this->getPosted('description')));
        $subject_area = trim((string)($body['subject_area'] ?? $this->getPosted('subject_area') ?: 'General'));
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));

        if ($title === '') {
            $this->json(['error' => 'Course title is required'], 400);
        }

        $slug = $this->ensureUniqueSlug('ms_courses', 'slug', $this->slugify($title));
        $this->db->insert('ms_courses', [
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'subject_area' => $subject_area,
            'class_id' => $class_id ?: null,
            'section_id' => $section_id ?: null,
            'status' => 'active',
        ]);

        $course = $this->firstRow('ms_courses', ['course_id' => $this->db->insert_id()]);
        $this->json([
            'success' => true,
            'course' => $this->coursePayload($course),
        ]);
    }

    /**
     * GET /api/admin/lessons
     */
    public function admin_lessons() {
        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();

        $rows = $this->db->order_by('updated_at', 'DESC')->get('ms_lessons')->result_array();
        $lessons = array_map([$this, 'lessonPayload'], $rows);
        $this->json(['lessons' => $lessons]);
    }

    /**
     * POST /api/admin/lessons/create
     */
    public function admin_lessons_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();

        $body = $this->getJsonInput();
        $title = trim((string)($body['title'] ?? $this->getPosted('title')));
        $lesson_url = trim((string)($body['lesson_url'] ?? $this->getPosted('lesson_url')));
        $game_url = trim((string)($body['game_url'] ?? $this->getPosted('game_url')));
        $lesson_type = trim((string)($body['lesson_type'] ?? $this->getPosted('lesson_type') ?: 'lesson'));
        $subject_area = trim((string)($body['subject_area'] ?? $this->getPosted('subject_area') ?: 'General'));
        $description = trim((string)($body['description'] ?? $this->getPosted('description')));
        $estimated_minutes = max(5, (int)($body['estimated_minutes'] ?? $this->getPosted('estimated_minutes') ?: 20));

        if ($title === '' || $lesson_url === '') {
            $this->json(['error' => 'Lesson title and lesson URL are required'], 400);
        }

        $slug = $this->ensureUniqueSlug('ms_lessons', 'slug', $this->slugify($title));
        $this->db->insert('ms_lessons', [
            'title' => $title,
            'slug' => $slug,
            'lesson_type' => $lesson_type,
            'subject_area' => $subject_area,
            'description' => $description,
            'lesson_url' => $lesson_url,
            'game_url' => $game_url ?: null,
            'estimated_minutes' => $estimated_minutes,
            'status' => 'active',
        ]);

        $lesson = $this->firstRow('ms_lessons', ['lesson_id' => $this->db->insert_id()]);
        $this->json([
            'success' => true,
            'lesson' => $this->lessonPayload($lesson),
        ]);
    }

    /**
     * POST /api/admin/course-lessons/create
     */
    public function admin_course_lessons_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();

        $body = $this->getJsonInput();
        $course_id = (int)($body['course_id'] ?? $this->getPosted('course_id'));
        $lesson_id = (int)($body['lesson_id'] ?? $this->getPosted('lesson_id'));
        $module_title = trim((string)($body['module_title'] ?? $this->getPosted('module_title')));
        $position = max(1, (int)($body['position'] ?? $this->getPosted('position') ?: 1));
        $is_required = isset($body['is_required']) ? (int)!empty($body['is_required']) : (int)!empty($this->getPosted('is_required'));

        if (!$course_id || !$lesson_id) {
            $this->json(['error' => 'Course and lesson are required'], 400);
        }

        $existing = $this->firstRow('ms_course_lessons', [
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
        ]);

        $payload = [
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'module_title' => $module_title ?: null,
            'position' => $position,
            'is_required' => $is_required ? 1 : 0,
        ];

        if ($existing) {
            $this->db->where('course_lesson_id', $existing['course_lesson_id'])->update('ms_course_lessons', $payload);
        } else {
            $this->db->insert('ms_course_lessons', $payload);
        }

        $this->json([
            'success' => true,
            'lessons' => $this->courseLessons($course_id),
        ]);
    }

    /**
     * POST /api/admin/teacher-courses/create
     */
    public function admin_teacher_courses_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();

        $body = $this->getJsonInput();
        $teacher_id = (int)($body['teacher_id'] ?? $this->getPosted('teacher_id'));
        $course_id = (int)($body['course_id'] ?? $this->getPosted('course_id'));
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));

        if (!$teacher_id || !$course_id) {
            $this->json(['error' => 'Teacher and course are required'], 400);
        }

        $existing = $this->firstRow('ms_teacher_courses', [
            'teacher_id' => $teacher_id,
            'course_id' => $course_id,
            'class_id' => $class_id ?: null,
            'section_id' => $section_id ?: null,
        ]);

        if (!$existing) {
            $this->db->insert('ms_teacher_courses', [
                'teacher_id' => $teacher_id,
                'course_id' => $course_id,
                'class_id' => $class_id ?: null,
                'section_id' => $section_id ?: null,
            ]);
        }

        $this->json(['success' => true]);
    }

    /**
     * POST /api/admin/student-courses/create
     */
    public function admin_student_courses_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('admin_login');
        $this->ensureWorkflowTables();

        $body = $this->getJsonInput();
        $student_id = (int)($body['student_id'] ?? $this->getPosted('student_id'));
        $course_id = (int)($body['course_id'] ?? $this->getPosted('course_id'));
        $teacher_id = (int)($body['teacher_id'] ?? $this->getPosted('teacher_id'));
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));

        if (!$student_id || !$course_id) {
            $this->json(['error' => 'Student and course are required'], 400);
        }

        $admin_id = $this->session->userdata('admin_id') ?: $this->session->userdata('user_id');
        $this->assignStudentCourse($student_id, $course_id, 'admin', $admin_id, $teacher_id ?: null, $class_id ?: null, $section_id ?: null);

        $this->json([
            'success' => true,
            'courses' => $this->studentCourseBundle($student_id),
        ]);
    }

    /**
     * GET /api/teacher/course-catalog
     */
    public function teacher_course_catalog() {
        $this->requireSession('teacher_login');
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();

        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');

        $catalog = [];
        $courses = $this->db->order_by('title', 'ASC')->get_where('ms_courses', ['status' => 'active'])->result_array();
        foreach ($courses as $course) {
            $item = $this->coursePayload($course);
            $assignment = $this->db->order_by('teacher_course_id', 'DESC')->get_where('ms_teacher_courses', [
                'teacher_id' => $teacher_id,
                'course_id' => $course['course_id'],
            ])->row_array();
            $class = !empty($assignment['class_id']) ? $this->firstRow('class', ['class_id' => $assignment['class_id']]) : null;
            $section = !empty($assignment['section_id']) ? $this->firstRow('section', ['section_id' => $assignment['section_id']]) : null;
            $item['class_id'] = !empty($assignment['class_id']) ? (int)$assignment['class_id'] : null;
            $item['section_id'] = !empty($assignment['section_id']) ? (int)$assignment['section_id'] : null;
            $item['class_name'] = $class['name'] ?? '';
            $item['section_name'] = $section['name'] ?? '';
            $item['lessons'] = $this->courseLessons($course['course_id']);
            $item['student_count'] = (int)$this->db->where([
                'course_id' => $course['course_id'],
                'teacher_id' => $teacher_id,
                'status' => 'active',
            ])->count_all_results('ms_student_courses');
            $item['is_assigned_teacher'] = !empty($assignment);
            $catalog[] = $item;
        }

        $this->json(['courses' => $catalog]);
    }

    /**
     * POST /api/teacher/student-courses/create
     */
    public function teacher_student_courses_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('teacher_login');
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();

        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $body = $this->getJsonInput();
        $student_id = (int)($body['student_id'] ?? $this->getPosted('student_id'));
        $course_id = (int)($body['course_id'] ?? $this->getPosted('course_id'));
        $class_id = (int)($body['class_id'] ?? $this->getPosted('class_id'));
        $section_id = (int)($body['section_id'] ?? $this->getPosted('section_id'));

        if (!$student_id || !$course_id) {
            $this->json(['error' => 'Student and course are required'], 400);
        }

        $assignment = $this->db->get_where('ms_teacher_courses', [
            'teacher_id' => $teacher_id,
            'course_id' => $course_id,
        ])->row_array();
        if (!$assignment) {
            $this->db->insert('ms_teacher_courses', [
                'teacher_id' => $teacher_id,
                'course_id' => $course_id,
                'class_id' => $class_id ?: null,
                'section_id' => $section_id ?: null,
            ]);
            $assignment = $this->db->get_where('ms_teacher_courses', [
                'teacher_id' => $teacher_id,
                'course_id' => $course_id,
            ])->row_array();
        }

        $this->assignStudentCourse(
            $student_id,
            $course_id,
            'teacher',
            $teacher_id,
            $teacher_id,
            $class_id ?: ($assignment['class_id'] ?? null),
            $section_id ?: ($assignment['section_id'] ?? null)
        );

        $this->json([
            'success' => true,
            'courses' => $this->studentCourseBundle($student_id),
        ]);
    }

    /**
     * POST /api/teacher/student-lessons/create
     */
    public function teacher_student_lessons_create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $this->requireSession('teacher_login');
        $this->ensureWorkflowTables();
        $this->ensureBuiltCurriculumCatalog();

        $teacher_id = $this->session->userdata('teacher_id')
                   ?: $this->session->userdata('user_id');
        $body = $this->getJsonInput();
        $student_id = (int)($body['student_id'] ?? $this->getPosted('student_id'));
        $course_id = (int)($body['course_id'] ?? $this->getPosted('course_id'));
        $lesson_id = (int)($body['lesson_id'] ?? $this->getPosted('lesson_id'));

        if (!$student_id || !$course_id || !$lesson_id) {
            $this->json(['error' => 'Student, course, and lesson are required'], 400);
        }

        $assignment = $this->db->get_where('ms_teacher_courses', [
            'teacher_id' => $teacher_id,
            'course_id' => $course_id,
        ])->row_array();
        if (!$assignment) {
            $this->db->insert('ms_teacher_courses', [
                'teacher_id' => $teacher_id,
                'course_id' => $course_id,
            ]);
        }

        $existing = $this->firstRow('ms_student_lesson_assignments', [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
        ]);

        if ($existing) {
            $this->db->where('student_lesson_assignment_id', $existing['student_lesson_assignment_id'])->update('ms_student_lesson_assignments', [
                'teacher_id' => $teacher_id,
                'status' => 'assigned',
            ]);
        } else {
            $this->db->insert('ms_student_lesson_assignments', [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'lesson_id' => $lesson_id,
                'teacher_id' => $teacher_id,
                'status' => 'assigned',
            ]);
        }

        $progress = $this->firstRow('ms_lesson_progress', [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
        ]);
        if (!$progress) {
            $this->db->insert('ms_lesson_progress', [
                'student_id' => $student_id,
                'course_id' => $course_id,
                'lesson_id' => $lesson_id,
                'status' => 'not_started',
                'completion_percent' => 0,
                'xp_earned' => 0,
            ]);
        }

        $this->json(['success' => true]);
    }

    /**
     * GET /api/student/learning
     */
    public function student_learning() {
        $this->requireSession('student_login');
        $this->ensureWorkflowTables();

        $student_id = $this->session->userdata('student_id')
                   ?: $this->session->userdata('user_id');

        $this->json(['courses' => $this->studentCourseBundle($student_id)]);
    }

    /**
     * GET /api/parent/courses
     */
    public function parent_courses() {
        $this->requireSession('parent_login');
        $this->ensureWorkflowTables();

        $parent_id  = $this->session->userdata('parent_id')
                   ?: $this->session->userdata('user_id');
        $student_id = (int)($this->input->get('student_id', TRUE) ?: 0);

        if (!$student_id) {
            $child = $this->db->get_where('student', ['parent_id' => $parent_id])->row_array();
            $student_id = $child ? (int)$child['student_id'] : 0;
        }

        if (!$student_id) {
            $this->json(['courses' => []]);
        }

        $child = $this->firstRow('student', ['student_id' => $student_id, 'parent_id' => $parent_id]);
        if (!$child) {
            $this->json(['error' => 'Student not found'], 404);
        }

        $this->json([
            'student_id' => $student_id,
            'courses' => $this->studentCourseBundle($student_id),
        ]);
    }

}
