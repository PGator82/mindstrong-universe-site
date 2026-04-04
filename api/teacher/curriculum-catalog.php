<?php
header('Content-Type: application/json; charset=UTF-8');

session_start();

if (!isset($_SESSION['teacher_login']) || (string)$_SESSION['teacher_login'] !== '1') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function course_meta($key) {
    $labels = [
        'foundations_math' => ['title' => 'Foundations Math', 'subject' => 'Mathematics', 'description' => 'Built lessons from the live Foundations math sequence.'],
        'pre_algebra' => ['title' => 'Pre-Algebra', 'subject' => 'Mathematics', 'description' => 'Built lessons from the pre-algebra path.'],
        'foundations_science' => ['title' => 'Foundations Science', 'subject' => 'Science', 'description' => 'Built lessons from the live Foundations science sequence.'],
        'foundations_english' => ['title' => 'Foundations English', 'subject' => 'English', 'description' => 'Built lessons from the live Foundations English sequence.'],
    ];
    return $labels[$key];
}

function title_from_path($path) {
    $parts = explode('/', trim(str_replace('\\', '/', preg_replace('#^/school/#', '', $path)), '/'));
    $base = basename($path, '.html');
    if (preg_match('/^lesson-(\d+)$/', $base, $m)) {
        $group = $parts[count($parts) - 2] ?? 'lesson';
        $group = str_replace(['foundations-science', 'foundations-english'], ['science', 'english'], $group);
        return ucwords(str_replace('-', ' ', $group)) . ' Lesson ' . $m[1];
    }
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
        'ratio' => 'Ratios',
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
        'expressions' => 'Expressions',
        'equations' => 'Equations',
        'percents' => 'Percents',
        'proportions' => 'Proportions',
    ];
    return $map[$base] ?? ucwords(str_replace('-', ' ', $base));
}

function make_lessons($courseKey, $subject, $paths) {
    $items = [];
    foreach ($paths as $index => $path) {
        $items[] = [
            'lesson_id' => 0,
            'lesson_key' => $path,
            'title' => title_from_path($path),
            'slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($path)), '-'),
            'lesson_type' => 'lesson',
            'subject_area' => $subject,
            'description' => '',
            'lesson_url' => $path,
            'game_url' => '',
            'estimated_minutes' => 20,
            'status' => 'active',
            'module_title' => '',
            'position' => $index + 1,
            'is_required' => true,
        ];
    }
    return $items;
}

$courses = [];

$courses[] = array_merge(course_meta('foundations_math'), [
    'course_id' => 0,
    'course_key' => 'foundations_math',
    'slug' => 'foundations_math',
    'status' => 'active',
    'student_count' => 0,
    'lessons' => make_lessons('foundations_math', 'Mathematics', [
        '/school/foundations/module-1/lesson-1.html',
        '/school/foundations/module-1/lesson-2.html',
        '/school/foundations/module-1/lesson-3.html',
        '/school/foundations/module-1/lesson-4.html',
        '/school/foundations/module-1/lesson-5.html',
        '/school/foundations/module-1/lesson-6.html',
        '/school/foundations/algebra/index.html',
        '/school/foundations/fractions/index.html',
        '/school/foundations/fractions/meaning/index.html',
        '/school/foundations/fractions/equivalent/index.html',
        '/school/foundations/fractions/convert/index.html',
        '/school/foundations/fractions/add-sub/index.html',
        '/school/foundations/fractions/multiply/index.html',
        '/school/foundations/fractions/divide/index.html',
        '/school/foundations/functions/index.html',
        '/school/foundations/geometry/index.html',
        '/school/foundations/geometry/angles/index.html',
        '/school/foundations/geometry/area/index.html',
        '/school/foundations/geometry/circles/index.html',
        '/school/foundations/geometry/coordinate/index.html',
        '/school/foundations/geometry/polygons/index.html',
        '/school/foundations/geometry/triangles/index.html',
        '/school/foundations/integers/index.html',
        '/school/foundations/linear/index.html',
        '/school/foundations/ratio/index.html',
        '/school/foundations/data/index.html',
        '/school/foundations/data/central/index.html',
        '/school/foundations/data/spread/index.html',
    ]),
]);

$courses[] = array_merge(course_meta('pre_algebra'), [
    'course_id' => 0,
    'course_key' => 'pre_algebra',
    'slug' => 'pre_algebra',
    'status' => 'active',
    'student_count' => 0,
    'lessons' => make_lessons('pre_algebra', 'Mathematics', [
        '/school/foundations/pre-algebra/index.html',
        '/school/foundations/pre-algebra/equations/index.html',
        '/school/foundations/pre-algebra/expressions/index.html',
        '/school/foundations/pre-algebra/percents/index.html',
        '/school/foundations/pre-algebra/proportions/index.html',
    ]),
]);

$courses[] = array_merge(course_meta('foundations_science'), [
    'course_id' => 0,
    'course_key' => 'foundations_science',
    'slug' => 'foundations_science',
    'status' => 'active',
    'student_count' => 0,
    'lessons' => make_lessons('foundations_science', 'Science', [
        '/school/foundations-science/lesson-1.html',
        '/school/foundations-science/lesson-2.html',
        '/school/foundations-science/lesson-3.html',
        '/school/foundations-science/lesson-4.html',
        '/school/foundations-science/lesson-5.html',
        '/school/foundations-science/lesson-6.html',
    ]),
]);

$courses[] = array_merge(course_meta('foundations_english'), [
    'course_id' => 0,
    'course_key' => 'foundations_english',
    'slug' => 'foundations_english',
    'status' => 'active',
    'student_count' => 0,
    'lessons' => make_lessons('foundations_english', 'English', [
        '/school/foundations-english/lesson-1.html',
        '/school/foundations-english/lesson-2.html',
        '/school/foundations-english/lesson-3.html',
        '/school/foundations-english/lesson-4.html',
        '/school/foundations-english/lesson-5.html',
        '/school/foundations-english/lesson-6.html',
    ]),
]);

echo json_encode(['courses' => $courses]);
