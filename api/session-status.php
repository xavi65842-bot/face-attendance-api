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
$session_id = intval($_GET['session_id'] ?? 0);

$db   = new Database();
$conn = $db->getConnection();

$session = null;

if ($session_id > 0) {
    $stmt = $conn->prepare(
        "SELECT id, session_token, lecturer_id, lecturer_name, department, semester, course_code, course_name, 
                started_at, expected_end_time, is_active, marked_students, total_students
         FROM attendance_sessions
         WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
} else if ($department && $semester) {
    $stmt = $conn->prepare(
        "SELECT id, session_token, lecturer_id, lecturer_name, department, semester, course_code, course_name, 
                started_at, expected_end_time, is_active, marked_students, total_students
         FROM attendance_sessions
         WHERE department = ? AND semester = ? AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$department, $semester]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$session) {
    echo json_encode([
        'success' => true,
        'active'  => false,
        'message' => "No active session found.",
        'attendees' => []
    ]);
    exit();
}

// Fetch all students who marked attendance in this session
$attStmt = $conn->prepare(
    "SELECT a.id as attendance_id, a.student_id, a.time, a.confidence, a.date,
            COALESCE(s.full_name, a.student_id) as full_name,
            COALESCE(s.department, a.department) as department,
            s.year_intake,
            COALESCE(s.semester, a.semester) as semester,
            s.photo_path
     FROM attendance a
     LEFT JOIN students s ON a.student_id = s.student_id
     WHERE a.session_id = ?
     ORDER BY a.time DESC, a.id DESC"
);
$attStmt->execute([$session['id']]);
$attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);

$formattedAttendees = array_map(function($att) {
    return [
        'student_id'  => $att['student_id'],
        'full_name'   => $att['full_name'],
        'department'  => $att['department'],
        'year_intake' => $att['year_intake'] ?? '—',
        'semester'    => (int)$att['semester'],
        'time'        => date('h:i:s A', strtotime($att['time'])),
        'date'        => $att['date'],
        'confidence'  => (int)$att['confidence'],
        'photo_path'  => $att['photo_path']
    ];
}, $attendees);

echo json_encode([
    'success'  => true,
    'active'   => (bool)$session['is_active'],
    'session'  => [
        'id'                => (int)$session['id'],
        'session_token'     => $session['session_token'],
        'lecturer_id'       => $session['lecturer_id'],
        'lecturer_name'     => $session['lecturer_name'],
        'department'        => $session['department'],
        'semester'          => (int)$session['semester'],
        'course_code'       => $session['course_code'],
        'course_name'       => $session['course_name'],
        'started_at'        => $session['started_at'],
        'expected_end_time' => $session['expected_end_time'],
        'marked_students'   => count($formattedAttendees),
        'total_students'    => (int)$session['total_students']
    ],
    'attendees' => $formattedAttendees
]);
?>