<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../includes/Database.php';
require_once '../includes/AmazonRekognition.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image provided']);
        exit();
    }

    $imageData = $input['image'];

    // Strip data URI prefix if present
    if (strpos($imageData, 'data:image') === 0) {
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
    }

    // Validate base64
    $imageBytes = base64_decode($imageData, true);
    if ($imageBytes === false || strlen($imageBytes) < 100) {
        echo json_encode(['success' => false, 'message' => 'Invalid image data']);
        exit();
    }

    $rekognition = new AmazonRekognition();

    // Check if collection has any faces — if empty, face is definitely unique
    $listResult = $rekognition->listFaces();
    if (!$listResult['success']) {
        echo json_encode(['success' => false, 'message' => 'Could not access face collection: ' . ($listResult['error'] ?? 'unknown error')]);
        exit();
    }

    if (count($listResult['faces']) === 0) {
        // Collection is empty — validate image has a face, then confirm unique
        $db   = new Database();
        $conn = $db->getConnection();

        // Use detectFaces to validate image quality
        $validateResult = $rekognition->searchFaceForDuplicateCheck($imageData);
        if (!$validateResult['success']) {
            echo json_encode([
                'success' => false,
                'message' => $validateResult['error'] ?? 'No face detected in image. Please ensure your face is clearly visible.'
            ]);
            exit();
        }

        echo json_encode([
            'success' => true,
            'exists'  => false,
            'message' => 'Face not found in system — safe to register'
        ]);
        exit();
    }

    // Collection has faces — do a proper duplicate search
    $searchResult = $rekognition->searchFaceForDuplicateCheck($imageData);

    // If the search itself failed (e.g. no face in image) — BLOCK, don't allow registration
    if (!$searchResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => $searchResult['error'] ?? 'Face validation failed. Please retake your photo with good lighting.'
        ]);
        exit();
    }

    if ($searchResult['matched']) {
        // Face already exists — find the student who owns it
        $db   = new Database();
        $conn = $db->getConnection();

        $face_id           = $searchResult['face_id'];
        $external_image_id = $searchResult['external_image_id'];
        $similarity        = round($searchResult['confidence'], 1);

        $stmt = $conn->prepare(
            "SELECT student_id, full_name, department FROM students WHERE face_id = ? OR student_id = ? LIMIT 1"
        );
        $stmt->execute([$face_id, $external_image_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'exists'     => true,
            'message'    => 'Face already registered',
            'student'    => $student ?: ['student_id' => $external_image_id, 'full_name' => 'Unknown Student'],
            'confidence' => $similarity
        ]);
    } else {
        // No match found — safe to register
        echo json_encode([
            'success' => true,
            'exists'  => false,
            'message' => 'Face not found in system — safe to register'
        ]);
    }

} catch (Exception $e) {
    error_log('[check-face.php] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Face validation failed: ' . $e->getMessage()
    ]);
}
?>
