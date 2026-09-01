<?php
// ============================================================
// FIX & RE-SYNC ALL STUDENTS INTO FACE++ FACESET
// Run this once from browser: http://localhost/face-attendance-api/fix-existing-faces.php
// ============================================================

require_once 'includes/Database.php';
require_once 'includes/FacePlusPlus.php';

$db      = new Database();
$conn    = $db->getConnection();
$faceApi = new FacePlusPlus();

$results  = [];
$fixed    = 0;
$failed   = 0;
$skipped  = 0;

// Step 1: Ensure faceset exists
$createResult = $faceApi->createFaceSet('school_attendance');
$results[] = "Faceset check: " . json_encode($createResult);

// Step 2: Get all students with a face token
$stmt = $conn->prepare("SELECT student_id, full_name, face_token, photo_path FROM students WHERE face_token IS NOT NULL AND face_token != ''");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results[] = "Found " . count($students) . " students with face tokens in DB.";

foreach ($students as $student) {
    $face_token = $student['face_token'];
    $student_id = $student['student_id'];
    $name       = $student['full_name'];

    // Try to add face token back into the faceset
    $addResult = $faceApi->addFaceToSet($face_token, 'school_attendance', $student_id);

    if (isset($addResult['error_message'])) {
        // If token is invalid/expired — re-detect from saved photo
        if (strpos($addResult['error_message'], 'INVALID_FACE_TOKEN') !== false
            || strpos($addResult['error_message'], 'face_token') !== false) {

            $photo_path = __DIR__ . '/uploads/' . $student['photo_path'];

            if ($student['photo_path'] && file_exists($photo_path)) {
                $imageData    = file_get_contents($photo_path);
                $image_base64 = base64_encode($imageData);

                $detectResult = $faceApi->detectFace($image_base64);

                if ($detectResult['success']) {
                    $new_token = $detectResult['face_token'];

                    // Add new token to faceset
                    $faceApi->addFaceToSet($new_token, 'school_attendance', $student_id);

                    // Update DB with new token
                    $conn->prepare("UPDATE students SET face_token = ? WHERE student_id = ?")->execute([$new_token, $student_id]);
                    $conn->prepare("UPDATE student_faces SET face_token = ? WHERE student_id = ? AND face_token = ?")->execute([$new_token, $student_id, $face_token]);

                    $results[] = "✅ RE-DETECTED & FIXED: {$name} ({$student_id}) — new token: {$new_token}";
                    $fixed++;
                } else {
                    $results[] = "❌ FAILED re-detect for {$name} ({$student_id}) — photo may be missing or unclear.";
                    $failed++;
                }
            } else {
                $results[] = "❌ FAILED: {$name} ({$student_id}) — photo file not found at: {$student['photo_path']}";
                $failed++;
            }
        } else {
            $results[] = "⚠️ ERROR adding {$name} ({$student_id}): " . $addResult['error_message'];
            $failed++;
        }
    } else {
        $results[] = "✅ OK: {$name} ({$student_id}) — added to faceset.";
        $fixed++;
    }
}

// Step 3: Also sync student_faces table entries
$sfStmt = $conn->prepare("SELECT sf.student_id, sf.face_token, s.full_name FROM student_faces sf JOIN students s ON s.student_id = sf.student_id");
$sfStmt->execute();
$extraFaces = $sfStmt->fetchAll(PDO::FETCH_ASSOC);

$results[] = "\nAlso syncing " . count($extraFaces) . " entries from student_faces table...";

foreach ($extraFaces as $sf) {
    $addResult = $faceApi->addFaceToSet($sf['face_token'], 'school_attendance', $sf['student_id']);
    if (!isset($addResult['error_message'])) {
        $results[] = "✅ student_faces sync OK: {$sf['full_name']} ({$sf['student_id']})";
    }
    // Silently skip errors here — main sync above already handled them
}

echo "<pre style='font-family:monospace; font-size:14px; padding:20px;'>";
echo "=== FACE SYNC RESULTS ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n=== SUMMARY ===\n";
echo "Fixed/Synced : {$fixed}\n";
echo "Failed       : {$failed}\n";
echo "\nDone. Now test face recognition again.\n";
echo "</pre>";
?>
