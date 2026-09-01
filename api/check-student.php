<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../includes/Database.php';

$student_id = '';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $student_id = trim($_GET['student_id'] ?? '');
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = trim($input['student_id'] ?? '');
}

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// ── STEP 1: Check if student exists ──────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT student_id, full_name, department, year_intake, semester, photo_path, registered_at
     FROM students WHERE student_id = ? LIMIT 1"
);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode([
        'success' => false,
        'registered' => false,
        'message' => "❌ Student ID '{$student_id}' is not registered in the system."
    ]);
    exit();
}

// ── STEP 2: Get all face descriptors for this student ────────────────────────
$faceStmt = $conn->prepare(
    "SELECT id, face_descriptor, photo_path, created_at
     FROM student_faces 
     WHERE student_id = ? 
     ORDER BY created_at"
);
$faceStmt->execute([$student_id]);
$faces = $faceStmt->fetchAll(PDO::FETCH_ASSOC);

$faceInfo = [];
$validFaces = 0;

foreach ($faces as $face) {
    $descriptor = json_decode($face['face_descriptor'], true);
    $isValid = $descriptor && is_array($descriptor) && count($descriptor) === 128;
    
    if ($isValid) $validFaces++;
    
    $faceInfo[] = [
        'id' => $face['id'],
        'photo_url' => $face['photo_path'] ? "http://localhost/face-attendance-api/uploads/" . $face['photo_path'] : null,
        'registered_at' => $face['created_at'],
        'descriptor_valid' => $isValid,
        'descriptor_length' => $descriptor ? count($descriptor) : 0
    ];
}

// ── STEP 3: Check recent attendance ───────────────────────────────────────────
$attendanceStmt = $conn->prepare(
    "SELECT date, time, course_name, confidence
     FROM attendance 
     WHERE student_id = ? 
     ORDER BY date DESC, time DESC 
     LIMIT 5"
);
$attendanceStmt->execute([$student_id]);
$recentAttendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

// ── STEP 4: Calculate registration quality ────────────────────────────────────
$quality = 'POOR';
$recommendations = [];

if ($validFaces === 0) {
    $quality = 'NO_FACES';
    $recommendations[] = 'Register at least one face photo';
} elseif ($validFaces === 1) {
    $quality = 'BASIC';
    $recommendations[] = 'Add 2-3 more face photos from different angles for better accuracy';
} elseif ($validFaces <= 3) {
    $quality = 'GOOD';
    $recommendations[] = 'Consider adding 1-2 more face photos for optimal accuracy';
} else {
    $quality = 'EXCELLENT';
}

if (empty($recentAttendance)) {
    $recommendations[] = 'No recent attendance records found';
}

echo json_encode([
    'success' => true,
    'registered' => true,
    'student' => [
        'id' => $student['student_id'],
        'name' => $student['full_name'],
        'department' => $student['department'],
        'year_intake' => $student['year_intake'],
        'semester' => $student['semester'],
        'photo_url' => $student['photo_path'] ? "http://localhost/face-attendance-api/uploads/" . $student['photo_path'] : null,
        'registered_at' => $student['registered_at']
    ],
    'face_registration' => [
        'total_faces' => count($faces),
        'valid_faces' => $validFaces,
        'quality' => $quality,
        'faces' => $faceInfo,
        'recommendations' => $recommendations
    ],
    'recent_attendance' => $recentAttendance,
    'system_status' => [
        'can_recognize' => $validFaces > 0,
        'recognition_accuracy' => $validFaces >= 3 ? 'HIGH' : ($validFaces >= 2 ? 'MEDIUM' : 'LOW'),
        'last_check' => date('Y-m-d H:i:s')
    ]
]);