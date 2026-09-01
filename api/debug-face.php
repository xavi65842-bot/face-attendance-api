<?php
/**
 * debug-face.php — diagnoses exactly WHY face detection is failing
 * Open in browser: http://localhost/face-attendance-api/api/debug-face.php
 *
 * REMOVE THIS FILE before going to production.
 */
ob_start(); // buffer ALL output so AJAX handler can call ob_clean() cleanly

error_reporting(E_ALL);
ini_set('display_errors', 0); // keep errors out of JSON responses
ini_set('log_errors', 1);

// ── AJAX handler — must run BEFORE any HTML is sent ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';
    $input  = json_decode(file_get_contents('php://input'), true);
    $image  = $input['image'] ?? '';

    if (!$image) {
        echo json_encode(['success' => false, 'error' => 'No image received']);
        exit();
    }

    // Strip data URI prefix
    if (strpos($image, 'base64,') !== false) {
        $image = explode('base64,', $image)[1];
    }

    // Validate base64
    $decoded = base64_decode($image, true);
    if (!$decoded || strlen($decoded) < 500) {
        echo json_encode([
            'success'   => false,
            'error'     => 'Image too small or invalid (' . strlen($decoded ?: '') . ' bytes). Camera may not have started yet.',
            'http_code' => 0,
            'raw'       => []
        ]);
        exit();
    }

    require_once '../includes/FacePlusPlus.php';
    $faceApi    = new FacePlusPlus();
    $ref        = new ReflectionClass($faceApi);
    $keyProp    = $ref->getProperty('api_key');    $keyProp->setAccessible(true);
    $secretProp = $ref->getProperty('api_secret'); $secretProp->setAccessible(true);
    $urlProp    = $ref->getProperty('api_url');    $urlProp->setAccessible(true);
    $api_key    = $keyProp->getValue($faceApi);
    $api_secret = $secretProp->getValue($faceApi);
    $api_url    = $urlProp->getValue($faceApi);

    // Raw detect call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . 'detect');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'api_key'           => $api_key,
        'api_secret'        => $api_secret,
        'image_base64'      => $image,
        'return_attributes' => 'gender,age'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlErr, 'http_code' => 0, 'raw' => []]);
        exit();
    }

    $raw = json_decode($resp, true) ?? ['raw_text' => substr($resp, 0, 500)];

    if (isset($raw['faces']) && count($raw['faces']) > 0) {
        $face = $raw['faces'][0];
        echo json_encode([
            'success'    => true,
            'face_token' => $face['face_token'],
            'attributes' => $face['attributes'] ?? [],
            'face_count' => count($raw['faces']),
            'http_code'  => $httpCode,
            'raw'        => $raw
        ]);
    } else {
        echo json_encode([
            'success'   => false,
            'error'     => $raw['error_message'] ?? 'No face in response',
            'http_code' => $httpCode,
            'raw'       => $raw
        ]);
    }
    exit();
}

// ── HTML page starts here (GET requests only) ─────────────────────────────────
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html>
<head>
<title>Face Detection Debugger</title>
<style>
  body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 20px; }
  h2   { color: #00d4ff; }
  .ok  { color: #00ff88; }
  .err { color: #ff4444; }
  .warn{ color: #ffaa00; }
  .box { background: #16213e; border: 1px solid #0f3460; padding: 15px;
         border-radius: 8px; margin: 10px 0; white-space: pre-wrap; }
  button { background: #0f3460; color: #fff; border: none; padding: 10px 20px;
           border-radius: 6px; cursor: pointer; font-size: 14px; margin: 5px; }
  button:hover { background: #00d4ff; color: #000; }
  video, canvas { border: 2px solid #0f3460; border-radius: 8px; }
  #result { margin-top: 20px; }
</style>
</head>
<body>

<h2>🔍 Face Detection Debugger</h2>

<?php
// ── SERVER-SIDE CHECKS ────────────────────────────────────────────────────────
echo "<h3>1. Server Environment Checks</h3><div class='box'>";

// PHP version
$phpOk = version_compare(PHP_VERSION, '7.4', '>=');
echo ($phpOk ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>")
   . " PHP Version: " . PHP_VERSION . "\n";

// cURL
$curlOk = function_exists('curl_init');
echo ($curlOk ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>")
   . " cURL extension: " . ($curlOk ? "loaded" : "MISSING — Face++ calls will fail") . "\n";

// GD
$gdOk = function_exists('imagecreatetruecolor');
echo ($gdOk ? "<span class='ok'>✅</span>" : "<span class='warn'>⚠️</span>")
   . " GD extension: " . ($gdOk ? "loaded" : "missing (placeholder photos won't work)") . "\n";

// uploads writable
$uploadsDir = __DIR__ . '/../uploads/';
$uploadOk   = is_dir($uploadsDir) && is_writable($uploadsDir);
echo ($uploadOk ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>")
   . " uploads/ folder: " . ($uploadOk ? "exists and writable" : "MISSING or NOT WRITABLE") . "\n";

echo "</div>";

// ── FACE++ CONNECTIVITY TEST ──────────────────────────────────────────────────
echo "<h3>2. Face++ API Connectivity</h3><div class='box'>";

require_once '../includes/FacePlusPlus.php';

// Use reflection to read private keys
$faceApi    = new FacePlusPlus();
$ref        = new ReflectionClass($faceApi);
$keyProp    = $ref->getProperty('api_key');    $keyProp->setAccessible(true);
$secretProp = $ref->getProperty('api_secret'); $secretProp->setAccessible(true);
$urlProp    = $ref->getProperty('api_url');    $urlProp->setAccessible(true);
$api_key    = $keyProp->getValue($faceApi);
$api_secret = $secretProp->getValue($faceApi);
$api_url    = $urlProp->getValue($faceApi);

echo "API Key    : " . substr($api_key, 0, 8) . "..." . substr($api_key, -4) . "\n";
echo "API Secret : " . substr($api_secret, 0, 4) . "****\n";
echo "Endpoint   : $api_url\n\n";

// Ping Face++ with a tiny test — get faceset detail (no image needed)
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
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo "<span class='err'>❌ Network error: $curlErr</span>\n";
    echo "<span class='warn'>→ Check your internet connection or firewall</span>\n";
} elseif ($httpCode === 0) {
    echo "<span class='err'>❌ No response (HTTP 0) — possible SSL or firewall block</span>\n";
} else {
    $data = json_decode($resp, true);
    echo "HTTP Status: $httpCode\n";
    if (isset($data['error_message'])) {
        $err = $data['error_message'];
        if (stripos($err, 'AUTHENTICATION') !== false || stripos($err, 'INVALID_API') !== false) {
            echo "<span class='err'>❌ API KEY ERROR: $err</span>\n";
            echo "<span class='warn'>→ Your API key or secret is wrong/expired</span>\n";
            echo "<span class='warn'>→ Login to faceplusplus.com and check your keys</span>\n";
        } elseif (stripos($err, 'FACESET_NOT_EXIST') !== false) {
            echo "<span class='warn'>⚠️  Faceset 'school_attendance' does not exist yet</span>\n";
            echo "<span class='ok'>→ It will be created automatically on first registration</span>\n";
        } else {
            echo "<span class='warn'>⚠️  Face++ response: $err</span>\n";
        }
    } else {
        $faceCount = count($data['face_tokens'] ?? []);
        echo "<span class='ok'>✅ Face++ API is reachable and keys are valid</span>\n";
        echo "   Faceset 'school_attendance' has <b>$faceCount</b> face(s) registered\n";
    }
}
echo "</div>";

// ── DATABASE CHECK ────────────────────────────────────────────────────────────
echo "<h3>3. Database Check</h3><div class='box'>";
try {
    require_once '../includes/Database.php';
    $db   = new Database();
    $conn = $db->getConnection();
    echo "<span class='ok'>✅ Database connected</span>\n";

    $studentCount = (int)$conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $faceCount    = (int)$conn->query("SELECT COUNT(*) FROM student_faces")->fetchColumn();
    echo "   Students in DB      : $studentCount\n";
    echo "   student_faces rows  : $faceCount\n";

    if ($studentCount === 0) {
        echo "<span class='warn'>⚠️  No students registered — register a student first before scanning</span>\n";
    }

    $activeSessions = (int)$conn->query("SELECT COUNT(*) FROM attendance_sessions WHERE is_active = 1")->fetchColumn();
    echo "   Active sessions     : $activeSessions\n";
    if ($activeSessions === 0) {
        echo "<span class='warn'>⚠️  No active session — lecturer must start a session before attendance can be marked</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='err'>❌ DB error: " . $e->getMessage() . "</span>\n";
}
echo "</div>";
?>

<!-- ── LIVE CAMERA TEST ──────────────────────────────────────────────────── -->
<h3>4. Live Camera → Face++ Detection Test</h3>
<p style="color:#aaa">This captures a frame from your webcam and sends it directly to Face++ detect. 
You will see the raw response so you know exactly what's happening.</p>

<video id="video" width="320" height="240" autoplay playsinline></video>
<canvas id="canvas" width="320" height="240" style="display:none"></canvas>
<br><br>
<button onclick="startCamera()">📷 Start Camera</button>
<button onclick="testDetect()" id="btnTest" disabled>🔍 Test Face Detection</button>
<button onclick="testRegisterFlow()" id="btnReg" disabled>📝 Test Full Register Flow</button>

<div id="result"></div>

<script>
let stream = null;

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: 640, height: 480, facingMode: 'user' } 
        });
        document.getElementById('video').srcObject = stream;
        document.getElementById('btnTest').disabled = false;
        document.getElementById('btnReg').disabled  = false;
        log('✅ Camera started', 'ok');
    } catch(e) {
        log('❌ Camera error: ' + e.message + '\n→ Allow camera permission in your browser', 'err');
    }
}

function captureFrame() {
    const video  = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    // Return full data URI — the PHP strips the prefix
    return canvas.toDataURL('image/jpeg', 0.92);
}

async function testDetect() {
    log('⏳ Capturing frame and sending to Face++ detect...', 'warn');
    const image = captureFrame();
    
    try {
        const res  = await fetch('debug-face.php?action=detect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image })
        });
        const data = await res.json();
        
        if (data.success) {
            log('✅ FACE DETECTED!\n' +
                '   Face token : ' + data.face_token + '\n' +
                '   Raw response:\n' + JSON.stringify(data.raw, null, 2), 'ok');
        } else {
            log('❌ FACE NOT DETECTED\n' +
                '   Error      : ' + data.error + '\n' +
                '   HTTP code  : ' + data.http_code + '\n' +
                '   Raw response:\n' + JSON.stringify(data.raw, null, 2) + '\n\n' +
                diagnose(data), 'err');
        }
    } catch(e) {
        log('❌ Request failed: ' + e.message, 'err');
    }
}

async function testRegisterFlow() {
    log('⏳ Testing full register flow (no DB write)...', 'warn');
    const image = captureFrame();
    
    try {
        const res  = await fetch('debug-face.php?action=fulltest', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image })
        });
        const data = await res.json();
        log(JSON.stringify(data, null, 2), data.detect_success ? 'ok' : 'err');
    } catch(e) {
        log('❌ Request failed: ' + e.message, 'err');
    }
}

function diagnose(data) {
    const raw = data.raw || {};
    const err = (raw.error_message || '').toUpperCase();
    
    if (err.includes('AUTHENTICATION') || err.includes('INVALID_API')) {
        return '💡 FIX: Your API key or secret is wrong.\n   → Login to faceplusplus.com → Apps → copy your key/secret\n   → Update includes/FacePlusPlus.php';
    }
    if (err.includes('IMAGE_ERROR') || err.includes('INVALID_IMAGE')) {
        return '💡 FIX: Image format issue.\n   → Make sure camera is on and frame is not black\n   → Try better lighting';
    }
    if (err.includes('FACE_NOT_FOUND') || data.error === 'No face detected') {
        return '💡 FIX: Face++ could not find a face in the image.\n   → Move closer to camera (face should fill ~30% of frame)\n   → Improve lighting — avoid backlight\n   → Look directly at camera\n   → Avoid glasses glare or extreme angles';
    }
    if (err.includes('QUOTA') || err.includes('LIMIT')) {
        return '💡 FIX: Face++ API quota exceeded.\n   → Login to faceplusplus.com and check your usage/billing';
    }
    if (data.http_code === 0 || err.includes('NETWORK')) {
        return '💡 FIX: No internet connection or Face++ is blocked.\n   → Check your internet\n   → Check Windows Firewall / antivirus is not blocking PHP/Apache';
    }
    return '💡 Check the raw response above for clues.';
}

function log(msg, type) {
    const div = document.getElementById('result');
    div.innerHTML += `<div class="box ${type}">${msg}</div>`;
}
</script>

</body>
</html>
