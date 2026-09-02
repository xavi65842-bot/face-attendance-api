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

$lecturer_id = trim($_GET['lecturer_id'] ?? '');

if (!$lecturer_id) {
    echo json_encode(['success' => false, 'message' => 'lecturer_id is required', 'sessions' => []]);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

try {
    // Get all sessions conducted by this lecturer (ordered from newest to oldest)
    $stmt = $conn->prepare(
        "SELECT id, session_token, course_code, course_name, department, semester,
                started_at, ended_at, expected_end_time, is_active, marked_students
         FROM attendance_sessions
         WHERE UPPER(lecturer_id) = UPPER(?) OR UPPER(lecturer_name) = UPPER(?)
         ORDER BY started_at DESC
         LIMIT 50"
    );
    $stmt->execute([$lecturer_id, $lecturer_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $now = time();
    $twentyFourHoursAgo = $now - (24 * 3600);

    foreach ($sessions as $s) {
        $sessId = (int)$s['id'];
        $sessionTime = strtotime($s['started_at']);
        $isLast24Hours = $sessionTime >= $twentyFourHoursAgo;

        // Get attendees for this session
        $attStmt = $conn->prepare(
            "SELECT a.id, a.student_id, a.time, a.confidence, a.date,
                    COALESCE(s.full_name, a.student_id) as full_name,
                    COALESCE(s.department, a.department) as department,
                    s.year_intake,
                    COALESCE(s.semester, a.semester) as semester,
                    s.photo_path
             FROM attendance a
             LEFT JOIN students s ON a.student_id = s.student_id
             WHERE a.session_id = ?
             ORDER BY a.time ASC"
        );
        $attStmt->execute([$sessId]);
        $attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        $roster = array_map(function($att) {
            return [
                'student_id'  => $att['student_id'],
                'full_name'   => $att['full_name'],
                'department'  => $att['department'],
                'year_intake' => $att['year_intake'] ?? '—',
                'semester'    => (int)$att['semester'],
                'time'        => date('h:i A', strtotime($att['time'])),
                'date'        => $att['date'],
                'confidence'  => (int)$att['confidence'],
                'photo_path'  => $att['photo_path']
            ];
        }, $attendees);

        $results[] = [
            'id'             => $sessId,
            'course_code'    => $s['course_code'],
            'course_name'    => $s['course_name'],
            'department'     => $s['department'],
            'semester'       => (int)$s['semester'],
            'started_at'     => $s['started_at'],
            'ended_at'       => $s['ended_at'],
            'date'           => date('M d, Y', $sessionTime),
            'time'           => date('h:i A', $sessionTime),
            'is_active'      => (bool)$s['is_active'],
            'is_24h'         => $isLast24Hours,
            'marked_count'   => count($roster),
            'attendees'      => $roster
        ];
    }

    echo json_encode([
        'success'  => true,
        'sessions' => $results,
        'count'    => count($results)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'sessions' => []]);
}
?>