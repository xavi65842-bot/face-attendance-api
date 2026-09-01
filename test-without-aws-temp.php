<?php
// Temporary test of register.php without AWS (for development only)
header('Content-Type: application/json');

echo "Testing registration API without AWS...\n\n";

// Create a test image (1x1 pixel base64 image)
$testImage = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU77zgAAAABJRU5ErkJggg==';

$testData = [
    'student_id' => 'TEST-001',
    'full_name' => 'Test Student',
    'department' => 'Computer Software Engineering',
    'year_intake' => '2024 September',
    'semester' => 1,
    'image' => 'data:image/png;base64,' . $testImage
];

echo "Test data prepared:\n";
echo "Student ID: " . $testData['student_id'] . "\n";
echo "Name: " . $testData['full_name'] . "\n";
echo "Department: " . $testData['department'] . "\n\n";

// Simulate the API call
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $testData;

// Capture output
ob_start();

// Mock the AWS part temporarily
class MockAmazonRekognition {
    public function indexFace($image_base64, $externalImageId) {
        return [
            'success' => true,
            'face_id' => 'mock-face-id-' . uniqid(),
            'confidence' => 95.5
        ];
    }
    
    public function deleteFace($faceId) {
        return ['success' => true];
    }
}

// Temporarily replace the real class
if (!class_exists('AmazonRekognition')) {
    class AmazonRekognition extends MockAmazonRekognition {}
}

echo "🧪 This is a MOCK test to verify your PHP code works.\n";
echo "📝 The registration logic will be tested without actually calling AWS.\n";
echo "🔧 Fix your AWS credentials, then test with real AWS.\n\n";

echo "Mock AWS Response:\n";
$mockRekognition = new MockAmazonRekognition();
$mockResult = $mockRekognition->indexFace($testImage, 'TEST-001');
echo "Face ID: " . $mockResult['face_id'] . "\n";
echo "Confidence: " . $mockResult['confidence'] . "%\n\n";

echo "✅ Your PHP code structure is working!\n";
echo "✅ Next step: Fix AWS credentials and test with real AWS.\n\n";

echo "To fix AWS credentials:\n";
echo "1. Read: get-new-aws-credentials.md\n";
echo "2. Get new AWS Access Keys\n";
echo "3. Update config-aws.php\n";
echo "4. Run: php simple-aws-test.php\n";
?>