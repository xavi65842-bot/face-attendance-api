<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: text/plain");
header("Access-Control-Allow-Origin: *");

require_once '../includes/Database.php';
require_once '../includes/FacePlusPlus.php';

$db      = new Database();
$conn    = $db->getConnection();
$faceApi = new FacePlusPlus();

// Get API keys via reflection
$ref        = new ReflectionClass($faceApi);
$keyProp    = $ref->getProperty('api_key');    $keyProp->setAccessible(true);
$secretProp = $ref->getProperty('api_secret'); $secretProp->setAccessible(true);
$api_key    = $keyProp->getValue($faceApi);
$api_secret = $secretProp->getValue($faceApi);

function faceRequest($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

// ── STEP 1: Get all face tokens currently in the Face++ faceset ──────────────
echo "=== STEP 1: Get current faceset tokens from Face++ ===\n";
$detail = faceRequest('https://api-us.faceplusplus.com/facepp/v3/faceset/getdetail', [
    'api_key'    => $api_key,
    'api_secret' => $api_secret,
    'outer_id'   => 'school_attendance'
]);

if (isset($detail['error_message'])) {
    echo "Faceset not found or error: " . $detail['error_message'] . "\n";
    echo "Creating fresh faceset...\n";
    faceRequest('https://api-us.faceplusplus.com/facepp/v3/faceset/create', [
        'api_key'      => $api_key,
        'api_secret'   => $api_secret,
        'outer_id'     => 'school_attendance',
        'display_name' => 'School Attendance'
    ]);
    $facesetTokens = [];
} else {
    $facesetTokens = $detail['face_tokens'] ?? [];
    echo "Face++ faceset has " . count($facesetTokens) . " face(s)\n";
}

// ── STEP 2: Get all face tokens that belong to ACTIVE students in DB ─────────
echo "\n=== STEP 2: Get face tokens for students still in database ===\n";
$dbTokens = [];

// From student_faces table (primary)
$rows = $conn->query("SELECT face_token FROM student_faces")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    if (!empty($r['face_token'])) $dbTokens[] = $r['face_token'];
}

// From students.face_token (legacy fallback)
$rows2 = $conn->query("SELECT face_token FROM students WHERE face_token IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    if (!empty($r['face_token']) && !in_array($r['face_token'], $dbTokens)) {
        $dbTokens[] = $r['face_token'];
    }
}

echo "Database has " . count($dbTokens) . " face token(s) for active students\n";

// ── STEP 3: Remove from Face++ any token NOT in the DB (orphaned/deleted students) ──
echo "\n=== STEP 3: Remove orphaned face tokens from Face++ ===\n";
$orphaned = array_diff($facesetTokens, $dbTokens);

if (empty($orphaned)) {
    echo "No orphaned tokens found — Face++ is already in sync\n";
} else {
    echo count($orphaned) . " orphaned token(s) found, removing...\n";
    foreach ($orphaned as $token) {
        $r = faceRequest('https://api-us.faceplusplus.com/facepp/v3/faceset/removeface', [
            'api_key'     => $api_key,
            'api_secret'  => $api_secret,
            'outer_id'    => 'school_attendance',
            'face_tokens' => $token
        ]);
        $status = isset($r['faceset_token']) ? "OK removed" : ("Failed: " . ($r['error_message'] ?? json_encode($r)));
        echo "  Token " . substr($token, 0, 12) . "... → {$status}\n";
    }
}

// ── STEP 4: Add back any DB token that is missing from Face++ ────────────────
echo "\n=== STEP 4: Re-add any missing DB tokens into Face++ ===\n";
$missing = array_diff($dbTokens, $facesetTokens);

if (empty($missing)) {
    echo "All DB tokens are already in Face++ — nothing to re-add\n";
} else {
    echo count($missing) . " token(s) missing from Face++, re-adding...\n";
    foreach ($missing as $token) {
        $r = faceRequest('https://api-us.faceplusplus.com/facepp/v3/faceset/addface', [
            'api_key'     => $api_key,
            'api_secret'  => $api_secret,
            'outer_id'    => 'school_attendance',
            'face_tokens' => $token
        ]);
        $status = isset($r['faceset_token']) ? "OK added" : ("Failed: " . ($r['error_message'] ?? json_encode($r)));
        echo "  Token " . substr($token, 0, 12) . "... → {$status}\n";
    }
}

// ── STEP 5: Clean up any NULL/orphaned face tokens left in DB ────────────────
echo "\n=== STEP 5: Clean up NULL face tokens in database ===\n";
// Use a subquery via a derived table to avoid MySQL's "can't update table referenced in FROM" error
$nullCount = $conn->exec(
    "UPDATE students s
     JOIN (SELECT face_token FROM student_faces) sf ON s.face_token = sf.face_token
     SET s.face_token = NULL
     WHERE s.face_token IS NOT NULL
       AND s.face_token NOT IN (SELECT face_token FROM student_faces sf2)"
);
// Simpler fallback: just null out tokens that have no matching student_faces row
if ($nullCount === false) {
    $validTokens = $conn->query("SELECT face_token FROM student_faces")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($validTokens)) {
        $nullCount = $conn->exec("UPDATE students SET face_token = NULL WHERE face_token IS NOT NULL");
    } else {
        $placeholders = implode(',', array_fill(0, count($validTokens), '?'));
        $stmt = $conn->prepare("UPDATE students SET face_token = NULL WHERE face_token IS NOT NULL AND face_token NOT IN ($placeholders)");
        $stmt->execute($validTokens);
        $nullCount = $stmt->rowCount();
    }
}
echo "Cleaned {$nullCount} stale face_token reference(s) from students table\n";

echo "\n=== DONE ===\n";
echo "Face++ faceset is now in sync with your database.\n";
echo "Active students: " . count($dbTokens) . " | Orphans removed: " . count($orphaned) . " | Missing re-added: " . count($missing) . "\n";
?>
