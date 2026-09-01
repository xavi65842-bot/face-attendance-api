<?php
// Get student photo
header("Content-Type: image/jpeg");
header("Access-Control-Allow-Origin: *");

require_once '../includes/Database.php';

$student_id = trim($_GET['student_id'] ?? '');
// Basic sanitization — strip anything that isn't alphanumeric, dash, or underscore
$student_id = preg_replace('/[^A-Za-z0-9\-_]/', '', $student_id);

if (!$student_id) {
    // Return default image if no student_id
    $default_image = __DIR__ . '/../uploads/check.jpg';
    if (file_exists($default_image)) {
        readfile($default_image) ;
    } else {
        echo "PLS MAKE SURE STUDENT HAS A TRUE [PHOTO_PATH]: MESSAGE BY BENX"  ;
    }
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT photo_path FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student && $student['photo_path']) {
    $photo_path = __DIR__ . '/../uploads/' . $student['photo_path'];
    if (file_exists($photo_path)) {
        readfile($photo_path);
        exit();
    }
}

// No photo found, return default
$default_image = __DIR__ . '/../uploads/check.jpg';
if (file_exists($default_image)) {
    readfile($default_image);
} else {
    // Create a simple default image
  echo "PLS MAKE SURE STUDENT HAS A TRUE [PHOTO_PATH]: MESSAGE BY BENX" ;
}
?>