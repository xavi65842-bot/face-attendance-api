<?php
// Direct Face++ API test - No database involved
header('Content-Type: text/html');

echo "<h1>Direct Face++ API Test</h1>";

require_once 'includes/FacePlusPlus.php';

$faceApi = new FacePlusPlus();

// First, delete old faceset to start fresh
echo "<h2>1. Deleting old faceset...</h2>";
$deleteResult = $faceApi->deleteFaceSet('school_attendance');
echo "<pre>";
print_r($deleteResult);
echo "</pre>";

// Create new faceset
echo "<h2>2. Creating new faceset...</h2>";
$createResult = $faceApi->createFaceSet('school_attendance');
echo "<pre>";
print_r($createResult);
echo "</pre>";

// Use a test image from the uploads folder
$testImagePath = __DIR__ . '/uploads/student_LCSMT-NGA-005-ADM-1001538_*.jpg';
$files = glob(__DIR__ . '/uploads/student_LCSMT-NGA-005-ADM-1001538_*.jpg');

if (count($files) > 0) {
    $testImage = $files[0];
    echo "<h2>3. Using test image: " . basename($testImage) . "</h2>";
    
    // Read image and convert to base64
    $imageData = base64_encode(file_get_contents($testImage));
    
    // Detect face
    echo "<h2>4. Detecting face...</h2>";
    $detectResult = $faceApi->detectFace($imageData);
    echo "<pre>";
    print_r($detectResult);
    echo "</pre>";
    
    if ($detectResult['success']) {
        $face_token = $detectResult['face_token'];
        $student_id = 'PRIME001';
        
        // Add face to set with user_id
        echo "<h2>5. Adding face to set with user_id: $student_id</h2>";
        $addResult = $faceApi->addFaceToSet($face_token, 'school_attendance', $student_id);
        echo "<pre>";
        print_r($addResult);
        echo "</pre>";
        
        // Verify the faceset contents
        echo "<h2>6. Verifying faceset contents...</h2>";
        $faceset = $faceApi->getFaceSet('school_attendance');
        echo "<pre>";
        print_r($faceset);
        echo "</pre>";
        
        // Test search with same face
        echo "<h2>7. Testing search with same face...</h2>";
        $searchResult = $faceApi->searchFace($face_token, 'school_attendance');
        echo "<pre>";
        print_r($searchResult);
        echo "</pre>";
        
        if (!empty($searchResult['user_id'])) {
            echo "<h2 style='color:green'>✅ SUCCESS! user_id = " . $searchResult['user_id'] . "</h2>";
        } else {
            echo "<h2 style='color:red'>❌ FAILED! user_id is still empty</h2>";
        }
    }
} else {
    echo "<p style='color:red'>No test image found. Please register a student first.</p>";
}
?>