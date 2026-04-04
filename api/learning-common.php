<?php

function ms_learning_storage_dir() {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
}

function ms_learning_assignments_path() {
    return ms_learning_storage_dir() . DIRECTORY_SEPARATOR . 'teacher_manifest_assignments.json';
}

function ms_learning_ensure_storage() {
    $dir = ms_learning_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function ms_learning_course_meta($key) {
    $labels = [
        'foundations_math' => ['title' => 'Foundations Math', 'subject' => 'Mathematics', 'description' => 'Built lessons from the live Foundations math sequence.'],
        'pre_algebra' => ['title' => 'Pre-Algebra', 'subject' => 'Mathematics', 'description' => 'Built lessons from the pre-algebra path.'],
        'foundations_science' => ['title' => 'Foundations Science', 'subject' => 'Science', 'description' => 'Built lessons from the live Foundations science sequence.'],
        'foundations_english' => ['title' => 'Foundations English', 'subject' => 'English', 'description' => 'Built lessons from the live Foundations English sequence.'],
    ];
    return $labels[$key] ?? ['title' => 'MindStrong Course', 'subject' => 'General', 'description' => 'Built curriculum lessons.'];
}

function ms_learning_title_from_path($path) {
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

function ms_learning_make_lessons($subject, $paths) {
    $items = [];
    foreach ($paths as $index => $path) {
        $items[] = [
            'lesson_id' => 0,
            'lesson_key' => $path,
            'title' => ms_learning_title_from_path($path),
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

function ms_learning_catalog() {
    return [
        array_merge(ms_learning_course_meta('foundations_math'), [
            'course_id' => 0,
            'course_key' => 'foundations_math',
            'slug' => 'foundations_math',
            'status' => 'active',
            'student_count' => 0,
            'lessons' => ms_learning_make_lessons('Mathematics', [
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
        ]),
        array_merge(ms_learning_course_meta('pre_algebra'), [
            'course_id' => 0,
            'course_key' => 'pre_algebra',
            'slug' => 'pre_algebra',
            'status' => 'active',
            'student_count' => 0,
            'lessons' => ms_learning_make_lessons('Mathematics', [
                '/school/foundations/pre-algebra/index.html',
                '/school/foundations/pre-algebra/equations/index.html',
                '/school/foundations/pre-algebra/expressions/index.html',
                '/school/foundations/pre-algebra/percents/index.html',
                '/school/foundations/pre-algebra/proportions/index.html',
            ]),
        ]),
        array_merge(ms_learning_course_meta('foundations_science'), [
            'course_id' => 0,
            'course_key' => 'foundations_science',
            'slug' => 'foundations_science',
            'status' => 'active',
            'student_count' => 0,
            'lessons' => ms_learning_make_lessons('Science', [
                '/school/foundations-science/lesson-1.html',
                '/school/foundations-science/lesson-2.html',
                '/school/foundations-science/lesson-3.html',
                '/school/foundations-science/lesson-4.html',
                '/school/foundations-science/lesson-5.html',
                '/school/foundations-science/lesson-6.html',
            ]),
        ]),
        array_merge(ms_learning_course_meta('foundations_english'), [
            'course_id' => 0,
            'course_key' => 'foundations_english',
            'slug' => 'foundations_english',
            'status' => 'active',
            'student_count' => 0,
            'lessons' => ms_learning_make_lessons('English', [
                '/school/foundations-english/lesson-1.html',
                '/school/foundations-english/lesson-2.html',
                '/school/foundations-english/lesson-3.html',
                '/school/foundations-english/lesson-4.html',
                '/school/foundations-english/lesson-5.html',
                '/school/foundations-english/lesson-6.html',
            ]),
        ]),
    ];
}

function ms_learning_read_assignments($file = null) {
    $path = $file ?: ms_learning_assignments_path();
    if (!file_exists($path)) {
        return ['assignments' => []];
    }
    $raw = file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : ['assignments' => []];
}

function ms_learning_write_assignments($payload, $file = null) {
    ms_learning_ensure_storage();
    $path = $file ?: ms_learning_assignments_path();
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
}

function ms_learning_bundle_for_student($studentId) {
    $studentId = (int)$studentId;
    if ($studentId < 1) {
        return [];
    }

    $catalog = ms_learning_catalog();
    $catalogByKey = [];
    foreach ($catalog as $course) {
        $catalogByKey[$course['course_key']] = $course;
    }

    $store = ms_learning_read_assignments();
    $assignments = array_values(array_filter($store['assignments'] ?? [], function ($assignment) use ($studentId) {
        return (int)($assignment['student_id'] ?? 0) === $studentId;
    }));

    $bundles = [];
    foreach ($assignments as $assignment) {
        $courseKey = trim((string)($assignment['course_key'] ?? ''));
        if ($courseKey === '' || !isset($catalogByKey[$courseKey])) {
            continue;
        }

        if (!isset($bundles[$courseKey])) {
            $course = $catalogByKey[$courseKey];
            $bundles[$courseKey] = [
                'course_key' => $courseKey,
                'course_id' => 0,
                'title' => $assignment['course_title'] ?: $course['title'],
                'slug' => $course['slug'],
                'description' => $course['description'],
                'subject_area' => $course['subject'] ?? $course['subject_area'],
                'teacher_name' => $assignment['teacher_name'] ?: 'MindStrong',
                'class_name' => !empty($assignment['class_id']) ? 'Class ' . (int)$assignment['class_id'] : '',
                'progress_percent' => 0,
                'lessons' => [],
            ];
        }

        $lessonMap = [];
        foreach ($bundles[$courseKey]['lessons'] as $lesson) {
            $lessonMap[$lesson['lesson_url']] = $lesson;
        }

        $assignedAllCourseLessons = ($assignment['assignment_type'] ?? '') === 'course' || empty($assignment['lesson_url']);
        $sourceLessons = $assignedAllCourseLessons ? $catalogByKey[$courseKey]['lessons'] : array_values(array_filter(
            $catalogByKey[$courseKey]['lessons'],
            function ($lesson) use ($assignment) {
                return ($lesson['lesson_url'] ?? '') === ($assignment['lesson_url'] ?? '');
            }
        ));

        if (!$sourceLessons && !empty($assignment['lesson_url'])) {
            $sourceLessons[] = [
                'lesson_id' => 0,
                'lesson_key' => $assignment['lesson_url'],
                'title' => $assignment['lesson_title'] ?: ms_learning_title_from_path($assignment['lesson_url']),
                'slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($assignment['lesson_url'])), '-'),
                'lesson_type' => 'lesson',
                'subject_area' => $bundles[$courseKey]['subject_area'],
                'description' => '',
                'lesson_url' => $assignment['lesson_url'],
                'game_url' => '',
                'estimated_minutes' => 20,
                'status' => 'active',
                'module_title' => '',
                'position' => 999,
                'is_required' => true,
            ];
        }

        foreach ($sourceLessons as $lesson) {
            $lessonUrl = $lesson['lesson_url'] ?? '';
            if ($lessonUrl === '') {
                continue;
            }
            if (!isset($lessonMap[$lessonUrl])) {
                $lesson['progress_status'] = 'assigned';
                $lessonMap[$lessonUrl] = $lesson;
            }
        }

        $lessons = array_values($lessonMap);
        usort($lessons, function ($a, $b) {
            return (int)($a['position'] ?? 0) <=> (int)($b['position'] ?? 0);
        });
        $bundles[$courseKey]['lessons'] = $lessons;
    }

    return array_values($bundles);
}
