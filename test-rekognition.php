<?php
// Amazon Rekognition Test Script
// Run this to verify your Amazon Rekognition setup is working

header('Content-Type: text/html; charset=utf-8');
echo "<h1>🔍 Amazon Rekognition Test</h1>";

require_once 'includes/AmazonRekognition.php';
require_once 'includes/Database.php';

echo "<h2>Step 1: Testing AWS Connection</h2>";

try {
    $rekognition = new AmazonRekognition();
    echo "<p style='color:green'>✅ Amazon Rekognition client initialized successfully</p>";
    
    // Test collection exists
    $listResult = $rekognition->listFaces();
    if ($listResult['success']) {
        $faceCount = count($listResult['faces']);
        echo "<p style='color:green'>✅ Collection 'school_attendance' found with {$faceCount} faces</p>";
        
        if ($faceCount > 0) {
            echo "<h3>Registered Faces:</h3>";
            echo "<ul>";
            foreach ($listResult['faces'] as $face) {
                $externalId = $face['ExternalImageId'] ?? 'Unknown';
                $confidence = round($face['Confidence'], 2);
                echo "<li>Face ID: {$face['FaceId']} | Student: {$externalId} | Confidence: {$confidence}%</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red'>❌ Error accessing collection: " . $listResult['error'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ AWS Connection failed: " . $e->getMessage() . "</p>";
    echo "<p><strong>Check:</strong></p>";
    echo "<ul>";
    echo "<li>Your AWS credentials in config-aws.php</li>";
    echo "<li>Internet connection</li>";
    echo "<li>AWS region is correct (eu-north-1)</li>";
    echo "</ul>";
    exit();
}

echo "<h2>Step 2: Testing Database Connection</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if face_id column exists
    $result = $conn->query("DESCRIBE students");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('face_id', $columns)) {
        echo "<p style='color:green'>✅ Database has face_id column</p>";
    } else {
        echo "<p style='color:orange'>⚠️ face_id column missing. Run: database/migrate-to-rekognition.sql</p>";
    }
    
    // Count registered students
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p style='color:green'>✅ Database connected. {$total} students registered</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit();
}

echo "<h2>Step 3: Test Image Recognition</h2>";

// Check if we have a test image
$testImagePath = __DIR__ . '/uploads/check.jpg';
if (file_exists($testImagePath)) {
    echo "<p>Testing with: uploads/check.jpg</p>";
    
    $imageData = file_get_contents($testImagePath);
    $base64Image = base64_encode($imageData);
    
    $searchResult = $rekognition->searchFace($base64Image);
    
    if ($searchResult['success']) {
        if ($searchResult['matched']) {
            echo "<p style='color:green'>✅ Face recognized!</p>";
            echo "<ul>";
            echo "<li>Face ID: {$searchResult['face_id']}</li>";
            echo "<li>Confidence: " . round($searchResult['confidence'], 2) . "%</li>";
            echo "<li>Student ID: " . ($searchResult['external_image_id'] ?? 'Not set') . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color:orange'>⚠️ No matching face found in collection</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Recognition failed: " . $searchResult['error'] . "</p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ No test image found at uploads/check.jpg</p>";
    echo "<p>Upload a photo to uploads/check.jpg to test recognition</p>";
}

echo "<h2>✅ Test Complete</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>If all tests pass, your system is ready!</li>";
echo "<li>Register students using your frontend</li>";
echo "<li>Test attendance marking</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Amazon Rekognition Test Script | " . date('Y-m-d H:i:s') . "</small></p>";
?>