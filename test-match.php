<?php
// Simple Face++ API test - NO GD Library Required
header('Content-Type: text/plain');

// YOUR API KEYS - PUT YOUR REAL KEYS HERE
$api_key = 'BUREBFti07x1AG3aRI6L3Ye0EcDP9fc-';      // ← PUT YOUR FACE++ API KEY
$api_secret = 'SleBH8PS1p2LP8XdPqLM3Mr5SkiaMJlL'; // ← PUT YOUR FACE++ API SECRET

echo "Testing Face++ API with external image...\n\n";

// Use a known working image URL (face image from Face++ demo)
$image_url = 'https://www.faceplusplus.com/static/img/demo/1.jpg';

$url = 'https://api-us.faceplusplus.com/facepp/v3/detect';
$data = [
    'api_key' => $api_key,
    'api_secret' => $api_secret,
    'image_url' => $image_url,
    'return_attributes' => 'gender,age'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n\n";

$result = json_decode($response, true);

if (isset($result['faces']) && count($result['faces']) > 0) {
    echo "✅✅✅ SUCCESS! Face++ API is WORKING! ✅✅✅\n\n";
    echo "Face detected!\n";
    echo "Face token: " . $result['faces'][0]['face_token'] . "\n";
    if (isset($result['faces'][0]['attributes'])) {
        echo "Gender: " . ($result['faces'][0]['attributes']['gender']['value'] ?? 'N/A') . "\n";
        echo "Age: " . ($result['faces'][0]['attributes']['age']['value'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ ERROR: " . ($result['error_message'] ?? 'Unknown error') . "\n";
    echo "\nFull response:\n";
    print_r($result);
}
?>