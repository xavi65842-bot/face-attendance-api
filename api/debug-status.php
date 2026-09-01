<?php
// Quick diagnostic — open in browser to see students vs sessions
header("Content-Type: text/html");
require_once '../includes/Database.php';

$db   = new Database();
$conn = $db->getConnection();

echo "<pre style='font-size:14px;padding:20px;'>";
echo "=== REGISTERED STUDENTS ===\n";
$students = $conn->query("SELECT student_id, full_name, department, semester, face_token FROM students ORDER BY department, semester")->fetchAll(PDO::FETCH_ASSOC);
foreach ($students as $s) {
    $token = $s['face_token'] ? substr($s['face_token'], 0, 12) . '...' : 'NO TOKEN';
    echo "  [{$s['department']}] Sem {$s['semester']} | {$s['full_name']} ({$s['student_id']}) | token: {$token}\n";
}

echo "\n=== ACTIVE SESSIONS ===\n";
$sessions = $conn->query("SELECT id, department, semester, lecturer_name, course_name, is_active, started_at FROM attendance_sessions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
if (empty($sessions)) {
    echo "  NO ACTIVE SESSIONS\n";
} else {
    foreach ($sessions as $s) {
        echo "  [{$s['department']}] Sem {$s['semester']} | {$s['lecturer_name']} | {$s['course_name']} | active={$s['is_active']}\n";
    }
}

echo "\n=== MATCH CHECK ===\n";
echo "Checking if each student has a matching active session...\n";
foreach ($students as $s) {
    $stmt = $conn->prepare("SELECT id FROM attendance_sessions WHERE department = ? AND semester = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$s['department'], $s['semester']]);
    $match = $stmt->fetch();
    $status = $match ? "✅ HAS ACTIVE SESSION" : "❌ NO MATCHING SESSION";
    echo "  {$s['full_name']} [{$s['department']}] Sem {$s['semester']} → {$status}\n";
}

echo "\n=== ALL SESSIONS (including inactive) ===\n";
$all = $conn->query("SELECT id, department, semester, lecturer_name, is_active, started_at, ended_at FROM attendance_sessions ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $s) {
    echo "  ID:{$s['id']} [{$s['department']}] Sem {$s['semester']} | active={$s['is_active']} | started={$s['started_at']} | ended={$s['ended_at']}\n";
}
echo "</pre>";
?>
