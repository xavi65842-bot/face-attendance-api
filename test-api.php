<?php
// Simple test - no database
header("Content-Type: application/json");
echo json_encode([
    'success' => true,
    'message' => 'PHP is working!',
    'time' => date('Y-m-d H:i:s')
]);
?>