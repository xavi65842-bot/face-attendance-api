<?php
// ONE-TIME SCRIPT: Remove duplicate face registrations
// Run this ONCE then delete this file
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/Database.php';
require_once 'includes/AmazonRekognition.php';

$db   = new Database();
$conn = $db->getConnection();
$rekognition = new AmazonRekognition();

echo "<h2>🧹 Fixing Duplicate Registrations</h2>";

// Get ALL students with their face_ids
$students = $conn->query(
    "SELECT student_id, full_name, face_id, photo_path, registered_at FROM students ORDER BY registered_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total students in DB: " . count($students) . "</p>";

// Get all faces from AWS
$listResult = $rekognition->listFaces();
$awsFaces   = $listResult['success'] ? $listResult['faces'] : [];
echo "<p>Total faces in AWS: " . count($awsFaces) . "</p>";

// Build a map of face_id => student for quick lookup
$faceMap = [];
$duplicates = [];

foreach ($students as $student) {
    if (!$student['face_id']) continue;

    // Search this face against all others
    // We'll use AWS to find duplicates by searching each student's face
    $photoPath = __DIR__ . '/uploads/' . $student['photo_path'];
    if (!file_exists($photoPath)) {
        echo "<p style='color:orange'>⚠️ Photo missing for {$student['full_name']} ({$student['student_id']})</p>";
        continue;
    }

    $imageData   = file_get_contents($photoPath);
    $base64Image = base64_encode($imageData);

    $searchResult = $rekognition->searchFaceForDuplicateCheck($base64Image);

    if ($searchResult['success'] && $searchResult['matched']) {
        $matchedFaceId = $searchResult['face_id'];
        $similarity    = round($searchResult['confidence'], 1);

        // If the matched face belongs to a DIFFERENT student, it's a duplicate
        if ($matchedFaceId !== $student['face_id']) {
            // Find who owns the matched face
            $ownerStmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE face_id = ? LIMIT 1");
            $ownerStmt->execute([$matchedFaceId]);
            $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);

            if ($owner) {
                echo "<p style='color:red'>🚫 DUPLICATE: <strong>{$student['full_name']}</strong> ({$student['student_id']}) is {$similarity}% similar to <strong>{$owner['full_name']}</strong> ({$owner['student_id']})</p>";
                $duplicates[] = $student;
            }
        }
    }
}

if (isset($_GET['delete']) && $_GET['delete'] === 'yes') {
    echo "<h3>🗑️ Deleting duplicates...</h3>";
    foreach ($duplicates as $dup) {
        // Delete from AWS
        if ($dup['face_id']) {
            $rekognition->deleteFace($dup['face_id']);
            echo "<p>✅ Deleted AWS face for {$dup['full_name']}</p>";
        }
        // Delete photo
        $photoPath = __DIR__ . '/uploads/' . $dup['photo_path'];
        if (file_exists($photoPath)) unlink($photoPath);
        // Delete from DB
        $conn->prepare("DELETE FROM students WHERE student_id = ?")->execute([$dup['student_id']]);
        $conn->prepare("DELETE FROM student_faces WHERE student_id = ?")->execute([$dup['student_id']]);
        echo "<p>✅ Deleted DB record for {$dup['full_name']} ({$dup['student_id']})</p>";
    }
    echo "<h3 style='color:green'>✅ Done! Duplicates removed.</h3>";
    echo "<p><strong>Delete this file after running!</strong></p>";
} else {
    if (count($duplicates) > 0) {
        echo "<br><a href='?delete=yes' style='background:red;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;'>🗑️ DELETE ALL DUPLICATES NOW</a>";
    } else {
        echo "<p style='color:green'>✅ No duplicates found!</p>";
    }
}
?>