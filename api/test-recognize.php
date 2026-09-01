<?php
// Full diagnostic test - open in browser
header("Content-Type: text/html");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/Database.php';
require_once '../includes/FacePlusPlus.php';

$db      = new Database();
$conn    = $db->getConnection();
$faceApi = new FacePlusPlus();

echo "<pre style='font-size:13px;padding:20px;font-family:monospace;'>";
echo "=== FULL SYSTEM DIAGNOSTIC ===\n\n";

// 1. Check DB students
echo "--- STUDENTS IN DB ---\n";
$students = $conn->query("SELECT student_id, full_name, department, semester, face_token FROM students")->fetchAll(PDO::FETCH_ASSOC);
if (empty($students)) {
    echo "❌ NO STUDENTS IN DATABASE\n";
} else {
    foreach ($students as $s) {
        echo "  {$s['full_name']} | {$s['department']} Sem {$s['semester']} | token: " . ($s['face_token'] ?: 'NULL') . "\n";
    }
}

// 2. Check student_faces
echo "\n--- STUDENT_FACES TABLE ---\n";
$faces = $conn->query("SELECT sf.student_id, sf.face_token, s.full_name FROM student_faces sf JOIN students s ON s.student_id = sf.student_id")->fetchAll(PDO::FETCH_ASSOC);
if (empty($faces)) {
    echo "❌ NO ENTRIES IN student_faces TABLE\n";
} else {
    foreach ($faces as $f) {
        echo "  {$f['full_name']} | token: {$f['face_token']}\n";
    }
}

// 3. Check Face++ faceset
echo "\n--- FACE++ FACESET CHECK ---\n";
$ref = new ReflectionClass($faceApi);
$keyProp = $ref->getProperty('api_key'); $keyProp->setAccessible(true);
$secretProp = $ref->getProperty('api_secret'); $secretProp->setAccessible(true);
$urlProp = $ref->getProperty('api_url'); $urlProp->setAccessible(true);
$api_key    = $keyProp->getValue($faceApi);
$api_secret = $secretProp->getValue($faceApi);
$api_url    = $urlProp->getValue($faceApi);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . 'faceset/getdetail');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'api_key'    => $api_key,
    'api_secret' => $api_secret,
    'outer_id'   => 'school_attendance'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);
$faceset = json_decode($response, true);

if (isset($faceset['error_message'])) {
    echo "❌ Faceset error: " . $faceset['error_message'] . "\n";
} else {
    $faceCount = $faceset['face_count'] ?? 0;
    echo "✅ Faceset exists | face_count: {$faceCount}\n";
    if (isset($faceset['face_tokens'])) {
        echo "  Tokens in faceset:\n";
        foreach ($faceset['face_tokens'] as $t) {
            echo "    - {$t}\n";
        }
    }
}

// 4. Cross-check DB tokens vs faceset tokens
echo "\n--- TOKEN CROSS-CHECK (DB vs Face++) ---\n";
$facesetTokens = $faceset['face_tokens'] ?? [];
foreach ($students as $s) {
    if (!$s['face_token']) {
        echo "  ❌ {$s['full_name']} — NO TOKEN IN DB\n";
        continue;
    }
    $inFaceset = in_array($s['face_token'], $facesetTokens);
    $status = $inFaceset ? "✅ IN FACESET" : "❌ NOT IN FACESET";
    echo "  {$s['full_name']} | {$status} | token: {$s['face_token']}\n";
}

// 5. Active sessions
echo "\n--- ACTIVE SESSIONS ---\n";
$sessions = $conn->query("SELECT department, semester, lecturer_name, is_active FROM attendance_sessions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
if (empty($sessions)) {
    echo "❌ NO ACTIVE SESSIONS\n";
} else {
    foreach ($sessions as $s) {
        echo "  ✅ [{$s['department']}] Sem {$s['semester']} | {$s['lecturer_name']}\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
echo "</pre>";
?>
