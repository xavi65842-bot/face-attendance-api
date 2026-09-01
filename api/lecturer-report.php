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

$db   = new Database();
$conn = $db->getConnection();

$lecturer_id = trim($_GET['lecturer_id'] ?? '');
$view        = trim($_GET['view']        ?? 'overview'); // overview | session | student
$session_id  = intval($_GET['session_id'] ?? 0);
$student_id  = trim($_GET['student_id']  ?? '');
$course_code = trim($_GET['course_code'] ?? '');

if (!$lecturer_id) {
    echo json_encode(['success' => false, 'message' => 'lecturer_id is required']);
    exit();
}

try {

// ── VIEW: overview ────────────────────────────────────────────────────────────
// Returns: summary cards + all sessions list + per-student attendance % table
if ($view === 'overview') {

    // All sessions this lecturer has run
    $sessionSql = "SELECT id, course_code, course_name, department, semester,
                          started_at, ended_at, expected_end_time,
                          is_active, marked_students, total_students
                   FROM attendance_sessions
                   WHERE lecturer_id = ?";
    $params = [$lecturer_id];

    if ($course_code) {
        $sessionSql .= " AND course_code = ?";
        $params[] = $course_code;
    }

    $sessionSql .= " ORDER BY started_at DESC";
    $sessStmt = $conn->prepare($sessionSql);
    $sessStmt->execute($params);
    $sessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sessions)) {
        echo json_encode([
            'success'  => true,
            'view'     => 'overview',
            'summary'  => ['total_sessions' => 0, 'total_students' => 0, 'avg_attendance_pct' => 0],
            'sessions' => [],
            'students' => []
        ]);
        exit();
    }

    $sessionIds = array_column($sessions, 'id');

    // Enrich each session with attendance rate
    // Bulk-fetch enrolled counts per dept+semester to avoid N+1
    $deptSemEnrolled = [];
    $enrollRows = $conn->query(
        "SELECT department, semester, COUNT(*) as cnt FROM students GROUP BY department, semester"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($enrollRows as $er) {
        $deptSemEnrolled[$er['department'] . '|' . $er['semester']] = (int)$er['cnt'];
    }

    foreach ($sessions as &$s) {
        $enrolled = $deptSemEnrolled[$s['department'] . '|' . $s['semester']] ?? 0;
        $s['enrolled_students'] = $enrolled;
        $present = (int)$s['marked_students'];
        $s['attendance_pct'] = $enrolled > 0 ? round(($present / $enrolled) * 100, 1) : 0;
        $s['day_name'] = date('l', strtotime($s['started_at']));
        $s['date']     = date('Y-m-d', strtotime($s['started_at']));
        $s['time']     = date('h:i A', strtotime($s['started_at']));
    }
    unset($s);

    // Get all unique dept+semester combos this lecturer teaches
    $deptSemRows = $conn->prepare(
        "SELECT DISTINCT department, semester FROM attendance_sessions WHERE lecturer_id = ?"
    );
    $deptSemRows->execute([$lecturer_id]);
    $deptSems = $deptSemRows->fetchAll(PDO::FETCH_ASSOC);

    // Build per-student attendance table across all this lecturer's sessions
    // Bulk-fetch all attendance counts for this lecturer in ONE query
    $bulkAttStmt = $conn->prepare(
        "SELECT a.student_id, ses.department, ses.semester, COUNT(*) as attended
         FROM attendance a
         JOIN attendance_sessions ses ON ses.id = a.session_id
         WHERE ses.lecturer_id = ?
         GROUP BY a.student_id, ses.department, ses.semester"
    );
    $bulkAttStmt->execute([$lecturer_id]);
    $bulkAtt = [];
    foreach ($bulkAttStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bulkAtt[$row['student_id'] . '|' . $row['department'] . '|' . $row['semester']] = (int)$row['attended'];
    }

    // Bulk-fetch total sessions per dept+semester for this lecturer
    $totalSessMap = [];
    $totalSessStmt = $conn->prepare(
        "SELECT department, semester, COUNT(*) as cnt
         FROM attendance_sessions WHERE lecturer_id = ?
         GROUP BY department, semester"
    );
    $totalSessStmt->execute([$lecturer_id]);
    foreach ($totalSessStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totalSessMap[$row['department'] . '|' . $row['semester']] = (int)$row['cnt'];
    }

    $students = [];
    foreach ($deptSems as $ds) {
        $stuStmt = $conn->prepare(
            "SELECT student_id, full_name, photo_path FROM students
             WHERE department = ? AND semester = ? ORDER BY full_name"
        );
        $stuStmt->execute([$ds['department'], $ds['semester']]);
        $deptStudents = $stuStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSessions = $totalSessMap[$ds['department'] . '|' . $ds['semester']] ?? 0;

        foreach ($deptStudents as $stu) {
            $attended = $bulkAtt[$stu['student_id'] . '|' . $ds['department'] . '|' . $ds['semester']] ?? 0;
            $pct      = $totalSessions > 0 ? round(($attended / $totalSessions) * 100, 1) : 0;

            $students[] = [
                'student_id'        => $stu['student_id'],
                'full_name'         => $stu['full_name'],
                'department'        => $ds['department'],
                'semester'          => $ds['semester'],
                'photo_url'         => $stu['photo_path']
                    ? "http://localhost/face-attendance-api/uploads/" . $stu['photo_path']
                    : null,
                'sessions_held'     => $totalSessions,
                'sessions_attended' => $attended,
                'attendance_pct'    => $pct,
                'status'            => $pct >= 75 ? 'good' : ($pct >= 50 ? 'warning' : 'at_risk')
            ];
        }
    }

    // Summary cards
    $totalSessions  = count($sessions);
    $avgPct         = $totalSessions > 0
        ? round(array_sum(array_column($sessions, 'attendance_pct')) / $totalSessions, 1)
        : 0;
    $uniqueStudents = count($students);

    // Unique courses this lecturer teaches
    $courses = [];
    foreach ($sessions as $s) {
        $key = $s['course_code'];
        if (!isset($courses[$key])) {
            $courses[$key] = ['course_code' => $s['course_code'], 'course_name' => $s['course_name']];
        }
    }

    echo json_encode([
        'success'  => true,
        'view'     => 'overview',
        'summary'  => [
            'total_sessions'    => $totalSessions,
            'total_students'    => $uniqueStudents,
            'avg_attendance_pct' => $avgPct,
            'courses'           => array_values($courses)
        ],
        'sessions' => $sessions,
        'students' => $students
    ]);
    exit();
}

// ── VIEW: session ─────────────────────────────────────────────────────────────
// Returns: full present/absent list for one specific session
if ($view === 'session') {

    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'session_id is required for session view']);
        exit();
    }

    // Verify session belongs to this lecturer
    $sesStmt = $conn->prepare(
        "SELECT id, course_code, course_name, department, semester,
                started_at, ended_at, is_active, marked_students
         FROM attendance_sessions
         WHERE id = ? AND lecturer_id = ? LIMIT 1"
    );
    $sesStmt->execute([$session_id, $lecturer_id]);
    $session = $sesStmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session not found or does not belong to this lecturer']);
        exit();
    }

    // All students enrolled in this dept+semester
    $allStmt = $conn->prepare(
        "SELECT student_id, full_name, photo_path FROM students
         WHERE department = ? AND semester = ? ORDER BY full_name"
    );
    $allStmt->execute([$session['department'], $session['semester']]);
    $allStudents = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    // Who actually attended this session
    $attStmt = $conn->prepare(
        "SELECT student_id, time, confidence FROM attendance WHERE session_id = ?"
    );
    $attStmt->execute([$session_id]);
    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    $attMap  = [];
    foreach ($attRows as $r) {
        $attMap[$r['student_id']] = $r;
    }

    $roster = [];
    foreach ($allStudents as $stu) {
        $present = isset($attMap[$stu['student_id']]);
        $roster[] = [
            'student_id'  => $stu['student_id'],
            'full_name'   => $stu['full_name'],
            'photo_url'   => $stu['photo_path']
                ? "http://localhost/face-attendance-api/uploads/" . $stu['photo_path']
                : null,
            'status'      => $present ? 'present' : 'absent',
            'time_in'     => $present ? date('h:i A', strtotime($attMap[$stu['student_id']]['time'])) : null,
            'confidence'  => $present ? (int)$attMap[$stu['student_id']]['confidence'] : null,
        ];
    }

    $enrolled = count($allStudents);
    $present  = count($attMap);
    $absent   = $enrolled - $present;

    echo json_encode([
        'success' => true,
        'view'    => 'session',
        'session' => [
            'id'          => $session['id'],
            'course_code' => $session['course_code'],
            'course_name' => $session['course_name'],
            'department'  => $session['department'],
            'semester'    => $session['semester'],
            'date'        => date('Y-m-d', strtotime($session['started_at'])),
            'day_name'    => date('l', strtotime($session['started_at'])),
            'time'        => date('h:i A', strtotime($session['started_at'])),
            'is_active'   => (bool)$session['is_active'],
            'enrolled'    => $enrolled,
            'present'     => $present,
            'absent'      => $absent,
            'attendance_pct' => $enrolled > 0 ? round(($present / $enrolled) * 100, 1) : 0
        ],
        'roster'  => $roster
    ]);
    exit();
}

// ── VIEW: student ─────────────────────────────────────────────────────────────
// Returns: one student's full attendance history across all this lecturer's sessions
if ($view === 'student') {

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'student_id is required for student view']);
        exit();
    }

    // Get student info
    $stuStmt = $conn->prepare(
        "SELECT student_id, full_name, department, semester, year_intake, photo_path
         FROM students WHERE student_id = ? LIMIT 1"
    );
    $stuStmt->execute([$student_id]);
    $student = $stuStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // All sessions this lecturer ran for this student's dept+semester
    $allSessSql = "SELECT id, course_code, course_name, started_at, is_active
                   FROM attendance_sessions
                   WHERE lecturer_id = ? AND department = ? AND semester = ?";
    $allSessParams = [$lecturer_id, $student['department'], $student['semester']];

    if ($course_code) {
        $allSessSql .= " AND course_code = ?";
        $allSessParams[] = $course_code;
    }

    $allSessSql .= " ORDER BY started_at DESC";
    $allSessStmt = $conn->prepare($allSessSql);
    $allSessStmt->execute($allSessParams);
    $allSessions = $allSessStmt->fetchAll(PDO::FETCH_ASSOC);

    // Which sessions did this student attend?
    $attStmt = $conn->prepare(
        "SELECT a.session_id, a.time, a.confidence
         FROM attendance a
         JOIN attendance_sessions ses ON ses.id = a.session_id
         WHERE a.student_id = ? AND ses.lecturer_id = ?
           AND ses.department = ? AND ses.semester = ?"
    );
    $attStmt->execute([
        $student_id, $lecturer_id,
        $student['department'], $student['semester']
    ]);
    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    $attMap  = [];
    foreach ($attRows as $r) {
        $attMap[$r['session_id']] = $r;
    }

    $history = [];
    foreach ($allSessions as $ses) {
        $attended = isset($attMap[$ses['id']]);
        $history[] = [
            'session_id'  => $ses['id'],
            'course_code' => $ses['course_code'],
            'course_name' => $ses['course_name'],
            'date'        => date('Y-m-d', strtotime($ses['started_at'])),
            'day_name'    => date('l', strtotime($ses['started_at'])),
            'time'        => date('h:i A', strtotime($ses['started_at'])),
            'status'      => $attended ? 'present' : 'absent',
            'time_in'     => $attended ? date('h:i A', strtotime($attMap[$ses['id']]['time'])) : null,
            'confidence'  => $attended ? (int)$attMap[$ses['id']]['confidence'] : null,
        ];
    }

    $totalSessions = count($allSessions);
    $attended      = count($attMap);
    $pct           = $totalSessions > 0 ? round(($attended / $totalSessions) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'view'    => 'student',
        'student' => [
            'student_id'   => $student['student_id'],
            'full_name'    => $student['full_name'],
            'department'   => $student['department'],
            'semester'     => $student['semester'],
            'year_intake'  => $student['year_intake'],
            'photo_url'    => $student['photo_path']
                ? "http://localhost/face-attendance-api/uploads/" . $student['photo_path']
                : null,
            'sessions_held'     => $totalSessions,
            'sessions_attended' => $attended,
            'sessions_absent'   => $totalSessions - $attended,
            'attendance_pct'    => $pct,
            'status'            => $pct >= 75 ? 'good' : ($pct >= 50 ? 'warning' : 'at_risk')
        ],
        'history' => $history
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid view. Use: overview, session, or student']);
} catch (PDOException $e) {
    error_log('[lecturer-report.php] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>
