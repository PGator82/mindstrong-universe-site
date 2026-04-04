<?php
header('Content-Type: application/json; charset=UTF-8');

$storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
$storageFile = $storageDir . DIRECTORY_SEPARATOR . 'teacher_manifest_assignments.json';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

function read_manifest_assignments($file) {
    if (!file_exists($file)) {
        return ['assignments' => []];
    }
    $raw = file_get_contents($file);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : ['assignments' => []];
}

function write_manifest_assignments($file, $payload) {
    $encoded = json_encode($payload, JSON_PRETTY_PRINT);
    file_put_contents($file, $encoded, LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(read_manifest_assignments($storageFile));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$studentId = (int)($body['student_id'] ?? 0);
$studentName = trim((string)($body['student_name'] ?? ''));
$courseKey = trim((string)($body['course_key'] ?? ''));
$courseTitle = trim((string)($body['course_title'] ?? ''));
$lessonUrl = trim((string)($body['lesson_url'] ?? ''));
$lessonTitle = trim((string)($body['lesson_title'] ?? ''));
$assignmentType = trim((string)($body['assignment_type'] ?? 'lesson'));
$teacherName = trim((string)($body['teacher_name'] ?? 'Teacher'));
$classId = (int)($body['class_id'] ?? 0);

if ($studentId < 1 || $courseKey === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Student and course are required']);
    exit;
}

$store = read_manifest_assignments($storageFile);
$assignments = $store['assignments'] ?? [];
$key = implode(':', [$studentId, $courseKey, $lessonUrl ?: 'course']);
$now = gmdate('c');
$saved = false;

foreach ($assignments as &$assignment) {
    if (($assignment['assignment_key'] ?? '') !== $key) {
        continue;
    }
    $assignment['student_name'] = $studentName ?: ($assignment['student_name'] ?? '');
    $assignment['course_title'] = $courseTitle ?: ($assignment['course_title'] ?? '');
    $assignment['lesson_title'] = $lessonTitle ?: ($assignment['lesson_title'] ?? '');
    $assignment['teacher_name'] = $teacherName ?: ($assignment['teacher_name'] ?? 'Teacher');
    $assignment['class_id'] = $classId ?: ($assignment['class_id'] ?? 0);
    $assignment['updated_at'] = $now;
    $saved = true;
    break;
}
unset($assignment);

if (!$saved) {
    $assignments[] = [
        'assignment_key' => $key,
        'assignment_type' => $assignmentType,
        'student_id' => $studentId,
        'student_name' => $studentName,
        'course_key' => $courseKey,
        'course_title' => $courseTitle,
        'lesson_url' => $lessonUrl,
        'lesson_title' => $lessonTitle,
        'teacher_name' => $teacherName,
        'class_id' => $classId,
        'status' => 'assigned',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

$store['assignments'] = array_values($assignments);
write_manifest_assignments($storageFile, $store);

echo json_encode([
    'success' => true,
    'assignment_key' => $key,
    'stored' => count($store['assignments']),
]);
