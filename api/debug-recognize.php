<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../includes/Database.php';

// Same thresholds as recognize.php
define('SUPER_STRICT_THRESHOLD', 0.35);
define('STRICT_THRESHOLD',       0.45);
define('MATCH_THRESHOLD',        0.55);
define('REJECT_THRESHOLD',       0.65);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['face_descriptor'])) {
    echo json_encode(['success' => false, 'message' => 'No face descriptor received.']);
    exit();
}

$liveDescriptor = $input['face_descriptor'];

if (!is_array($liveDescriptor) || count($liveDescriptor) !== 128) {
    echo json_encode(['success' => false, 'message' => 'Invalid face descriptor. Expected 128 values.']);
    exit();
}

$db   = new Database();
$conn = $db->getConnection();

// Load all stored face descriptors with detailed info
$allFaces = $conn->query(
    "SELECT sf.student_id, sf.face_descriptor, sf.photo_path, sf.created_at,
            s.full_name, s.department, s.semester
     FROM student_faces sf
     JOIN students s ON sf.student_id = s.student_id
     WHERE sf.face_descriptor IS NOT NULL
     ORDER BY sf.student_id, sf.created_at"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($allFaces)) {
    echo json_encode([
        'success' => false,
        'message' => 'No registered faces found.',
        'debug_info' => [
            'total_faces' => 0,
            'registered_students' => 0
        ]
    ]);
    exit();
}

// Calculate distances to all faces
$results = [];
$studentGroups = [];

foreach ($allFaces as $row) {
    $studentId = $row['student_id'];
    $stored = json_decode($row['face_descriptor'], true);
    
    if (!$stored || count($stored) !== 128) continue;
    
    // Calculate distance
    $sum = 0;
    for ($i = 0; $i < 128; $i++) {
        $diff = ($liveDescriptor[$i] ?? 0) - ($stored[$i] ?? 0);
        $sum += $diff * $diff;
    }
    $distance = sqrt($sum);
    
    // Determine match level
    $matchLevel = 'NO_MATCH';
    $confidence = 0;
    
    if ($distance <= SUPER_STRICT_THRESHOLD) {
        $matchLevel = 'SUPER_STRICT';
        $confidence = 100;
    } elseif ($distance <= STRICT_THRESHOLD) {
        $matchLevel = 'STRICT';
        $confidence = 95;
    } elseif ($distance <= MATCH_THRESHOLD) {
        $matchLevel = 'ACCEPTABLE';
        $confidence = max(65, 95 - round(30 * (($distance - STRICT_THRESHOLD) / (MATCH_THRESHOLD - STRICT_THRESHOLD))));
    } elseif ($distance <= REJECT_THRESHOLD) {
        $matchLevel = 'WEAK';
        $confidence = max(0, 65 - round(65 * (($distance - MATCH_THRESHOLD) / (REJECT_THRESHOLD - MATCH_THRESHOLD))));
    }
    
    $result = [
        'student_id' => $studentId,
        'student_name' => $row['full_name'],
        'department' => $row['department'],
        'semester' => $row['semester'],
        'distance' => round($distance, 4),
        'confidence' => $confidence,
        'match_level' => $matchLevel,
        'photo_path' => $row['photo_path'],
        'registered_at' => $row['created_at']
    ];
    
    $results[] = $result;
    
    // Group by student
    if (!isset($studentGroups[$studentId])) {
        $studentGroups[$studentId] = [
            'student_name' => $row['full_name'],
            'department' => $row['department'],
            'semester' => $row['semester'],
            'faces' => [],
            'best_distance' => PHP_FLOAT_MAX,
            'best_confidence' => 0
        ];
    }
    
    $studentGroups[$studentId]['faces'][] = $result;
    
    if ($distance < $studentGroups[$studentId]['best_distance']) {
        $studentGroups[$studentId]['best_distance'] = $distance;
        $studentGroups[$studentId]['best_confidence'] = $confidence;
    }
}

// Sort results by distance (best matches first)
usort($results, function($a, $b) {
    return $a['distance'] <=> $b['distance'];
});

// Find best match
$bestMatch = $results[0] ?? null;
$wouldMatch = $bestMatch && $bestMatch['distance'] <= REJECT_THRESHOLD && $bestMatch['confidence'] >= 65;

echo json_encode([
    'success' => true,
    'would_match' => $wouldMatch,
    'best_match' => $bestMatch,
    'all_matches' => array_slice($results, 0, 10), // Top 10 matches
    'student_summary' => array_map(function($group) {
        return [
            'student_id' => key($group),
            'student_name' => $group['student_name'],
            'department' => $group['department'],
            'semester' => $group['semester'],
            'face_count' => count($group['faces']),
            'best_distance' => round($group['best_distance'], 4),
            'best_confidence' => $group['best_confidence']
        ];
    }, array_slice($studentGroups, 0, 5)), // Top 5 students
    'debug_info' => [
        'total_faces_checked' => count($allFaces),
        'unique_students' => count($studentGroups),
        'thresholds' => [
            'super_strict' => SUPER_STRICT_THRESHOLD,
            'strict' => STRICT_THRESHOLD,
            'match' => MATCH_THRESHOLD,
            'reject' => REJECT_THRESHOLD
        ],
        'descriptor_length' => count($liveDescriptor),
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);