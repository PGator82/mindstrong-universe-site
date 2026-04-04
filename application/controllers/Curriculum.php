<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Curriculum extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        header('Content-Type: application/json; charset=UTF-8');
    }

    private function json($data, $code = 200)
    {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    private function requireTeacherSession()
    {
        if ($this->session->userdata('teacher_login') != '1') {
            $this->json(['error' => 'Unauthorized'], 401);
        }
    }

    private function curriculumCourseLabel($key)
    {
        $labels = [
            'foundations_math' => ['title' => 'Foundations Math', 'subject' => 'Mathematics', 'description' => 'Built lessons from the live Foundations math sequence.'],
            'foundations_science' => ['title' => 'Foundations Science', 'subject' => 'Science', 'description' => 'Built lessons from the live Foundations science sequence.'],
            'foundations_english' => ['title' => 'Foundations English', 'subject' => 'English', 'description' => 'Built lessons from the live Foundations English sequence.'],
            'pre_algebra' => ['title' => 'Pre-Algebra', 'subject' => 'Mathematics', 'description' => 'Built lessons from the pre-algebra path.'],
        ];
        return $labels[$key] ?? ['title' => 'MindStrong Course', 'subject' => 'General', 'description' => 'Built curriculum lessons.'];
    }

    private function titleFromCurriculumPath($relative_path)
    {
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

    private function curriculumDescriptor($course_key, $lesson_url, $module_title = '')
    {
        return [
            'course_key' => $course_key,
            'title' => $this->titleFromCurriculumPath($lesson_url),
            'module_title' => $module_title,
            'lesson_url' => $lesson_url,
            'subject_area' => $this->curriculumCourseLabel($course_key)['subject'],
            'lesson_type' => 'lesson',
            'estimated_minutes' => 20,
        ];
    }

    private function builtCurriculumDescriptors()
    {
        $descriptors = [];

        for ($i = 1; $i <= 6; $i++) {
            $descriptors[] = $this->curriculumDescriptor('foundations_math', '/school/foundations/module-1/lesson-' . $i . '.html', 'Number Sense');
        }

        foreach (['algebra', 'functions', 'integers', 'linear', 'ratio'] as $module) {
            $descriptors[] = $this->curriculumDescriptor(
                'foundations_math',
                '/school/foundations/' . $module . '/index.html',
                $this->titleFromCurriculumPath('/school/foundations/' . $module . '/index.html')
            );
        }

        foreach ([
            '/school/foundations/fractions/index.html',
            '/school/foundations/fractions/meaning/index.html',
            '/school/foundations/fractions/equivalent/index.html',
            '/school/foundations/fractions/convert/index.html',
            '/school/foundations/fractions/add-sub/index.html',
            '/school/foundations/fractions/multiply/index.html',
            '/school/foundations/fractions/divide/index.html',
        ] as $path) {
            $descriptors[] = $this->curriculumDescriptor('foundations_math', $path, 'Fractions and Operations');
        }

        foreach ([
            '/school/foundations/geometry/index.html',
            '/school/foundations/geometry/angles/index.html',
            '/school/foundations/geometry/area/index.html',
            '/school/foundations/geometry/circles/index.html',
            '/school/foundations/geometry/coordinate/index.html',
            '/school/foundations/geometry/polygons/index.html',
            '/school/foundations/geometry/triangles/index.html',
        ] as $path) {
            $descriptors[] = $this->curriculumDescriptor('foundations_math', $path, 'Geometry Foundations');
        }

        foreach ([
            '/school/foundations/data/index.html',
            '/school/foundations/data/central/index.html',
            '/school/foundations/data/spread/index.html',
        ] as $path) {
            $descriptors[] = $this->curriculumDescriptor('foundations_math', $path, 'Data and Statistics');
        }

        foreach ([
            '/school/foundations/pre-algebra/index.html',
            '/school/foundations/pre-algebra/equations/index.html',
            '/school/foundations/pre-algebra/expressions/index.html',
            '/school/foundations/pre-algebra/percents/index.html',
            '/school/foundations/pre-algebra/proportions/index.html',
        ] as $path) {
            $descriptors[] = $this->curriculumDescriptor('pre_algebra', $path, 'Pre-Algebra');
        }

        for ($i = 1; $i <= 6; $i++) {
            $descriptors[] = $this->curriculumDescriptor('foundations_science', '/school/foundations-science/lesson-' . $i . '.html', 'Foundations Science');
            $descriptors[] = $this->curriculumDescriptor('foundations_english', '/school/foundations-english/lesson-' . $i . '.html', 'Foundations English');
        }

        return $descriptors;
    }

    public function teacher_catalog()
    {
        $this->requireTeacherSession();

        $courses = [];
        foreach (['foundations_math', 'pre_algebra', 'foundations_science', 'foundations_english'] as $slug) {
            $meta = $this->curriculumCourseLabel($slug);
            $courses[$slug] = [
                'course_id' => 0,
                'course_key' => $slug,
                'slug' => $slug,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'subject_area' => $meta['subject'],
                'status' => 'active',
                'student_count' => 0,
                'lessons' => [],
            ];
        }

        foreach ($this->builtCurriculumDescriptors() as $index => $descriptor) {
            $slug = $descriptor['course_key'];
            if (!isset($courses[$slug])) continue;
            $courses[$slug]['lessons'][] = [
                'lesson_id' => 0,
                'lesson_key' => $descriptor['lesson_url'],
                'title' => $descriptor['title'],
                'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $descriptor['lesson_url']), '-')),
                'lesson_type' => $descriptor['lesson_type'],
                'subject_area' => $descriptor['subject_area'],
                'description' => $descriptor['module_title'],
                'lesson_url' => $descriptor['lesson_url'],
                'game_url' => '',
                'estimated_minutes' => (int)$descriptor['estimated_minutes'],
                'status' => 'active',
                'module_title' => $descriptor['module_title'],
                'position' => $index + 1,
                'is_required' => true,
            ];
        }

        $this->json(['courses' => array_values($courses)]);
    }
}
