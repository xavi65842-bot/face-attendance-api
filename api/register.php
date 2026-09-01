<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../includes/Database.php';
require_once '../includes/AmazonRekognition.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

$student_id   = trim($input['student_id']   ?? '');
$full_name    = trim($input['full_name']    ?? '');
$department   = trim($input['department']   ?? '');
$year_intake  = trim($input['year_intake']  ?? '');
$semester     = intval($input['semester']   ?? 0);
$image_base64 = $input['image']             ?? '';

if (!$student_id || !$full_name || !$department || !$year_intake || !$semester || !$image_base64) {
    echo json_encode(['success' => false, 'message' => 'All fields and image are required']);
    exit();
}

// Strip data URI prefix if present
if (strpos($image_base64, 'base64,') !== false) {
    $image_base64 = explode('base64,', $image_base64)[1];
}

$db   = new Database();
$conn = $db->getConnection();

// ── STEP 1: Student ID uniqueness check ──────────────────────────────────────
$checkStmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE student_id = ? LIMIT 1");
$checkStmt->execute([$student_id]);
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    echo json_encode([
        'success' => false,
        'message' => "❌ Student ID '{$student_id}' is already registered to {$existing['full_name']}."
    ]);
    exit();
}

// ── STEP 2: STRICT DUPLICATE FACE DETECTION ──────────────────────────────────
// This MUST pass before we allow registration. Any error = BLOCK registration.
$rekognition = new AmazonRekognition();

// Check if collection has any faces first (skip search if empty)
$listResult = $rekognition->listFaces();
$collectionHasFaces = $listResult['success'] && count($listResult['faces']) > 0;

if ($collectionHasFaces) {
    // Use dedicated duplicate check method (separate from attendance search)
    $duplicateResult = $rekognition->searchFaceForDuplicateCheck($image_base64);
    
    // If the duplicate check itself failed (e.g. no face in image), BLOCK registration
    if (!$duplicateResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => '❌ Face validation failed: ' . $duplicateResult['error'] . ' Please retake your photo with good lighting.'
        ]);
        exit();
    }
    
    // If a matching face was found, BLOCK registration
    if ($duplicateResult['matched']) {
        $existing_face_id    = $duplicateResult['face_id'];
        $similarity          = round($duplicateResult['confidence'], 1);
        $external_image_id   = $duplicateResult['external_image_id'];
        
        // Find the student who owns this face
        $faceOwnerStmt = $conn->prepare(
            "SELECT student_id, full_name FROM students WHERE face_id = ? OR student_id = ? LIMIT 1"
        );
        $faceOwnerStmt->execute([$existing_face_id, $external_image_id]);
        $faceOwner = $faceOwnerStmt->fetch(PDO::FETCH_ASSOC);
        
        $ownerName = $faceOwner ? $faceOwner['full_name'] : 'another student';
        $ownerId   = $faceOwner ? $faceOwner['student_id'] : $external_image_id;
        
        echo json_encode([
            'success'          => false,
            'duplicate_face'   => true,
            'message'          => "🚫 DUPLICATE FACE BLOCKED! This face ({$similarity}% match) is already registered to {$ownerName} ({$ownerId}). One person = one registration only.",
            'existing_student' => [
                'student_id' => $ownerId,
                'full_name'  => $ownerName,
                'similarity' => $similarity
            ]
        ]);
        exit();
    }
} else {
    // Collection is empty — still validate that the image contains a face
    $validateResult = $rekognition->searchFaceForDuplicateCheck($image_base64);
    if (!$validateResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => '❌ ' . $validateResult['error']
        ]);
        exit();
    }
}

// ── STEP 3: Save photo to disk ───────────────────────────────────────────────
$upload_dir = __DIR__ . '/../uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$filename  = 'student_' . preg_replace('/[^A-Za-z0-9]/', '_', $student_id) . '_' . date('Ymd_His') . '.jpg';
$filepath  = $upload_dir . $filename;
$imageData = base64_decode($image_base64);
$saved     = file_put_contents($filepath, $imageData);

if ($saved === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save photo. Check uploads/ folder permissions.']);
    exit();
}

// ── STEP 4: Index face with Amazon Rekognition ───────────────────────────────
$indexResult = $rekognition->indexFace($image_base64, $student_id);

if (!$indexResult['success']) {
    // Clean up saved photo if face indexing failed
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode([
        'success' => false,
        'message' => 'Face registration failed: ' . $indexResult['error']
    ]);
    exit();
}

$face_id = $indexResult['face_id'];
$confidence = $indexResult['confidence'];

// ── STEP 5: Final duplicate guard after indexing ────────────────────────────
// After indexing, immediately search again to confirm no duplicate slipped through
$postIndexSearch = $rekognition->searchFaceForDuplicateCheck($image_base64);

if ($postIndexSearch['success'] && $postIndexSearch['matched']) {
    $matched_face_id = $postIndexSearch['face_id'];
    
    // If the matched face is NOT the one we just created, it's a duplicate
    if ($matched_face_id !== $face_id) {
        // Remove the face we just indexed
        $rekognition->deleteFace($face_id);
        if (file_exists($filepath)) unlink($filepath);
        
        // Find owner
        $ownerStmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE face_id = ? LIMIT 1");
        $ownerStmt->execute([$matched_face_id]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        
        $ownerName = $owner ? $owner['full_name'] : 'another student';
        $ownerId   = $owner ? $owner['student_id'] : 'unknown';
        
        echo json_encode([
            'success'        => false,
            'duplicate_face' => true,
            'message'        => "🚫 DUPLICATE FACE BLOCKED! This face is already registered to {$ownerName} ({$ownerId}). One person = one registration only."
        ]);
        exit();
    }
}

// Also check database for same face_id (safety net)
$duplicateCheck = $conn->prepare("SELECT student_id, full_name FROM students WHERE face_id = ? LIMIT 1");
$duplicateCheck->execute([$face_id]);
$duplicate = $duplicateCheck->fetch(PDO::FETCH_ASSOC);

if ($duplicate) {
    $rekognition->deleteFace($face_id);
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode([
        'success'        => false,
        'duplicate_face' => true,
        'message'        => "🚫 DUPLICATE FACE BLOCKED! This face is already registered to {$duplicate['full_name']} ({$duplicate['student_id']})."
    ]);
    exit();
}

// ── STEP 6: Save to database ──────────────────────────────────────────────────
try {
    $stmt = $conn->prepare(
        "INSERT INTO students (student_id, full_name, department, year_intake, semester, face_id, photo_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$student_id, $full_name, $department, $year_intake, $semester, $face_id, $filename]);

    // Also insert into student_faces table for compatibility
    $faceStmt = $conn->prepare(
        "INSERT INTO student_faces (student_id, face_token, photo_path) VALUES (?, ?, ?)"
    );
    $faceStmt->execute([$student_id, $face_id, $filename]);

} catch (PDOException $e) {
    // Clean up on database error
    if (file_exists($filepath)) unlink($filepath);
    $rekognition->deleteFace($face_id); // Remove from AWS collection
    error_log('[register.php] DB insert failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save student record. Please try again.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => '✅ Student registered successfully with Amazon Rekognition. Face uniqueness verified.',
    'data'    => [
        'student_id'   => $student_id,
        'full_name'    => $full_name,
        'face_id'      => $face_id,
        'confidence'   => round($confidence, 2),
        'photo_url'    => "http://localhost/face-attendance-api/uploads/" . $filename,
        'provider'     => 'Amazon Rekognition',
        'duplicate_check' => 'Passed'
    ]
]);