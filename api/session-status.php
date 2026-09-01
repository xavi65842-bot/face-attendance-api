<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/Database.php';

$department = trim($_GET['department'] ?? '');
$semester   = intval($_GET['semester']  ?? 0);

if (!$department || !$semester) {
    echo json_encode(['success' => false, 'message' => 'department and semester are required']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// Get active session for this dept + semester (only lecturer can stop sessions)
$stmt = $conn->prepare(
    "SELECT id, session_token, lecturer_name, course_code, course_name, 
            started_at, expected_end_time, marked_students, total_students
     FROM attendance_sessions
     WHERE department = ? AND semester = ? AND is_active = 1
     LIMIT 1"
);
$stmt->execute([$department, $semester]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode([
        'success' => true,
        'active'  => false,
        'message' => "No active session for {$department} Term {$semester}."
    ]);
    exit();
}

echo json_encode([
    'success'  => true,
    'active'   => true,
    'session'  => [
        'id'                => $session['id'],
        'session_token'     => $session['session_token'],
        'lecturer_name'     => $session['lecturer_name'],
        'course_code'       => $session['course_code'],
        'course_name'       => $session['course_name'],
        'started_at'        => $session['started_at'],
        'expected_end_time' => $session['expected_end_time'],
        'marked_students'   => $session['marked_students'],
        'total_students'    => $session['total_students']
    ]
]);
?>
