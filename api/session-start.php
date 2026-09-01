<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/Database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

$lecturer_id   = trim($input['lecturer_id']   ?? '');
$lecturer_name = trim($input['lecturer_name'] ?? '');
$department    = trim($input['department']    ?? '');
$semester      = intval($input['semester']    ?? 0);
$course_code   = trim($input['course_code']   ?? '');
$course_name   = trim($input['course_name']   ?? '');
$ends_at       = trim($input['ends_at']       ?? ''); // Expected format: "2026-04-04 15:00:00"

if (!$lecturer_id || !$lecturer_name || !$department || !$semester || !$course_code || !$course_name || !$ends_at) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate ends_at is a real datetime and is in the future
if (!strtotime($ends_at) || strtotime($ends_at) <= time()) {
    echo json_encode(['success' => false, 'message' => 'ends_at must be a valid future datetime (e.g. 2026-04-06 15:00:00)']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

$now = date('Y-m-d H:i:s');

// ── Check if there is already an active session for this dept + semester ─────
// Only is_active = 1 matters — sessions only stop when lecturer manually stops them
$checkStmt = $conn->prepare(
    "SELECT id, lecturer_name, course_name, expected_end_time 
     FROM attendance_sessions 
     WHERE department = ? AND semester = ? AND is_active = 1
     LIMIT 1"
);
$checkStmt->execute([$department, $semester]);
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $end_time_formatted = date('h:i A', strtotime($existing['expected_end_time']));
    echo json_encode([
        'success' => false,
        'message' => "⚠️ A session is already active for {$department} Term {$semester}. Lecturer {$existing['lecturer_name']} is teaching {$existing['course_name']} (scheduled until {$end_time_formatted}). The lecturer must stop the session before a new one can start.",
        'active_session' => [
            'lecturer_name'     => $existing['lecturer_name'],
            'course_name'       => $existing['course_name'],
            'expected_end_time' => $existing['expected_end_time']
        ]
    ]);
    exit();
}

// ── Generate unique session token ─────────────────────────────────────────────
$session_token = bin2hex(random_bytes(16));

// ── Create the session ────────────────────────────────────────────────────────
$insertStmt = $conn->prepare(
    "INSERT INTO attendance_sessions 
        (session_token, is_active, lecturer_id, lecturer_name, department, semester, course_code, course_name, started_at, expected_end_time)
     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$insertStmt->execute([
    $session_token,
    $lecturer_id,
    $lecturer_name,
    $department,
    $semester,
    $course_code,
    $course_name,
    $now,
    $ends_at
]);

$session_id = $conn->lastInsertId();

echo json_encode([
    'success'       => true,
    'message'       => "✅ Session started for {$department} Term {$semester}. Attendance is now open.",
    'session' => [
        'id'                => $session_id,
        'session_token'     => $session_token,
        'department'        => $department,
        'semester'          => $semester,
        'course_code'       => $course_code,
        'course_name'       => $course_name,
        'started_at'        => $now,
        'expected_end_time' => $ends_at
    ]
]);
?>
