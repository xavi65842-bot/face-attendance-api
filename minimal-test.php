<?php
// Minimal Face++ API Test - WITH WORKING IMAGE
header('Content-Type: text/html');

// ⚠️ PUT YOUR ACTUAL API KEYS HERE
$api_key = 'BUREBFti07x1AG3aRI6L3Ye0EcDP9fc-';      // ← PUT YOUR FULL API KEY
$api_secret = 'SleBH8PS1p2LP8XdPqLM3Mr5SkiaMJlL'; // ← PUT YOUR API SECRET

echo "<h1>Minimal Face++ API Test</h1>";

// Working test image URLs (try different ones)
$testUrls = [
    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400',  // Face photo
    'https://randomuser.me/api/portraits/men/1.jpg',  // Random user face
    'https://thispersondoesnotexist.com/'  // AI generated face
];

$workingUrl = null;
$faceToken = null;

foreach ($testUrls as $url) {
    echo "<h2>Trying URL: " . htmlspecialchars($url) . "</h2>";
    
    $detectData = [
        'api_key' => $api_key,
        'api_secret' => $api_secret,
        'image_url' => $url,
        'return_attributes' => 'gender,age'
    ];
    
    $ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/detect');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($detectData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    echo "<p>HTTP Status: $httpCode</p>";
    
    if (isset($result['faces']) && count($result['faces']) > 0) {
        $faceToken = $result['faces'][0]['face_token'];
        $workingUrl = $url;
        echo "<p style='color:green'>✅ Face detected! Face token: " . substr($faceToken, 0, 20) . "...</p>";
        break;
    } else {
        $error = $result['error_message'] ?? 'No face detected';
        echo "<p style='color:orange'>❌ Failed: $error</p>";
    }
}

if (!$faceToken) {
    die("<h2 style='color:red'>❌ Could not detect face from any test URL. Please check your API keys and internet connection.</h2>");
}

echo "<h2 style='color:green'>✅ Working URL found! Using: " . htmlspecialchars($workingUrl) . "</h2>";
echo "<p>Face Token: $faceToken</p>";

// Create a unique faceset ID
$facesetId = 'test_faceset_' . time();

// Create faceset
echo "<h2>Creating faceset: $facesetId</h2>";
$createData = [
    'api_key' => $api_key,
    'api_secret' => $api_secret,
    'outer_id' => $facesetId,
    'display_name' => 'Test Faceset'
];

$ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/faceset/create');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($createData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$createResult = json_decode($response, true);

echo "<pre>";
print_r($createResult);
echo "</pre>";

if (isset($createResult['error_message'])) {
    die("<h2 style='color:red'>❌ Create faceset error: " . $createResult['error_message'] . "</h2>");
}

// Add face with user_id
$testUserId = 'TEST_USER_' . time();
echo "<h2>Adding face with user_id = $testUserId</h2>";
$addData = [
    'api_key' => $api_key,
    'api_secret' => $api_secret,
    'face_tokens' => $faceToken,
    'outer_id' => $facesetId,
    'user_id' => $testUserId
];

$ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/faceset/addface');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($addData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$addResult = json_decode($response, true);

echo "<pre>";
print_r($addResult);
echo "</pre>";

if (isset($addResult['error_message'])) {
    die("<h2 style='color:red'>❌ Add face error: " . $addResult['error_message'] . "</h2>");
}

// Get faceset details to verify
echo "<h2>Verifying faceset contents...</h2>";
$getData = [
    'api_key' => $api_key,
    'api_secret' => $api_secret,
    'outer_id' => $facesetId
];

$ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/faceset/getdetail');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($getData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$getResult = json_decode($response, true);

echo "<pre>";
print_r($getResult);
echo "</pre>";

// Search for the face
echo "<h2>Searching for the face...</h2>";
$searchData = [
    'api_key' => $api_key,
    'api_secret' => $api_secret,
    'face_token' => $faceToken,
    'outer_id' => $facesetId,
    'return_result_count' => 1
];

$ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/search');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($searchData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$searchResult = json_decode($response, true);

echo "<pre>";
print_r($searchResult);
echo "</pre>";

// FINAL RESULT
echo "<hr>";
echo "<h1>=== FINAL RESULT ===</h1>";

if (isset($searchResult['results'][0]['user_id']) && !empty($searchResult['results'][0]['user_id'])) {
    echo "<h2 style='color:green; font-size:28px;'>✅ SUCCESS! ✅</h2>";
    echo "<h3>user_id = " . $searchResult['results'][0]['user_id'] . "</h3>";
    echo "<h3>Confidence = " . $searchResult['results'][0]['confidence'] . "%</h3>";
    echo "<p style='color:green'>Face++ IS storing the user_id correctly!</p>";
    echo "<p>Your API keys are working properly.</p>";
} else {
    echo "<h2 style='color:red; font-size:28px;'>❌ FAILED! ❌</h2>";
    echo "<p>The user_id is empty in the search result.</p>";
    echo "<p>This suggests a problem with your Face++ account or API version.</p>";
    
    // Check if user_id was in the faceset
    if (isset($getResult['user_ids']) && !empty($getResult['user_ids'])) {
        echo "<p>But the faceset does have user_ids: " . implode(', ', $getResult['user_ids']) . "</p>";
    }
}
?>