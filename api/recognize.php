<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../includes/Database.php';
require_once '../includes/AmazonRekognition.php';

// Amazon Rekognition confidence thresholds for school attendance
define('MIN_CONFIDENCE_THRESHOLD', 80);  // Minimum 80% confidence for attendance
define('HIGH_CONFIDENCE_THRESHOLD', 90); // 90%+ is excellent recognition

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['image'])) {
    echo json_encode(['success' => false, 'message' => 'No image received for face recognition.']);
    exit();
}

$image_base64 = $input['image'];

// Strip data URI prefix if present
if (strpos($image_base64, 'base64,') !== false) {
    $image_base64 = explode('base64,', $image_base64)[1];
}

$db   = new Database();
$conn = $db->getConnection();

// ── STEP 1: Search face using Amazon Rekognition ─────────────────────────────
$rekognition = new AmazonRekognition();
$searchResult = $rekognition->searchFace($image_base64);

if (!$searchResult['success']) {
    echo json_encode([
        'success' => false,
        'message' => 'Face recognition failed: ' . $searchResult['error']
    ]);
    exit();
}

if (!$searchResult['matched']) {
    echo json_encode([
        'success'        => false,
        'not_registered' => true,
        'message'        => '⚠️ Your face is not registered in this system. Please contact your lecturer or admin to register.',
        'provider'       => 'Amazon Rekognition'
    ]);
    exit();
}

$face_id = $searchResult['face_id'];
$confidence = $searchResult['confidence'];
$external_image_id = $searchResult['external_image_id'];

// Check if confidence meets our threshold
if ($confidence < MIN_CONFIDENCE_THRESHOLD) {
    echo json_encode([
        'success'        => false,
        'not_registered' => true,
        'message'        => "⚠️ Face recognition confidence too low ({$confidence}%). Please try again with better lighting and face the camera directly.",
        'confidence'     => round($confidence, 2),
        'min_required'   => MIN_CONFIDENCE_THRESHOLD,
        'provider'       => 'Amazon Rekognition'
    ]);
    exit();
}

// ── STEP 2: Find student by face_id or external_image_id ─────────────────────
$student = null;

// First try to find by face_id in students table
if ($face_id) {
    $stmt = $conn->prepare(
        "SELECT student_id, full_name, department, year_intake, semester, photo_path
         FROM students WHERE face_id = ? LIMIT 1"
    );
    $stmt->execute([$face_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If not found and we have external_image_id, try that (it should be student_id)
if (!$student && $external_image_id) {
    $stmt = $conn->prepare(
        "SELECT student_id, full_name, department, year_intake, semester, photo_path
         FROM students WHERE student_id = ? LIMIT 1"
    );
    $stmt->execute([$external_image_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$student) {
    echo json_encode([
        'success'        => false,
        'not_registered' => true,
        'message'        => '⚠️ Face matched but student record not found. Please contact admin.',
        'face_id'        => $face_id,
        'external_id'    => $external_image_id
    ]);
    exit();
}

// ── STEP 3: Check active session ─────────────────────────────────────────────
$sessionStmt = $conn->prepare(
    "SELECT id, course_name, course_code, expected_end_time
     FROM attendance_sessions
     WHERE department = ? AND semester = ? AND is_active = 1
     LIMIT 1"
);
$sessionStmt->execute([$student['department'], $student['semester']]);
$activeSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

if (!$activeSession) {
    echo json_encode([
        'success' => false,
        'message' => "⏳ No active session for {$student['department']} Term {$student['semester']}. Please wait for your lecturer to start the class."
    ]);
    exit();
}

$active_session_id = $activeSession['id'];
$today             = date('Y-m-d');

// ── STEP 4: Count today's classes for ordinal label ───────────────────────────
$countStmt = $conn->prepare(
    "SELECT COUNT(*) as class_count FROM attendance WHERE student_id = ? AND date = ?"
);
$countStmt->execute([$student['student_id'], $today]);
$classCount  = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['class_count'];
$ordinals    = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
$nextOrdinal = $ordinals[$classCount] ?? ($classCount + 1) . 'th';

$photo_url = $student['photo_path']
    ? "http://localhost/face-attendance-api/uploads/" . $student['photo_path']
    : null;

$student_payload = [
    'id'          => $student['student_id'],
    'name'        => $student['full_name'],
    'department'  => $student['department'],
    'year_intake' => $student['year_intake'],
    'semester'    => $student['semester'],
    'photo_url'   => $photo_url,
    'class_count' => $classCount + 1,
    'ordinal'     => $nextOrdinal,
];

// ── STEP 5: Check already marked ─────────────────────────────────────────────
$checkStmt = $conn->prepare(
    "SELECT id FROM attendance WHERE student_id = ? AND session_id = ? LIMIT 1"
);
$checkStmt->execute([$student['student_id'], $active_session_id]);
if ($checkStmt->rowCount() > 0) {
    echo json_encode([
        'success'        => true,
        'already_marked' => true,
        'message'        => "👋 Already marked for this class, {$student['full_name']}!",
        'student'        => $student_payload,
        'confidence'     => round($confidence, 2),
        'recognition_quality' => $confidence >= HIGH_CONFIDENCE_THRESHOLD ? 'EXCELLENT' : 'GOOD',
        'provider'       => 'Amazon Rekognition'
    ]);
    exit();
}

// ── STEP 6: Mark attendance ───────────────────────────────────────────────────
try {
    $attStmt = $conn->prepare(
        "INSERT INTO attendance (student_id, session_id, department, semester, course_code, course_name, date, time, confidence)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $attStmt->execute([
        $student['student_id'],
        $active_session_id,
        $student['department'],
        $student['semester'],
        $activeSession['course_code'],
        $activeSession['course_name'],
        $today,
        date('H:i:s'),
        round($confidence)
    ]);

    $conn->prepare(
        "UPDATE attendance_sessions SET marked_students = marked_students + 1 WHERE id = ?"
    )->execute([$active_session_id]);

} catch (PDOException $e) {
    error_log('[recognize.php] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to record attendance. Please try again.']);
    exit();
}

echo json_encode([
    'success'           => true,
    'already_marked'    => false,
    'attendance_marked' => true,
    'message'           => "✅ {$nextOrdinal} class today — Welcome, {$student['full_name']}! Attendance marked.",
    'student'           => $student_payload,
    'confidence'        => round($confidence, 2),
    'recognition_quality' => $confidence >= HIGH_CONFIDENCE_THRESHOLD ? 'EXCELLENT' : 'GOOD',
    'timestamp'         => date('Y-m-d H:i:s'),
    'provider'          => 'Amazon Rekognition',
    'match_info' => [
        'face_id' => $face_id,
        'external_image_id' => $external_image_id,
        'aws_confidence' => round($confidence, 2)
    ]
]);