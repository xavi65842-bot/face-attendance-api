<?php
// Dashboard API - Get all students with attendance stats
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$department  = isset($_GET['department'])  && trim($_GET['department'])  !== 'all' ? trim($_GET['department'])  : null;
$semester    = isset($_GET['semester'])    && trim($_GET['semester'])    !== 'all' ? intval($_GET['semester'])  : null;
$year_intake = isset($_GET['year_intake']) && trim($_GET['year_intake']) !== 'all' ? trim($_GET['year_intake']) : null;

// Build query for students
$sql = "SELECT student_id, full_name, department, year_intake, semester, registered_at FROM students WHERE 1=1";
$params = [];

if ($department) {
    $sql .= " AND department = ?";
    $params[] = $department;
}
if ($semester) {
    $sql .= " AND semester = ?";
    $params[] = $semester;
}
if ($year_intake) {
    $sql .= " AND year_intake = ?";
    $params[] = $year_intake;
}

$sql .= " ORDER BY department, semester, full_name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total days in semester (example: assume 90 days per semester)
$total_days = 90;

// Fetch all attendance counts in ONE query instead of per-student queries
$attMap = [];
$attRows = $conn->query("SELECT student_id, COUNT(DISTINCT date) as present FROM attendance GROUP BY student_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($attRows as $row) {
    $attMap[$row['student_id']] = (int)$row['present'];
}

// Fetch all photo paths in ONE query
$photoMap = [];
$photoRows = $conn->query("SELECT student_id, photo_path FROM students")->fetchAll(PDO::FETCH_ASSOC);
foreach ($photoRows as $row) {
    $photoMap[$row['student_id']] = $row['photo_path'];
}

// Merge into students array
foreach ($students as &$student) {
    $present = $attMap[$student['student_id']] ?? 0;
    $student['attendance_percentage'] = round(($present / $total_days) * 100, 1);
    $student['present_days']  = $present;
    $student['total_days']    = $total_days;
    $photoPath = $photoMap[$student['student_id']] ?? null;
    $student['photo_url'] = $photoPath
        ? "http://localhost/face-attendance-api/uploads/" . $photoPath
        : null;
}
unset($student); // break reference to avoid accidental mutation

// Get statistics for dashboard
$deptStats = $conn->query("SELECT department, COUNT(*) as count FROM students GROUP BY department")->fetchAll(PDO::FETCH_ASSOC);
$semesterStats = $conn->query("SELECT semester, COUNT(*) as count FROM students GROUP BY semester ORDER BY semester")->fetchAll(PDO::FETCH_ASSOC);
$intakeStats = $conn->query("SELECT year_intake, COUNT(*) as count FROM students GROUP BY year_intake")->fetchAll(PDO::FETCH_ASSOC);

// Today's attendance
$today = date('Y-m-d');
$todayStmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE date = ?");
$todayStmt->execute([$today]);
$today_count = $todayStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total students (unfiltered) — used for accurate percentage calculations
$totalAllStudents = (int)$conn->query("SELECT COUNT(*) FROM students")->fetchColumn();

$totalStudents = count($students); // filtered count for display

echo json_encode([
    'success' => true,
    'data' => [
        'students' => $students,
        'statistics' => [
            'total_students' => $totalStudents,
            'today_present' => $today_count,
            'today_percentage' => $totalAllStudents > 0 ? round(($today_count / $totalAllStudents) * 100, 1) : 0,
            'by_department' => $deptStats,
            'by_semester' => $semesterStats,
            'by_intake' => $intakeStats
        ],
        'filters' => [
            'departments' => array_column($deptStats, 'department'),
            'semesters' => [1,2,3],
            'intakes' => array_column($intakeStats, 'year_intake')
        ]
    ]
]);
?>