<?php
// Used internally by recognize.php and optionally by the frontend
// Returns whether attendance is currently open for a given dept + semester
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
    echo json_encode(['open' => false, 'message' => 'department and semester are required']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// Only lecturer can stop sessions — no auto-expire here
$stmt = $conn->prepare(
    "SELECT id, course_name, expected_end_time 
     FROM attendance_sessions
     WHERE department = ? AND semester = ? AND is_active = 1
     LIMIT 1"
);
$stmt->execute([$department, $semester]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode([
        'open'    => false,
        'message' => "Attendance is not open for {$department} Term {$semester}. Waiting for lecturer to start a session."
    ]);
    exit();
}

echo json_encode([
    'open'       => true,
    'session_id' => $session['id'],
    'course'     => $session['course_name'],
    'ends_at'    => $session['expected_end_time']
]);
?>
