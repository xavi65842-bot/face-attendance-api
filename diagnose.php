<?php
header('Content-Type: text/plain');

echo "=== FACE ATTENDANCE DIAGNOSTIC ===\n\n";

// 1. Check Face++ API
echo "1. Testing Face++ API...\n";
require_once 'includes/FacePlusPlus.php';
$faceApi = new FacePlusPlus();

// Create a simple test image
$testImage = base64_encode('test');
$detect = $faceApi->detectFace($testImage);
echo "Face++ detect response: " . ($detect['success'] ? "Connected" : "Failed - " . ($detect['error'] ?? 'Unknown')) . "\n\n";

// 2. Check Database
echo "2. Checking Database...\n";
require_once 'includes/Database.php';
$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    echo "✅ Database connected\n";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM students");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Students in database: " . $count['count'] . "\n";
    
    $stmt = $conn->query("SELECT student_id, full_name, face_token FROM students LIMIT 5");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($students as $s) {
        $hasToken = !empty($s['face_token']) ? "Yes" : "NO";
        echo "  - {$s['student_id']} ({$s['full_name']}): Face token? {$hasToken}\n";
    }
} else {
    echo "❌ Database connection FAILED\n";
}

echo "\n3. Check API Endpoint...\n";
$ch = curl_init('http://localhost/face-attendance-api/api/recognize.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['image' => 'test']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 200) . "\n";
?>