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

$session_id  = intval($input['session_id']  ?? 0);
$lecturer_id = trim($input['lecturer_id']   ?? '');

if (!$session_id || !$lecturer_id) {
    echo json_encode(['success' => false, 'message' => 'session_id and lecturer_id are required']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// Verify session belongs to this lecturer and is still active
$checkStmt = $conn->prepare(
    "SELECT id, department, semester, course_name, marked_students 
     FROM attendance_sessions 
     WHERE id = ? AND lecturer_id = ? AND is_active = 1 
     LIMIT 1"
);
$checkStmt->execute([$session_id, $lecturer_id]);
$session = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode(['success' => false, 'message' => '❌ Session not found or already stopped.']);
    exit();
}

// Stop the session
$now = date('Y-m-d H:i:s');
$stopStmt = $conn->prepare(
    "UPDATE attendance_sessions SET is_active = 0, ended_at = ? WHERE id = ?"
);
$stopStmt->execute([$now, $session_id]);

echo json_encode([
    'success' => true,
    'message' => "✅ Session stopped for {$session['department']} Term {$session['semester']}. {$session['marked_students']} student(s) marked present.",
    'ended_at' => $now
]);
?>
