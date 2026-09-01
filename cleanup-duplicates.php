<?php
// Clean up duplicate faces in the system
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🧹 Cleanup Duplicate Faces</h1>";

require_once 'includes/Database.php';
require_once 'includes/AmazonRekognition.php';

$db = new Database();
$conn = $db->getConnection();
$rekognition = new AmazonRekognition();

echo "<h2>Step 1: Checking for duplicate faces in AWS collection</h2>";

// Get all faces from AWS collection
$listResult = $rekognition->listFaces();
if (!$listResult['success']) {
    echo "<p style='color:red'>❌ Failed to list faces: " . $listResult['error'] . "</p>";
    exit();
}

$awsFaces = $listResult['faces'];
echo "<p>Found " . count($awsFaces) . " faces in AWS collection</p>";

// Get all students from database
$students = $conn->query("SELECT student_id, full_name, face_id FROM students WHERE face_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Found " . count($students) . " students with face_id in database</p>";

echo "<h2>Step 2: Checking for inconsistencies</h2>";

$duplicatesFound = 0;
$orphanedFaces = 0;
$missingFaces = 0;

// Check for faces in AWS but not in database
foreach ($awsFaces as $awsFace) {
    $faceId = $awsFace['FaceId'];
    $externalId = $awsFace['ExternalImageId'] ?? 'Unknown';
    
    $found = false;
    foreach ($students as $student) {
        if ($student['face_id'] === $faceId || $student['student_id'] === $externalId) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "<p style='color:orange'>⚠️ Orphaned face in AWS: {$faceId} (External ID: {$externalId})</p>";
        $orphanedFaces++;
    }
}

// Check for students with face_id but face not in AWS
foreach ($students as $student) {
    if ($student['face_id']) {
        $found = false;
        foreach ($awsFaces as $awsFace) {
            if ($awsFace['FaceId'] === $student['face_id']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "<p style='color:red'>❌ Student {$student['student_id']} ({$student['full_name']}) has face_id {$student['face_id']} but face not found in AWS</p>";
            $missingFaces++;
        }
    }
}

echo "<h2>Step 3: Summary</h2>";
echo "<p><strong>Orphaned faces in AWS:</strong> {$orphanedFaces}</p>";
echo "<p><strong>Missing faces in AWS:</strong> {$missingFaces}</p>";

if ($orphanedFaces > 0 || $missingFaces > 0) {
    echo "<h3>🔧 Cleanup Actions Available:</h3>";
    echo "<p><a href='?action=cleanup_orphaned' style='background:orange;color:white;padding:10px;text-decoration:none;border-radius:5px;'>Clean Up Orphaned Faces</a></p>";
    echo "<p><a href='?action=reset_missing' style='background:red;color:white;padding:10px;text-decoration:none;border-radius:5px;'>Reset Missing Face IDs</a></p>";
} else {
    echo "<p style='color:green'>✅ No cleanup needed! System is consistent.</p>";
}

// Handle cleanup actions
if (isset($_GET['action'])) {
    echo "<hr><h2>Cleanup Results:</h2>";
    
    if ($_GET['action'] === 'cleanup_orphaned') {
        $cleaned = 0;
        foreach ($awsFaces as $awsFace) {
            $faceId = $awsFace['FaceId'];
            $externalId = $awsFace['ExternalImageId'] ?? 'Unknown';
            
            $found = false;
            foreach ($students as $student) {
                if ($student['face_id'] === $faceId || $student['student_id'] === $externalId) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $deleteResult = $rekognition->deleteFace($faceId);
                if ($deleteResult['success']) {
                    echo "<p style='color:green'>✅ Deleted orphaned face: {$faceId}</p>";
                    $cleaned++;
                } else {
                    echo "<p style='color:red'>❌ Failed to delete face {$faceId}: " . $deleteResult['error'] . "</p>";
                }
            }
        }
        echo "<p><strong>Cleaned up {$cleaned} orphaned faces</strong></p>";
    }
    
    if ($_GET['action'] === 'reset_missing') {
        $reset = 0;
        foreach ($students as $student) {
            if ($student['face_id']) {
                $found = false;
                foreach ($awsFaces as $awsFace) {
                    if ($awsFace['FaceId'] === $student['face_id']) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $stmt = $conn->prepare("UPDATE students SET face_id = NULL WHERE student_id = ?");
                    if ($stmt->execute([$student['student_id']])) {
                        echo "<p style='color:green'>✅ Reset face_id for {$student['student_id']} ({$student['full_name']})</p>";
                        $reset++;
                    }
                }
            }
        }
        echo "<p><strong>Reset {$reset} missing face IDs</strong></p>";
        echo "<p style='color:blue'>ℹ️ These students will need to re-register their faces</p>";
    }
}

echo "<hr>";
echo "<h2>✅ Duplicate Prevention Features Added:</h2>";
echo "<ul>";
echo "<li>✅ Face search before registration (60% threshold for broader duplicate detection)</li>";
echo "<li>✅ Multiple match checking (checks up to 5 similar faces)</li>";
echo "<li>✅ Double verification after indexing</li>";
echo "<li>✅ Automatic cleanup on registration failure</li>";
echo "<li>✅ Detailed error messages with existing student info</li>";
echo "</ul>";

echo "<p><strong>Your system now has STRONG duplicate face prevention!</strong></p>";
echo "<p><small>Cleanup Script | " . date('Y-m-d H:i:s') . "</small></p>";
?>