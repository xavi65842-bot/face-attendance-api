<?php
// Run this ONCE to create the face collection
header('Content-Type: text/html');

echo "<h1>Setting up Amazon Rekognition Collection</h1>";

require_once 'includes/AmazonRekognition.php';

$rekognition = new AmazonRekognition();
$result = $rekognition->createCollection();

if ($result['success']) {
    echo "<p style='color:green'>✅ " . $result['message'] . "</p>";
} else {
    echo "<p style='color:red'>❌ Error: " . $result['message'] . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Update your database: ALTER TABLE students ADD COLUMN face_id VARCHAR(100);</li>";
echo "<li>Update register.php to use Amazon Rekognition</li>";
echo "<li>Update recognize.php to use Amazon Rekognition</li>";
echo "<li>Test registration and attendance</li>";
echo "</ol>";
?>