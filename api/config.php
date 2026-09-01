<?php

// Configuration file for API endpoints
// Location: C:\xampp\htdocs\face-attendance-api\api\config.php

// Error reporting — off in production to prevent leaking into JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Enable CORS (so Next.js can call this API)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type to JSON
header("Content-Type: application/json");

// Include database class
require_once dirname(__DIR__) . '/includes/Database.php';

// Function to send JSON response
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

// Function to get POST data (JSON or form data)
function getPostData() {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $content = trim(file_get_contents("php://input"));
        return json_decode($content, true);
    }
    
    return $_POST;
}

// Function to validate required fields
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), null, 400);
    }
    
    return true;
}

// Function to get database connection
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>