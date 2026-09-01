<?php
// Check database contents
header('Content-Type: text/html');

echo "<h1>Database Check</h1>";

require_once __DIR__ . '/includes/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("❌ Database connection failed!");
}

echo "✅ Database connected!<br><br>";

// Get all students
$stmt = $conn->query("SELECT * FROM students");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Students in Database (" . count($students) . ")</h2>";

if (count($students) > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Student ID</th><th>Name</th><th>Department</th><th>Face Token (first 20 chars)</th></tr>";
    foreach ($students as $student) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($student['department']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($student['face_token'] ?? 'none', 0, 20)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No students found! Please register a student first.<br>";
}

// Get attendance records
$stmt = $conn->query("SELECT * FROM attendance ORDER BY date DESC");
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Attendance Records (" . count($attendance) . ")</h2>";
if (count($attendance) > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Student ID</th><th>Date</th><th>Time</th><th>Confidence</th></tr>";
    foreach ($attendance as $record) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($record['student_id']) . "</td>";
        echo "<td>" . $record['date'] . "</td>";
        echo "<td>" . $record['time'] . "</td>";
        echo "<td>" . $record['confidence'] . "%</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No attendance records yet.<br>";
}
?>