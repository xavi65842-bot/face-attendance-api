<?php
// Test API endpoint
// Location: C:\xampp\htdocs\face-attendance-api\api\test.php

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    sendResponse(true, 'Face Attendance API is running!', [
        'version' => '1.0',
        'endpoints' => [
            'POST /api/register.php' => 'Register new student with face',
            'POST /api/recognize.php' => 'Recognize face and mark attendance',
            'GET /api/students.php' => 'Get all students',
            'GET /api/stats.php' => 'Get attendance statistics',
            'GET /api/attendance.php' => 'Get attendance records'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} elseif ($method == 'POST') {
    $data = getPostData();
    sendResponse(true, 'POST request received', [
        'received_data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} else {
    sendResponse(false, 'Method not supported', null, 405);
}
?>