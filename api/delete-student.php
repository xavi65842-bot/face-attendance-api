<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../includes/Database.php';
require_once '../includes/AmazonRekognition.php';

$input      = json_decode(file_get_contents('php://input'), true);
$student_id = trim($input['student_id'] ?? $_GET['student_id'] ?? '');

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'student_id is required']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// Get student info including face_id before deleting
$stmt = $conn->prepare("SELECT photo_path, face_id FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit();
}

// ── STEP 1: Delete face from AWS Rekognition ─────────────────────────────────
if (!empty($student['face_id'])) {
    $rekognition = new AmazonRekognition();
    $deleteResult = $rekognition->deleteFace($student['face_id']);
    
    if ($deleteResult['success']) {
        error_log("[delete-student.php] Deleted AWS face {$student['face_id']} for student {$student_id}");
    } else {
        error_log("[delete-student.php] Failed to delete AWS face {$student['face_id']}: " . ($deleteResult['error'] ?? 'unknown'));
        // Continue anyway — we still want to delete from DB even if AWS delete fails
    }
}

// ── STEP 2: Delete photos from disk ──────────────────────────────────────────
if (!empty($student['photo_path'])) {
    $file = __DIR__ . '/../uploads/' . $student['photo_path'];
    if (file_exists($file)) unlink($file);
}

// Delete any extra photos from student_faces
$sfStmt = $conn->prepare("SELECT photo_path FROM student_faces WHERE student_id = ?");
$sfStmt->execute([$student_id]);
foreach ($sfStmt->fetchAll(PDO::FETCH_ASSOC) as $sf) {
    if (!empty($sf['photo_path'])) {
        $file = __DIR__ . '/../uploads/' . $sf['photo_path'];
        if (file_exists($file)) unlink($file);
    }
}

// ── STEP 3: Delete from database ─────────────────────────────────────────────
// CASCADE should handle student_faces + attendance, but we do it manually as safety net
$conn->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$student_id]);
$conn->prepare("DELETE FROM student_faces WHERE student_id = ?")->execute([$student_id]);
$conn->prepare("DELETE FROM students WHERE student_id = ?")->execute([$student_id]);

echo json_encode([
    'success' => true,
    'message' => "✅ Student {$student_id} deleted successfully (including face from AWS Rekognition)."
]);
