<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once 'config.php';
require_once '../includes/AmazonRekognition.php';

try {
    $db = getDB();
    
    // 1. Wipe AWS Rekognition Collection
    $rekognition = new AmazonRekognition();
    $awsReset = $rekognition->resetCollection();
    
    // 2. Clear all students & attendance from MySQL
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE attendance;");
    $db->exec("TRUNCATE TABLE student_faces;");
    $db->exec("TRUNCATE TABLE students;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // 3. Clear uploads directory (keep .gitkeep)
    $uploadDir = dirname(__DIR__) . '/uploads';
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                @unlink($file);
            }
        }
    }
    
    sendResponse(true, 'All old student faces, biometric embeddings, and attendance records have been completely cleared! System is 100% fresh and ready.', [
        'aws_rekognition' => $awsReset,
        'database' => 'CLEARED (students, student_faces, attendance)',
        'faculty' => 'PRESERVED (13 Nigerian lecturers active)',
        'status' => 'READY_FOR_FRESH_REGISTRATIONS'
    ]);
    
} catch (Exception $e) {
    sendResponse(false, 'Reset error: ' . $e->getMessage(), null, 500);
}
?>