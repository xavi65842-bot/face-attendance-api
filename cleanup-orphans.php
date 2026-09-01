<?php
// Clean up orphaned faces in AWS (faces that exist in AWS but not in database)
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/Database.php';
require_once 'includes/AmazonRekognition.php';

$db   = new Database();
$conn = $db->getConnection();
$rekognition = new AmazonRekognition();

echo "<h2>🧹 Cleanup Orphaned AWS Faces</h2>";

// Get all faces from AWS
$listResult = $rekognition->listFaces();
if (!$listResult['success']) {
    echo "<p style='color:red'>❌ Failed: " . $listResult['error'] . "</p>";
    exit();
}

$awsFaces = $listResult['faces'];
echo "<p>AWS faces: <strong>" . count($awsFaces) . "</strong></p>";

// Get all face_ids from database
$dbFaceIds = $conn->query("SELECT face_id FROM students WHERE face_id IS NOT NULL")
                  ->fetchAll(PDO::FETCH_COLUMN);
echo "<p>DB face_ids: <strong>" . count($dbFaceIds) . "</strong></p>";

// Find orphaned faces
$orphaned = [];
foreach ($awsFaces as $face) {
    $faceId = $face['FaceId'];
    if (!in_array($faceId, $dbFaceIds)) {
        $orphaned[] = [
            'face_id' => $faceId,
            'student_id' => $face['ExternalImageId'] ?? 'Unknown'
        ];
    }
}

echo "<h3>Orphaned: " . count($orphaned) . "</h3>";

if (count($orphaned) === 0) {
    echo "<p style='color:green'>✅ No orphans! AWS and DB are in sync.</p>";
    exit();
}

foreach ($orphaned as $o) {
    echo "<p>🗑️ {$o['student_id']} → {$o['face_id']}</p>";
}

if (isset($_GET['delete'])) {
    echo "<hr><h3>Deleting...</h3>";
    foreach ($orphaned as $o) {
        $result = $rekognition->deleteFace($o['face_id']);
        if ($result['success']) {
            echo "<p style='color:green'>✅ Deleted {$o['student_id']}</p>";
        } else {
            echo "<p style='color:red'>❌ Failed {$o['student_id']}</p>";
        }
    }
    echo "<p><strong>Done! <a href='cleanup-orphans.php'>Refresh</a></strong></p>";
} else {
    echo "<hr><a href='?delete=yes' style='background:red;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🗑️ DELETE ALL ORPHANS</a>";
}
?>