<?php
// Get All Students API
// Location: C:\xampp\htdocs\face-attendance-api\api\students.php

require_once 'config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method allowed', null, 405);
}

$db = getDB();

try {
    $query = "SELECT id, student_id, full_name, department, year_intake, semester, registered_at 
              FROM students 
              ORDER BY registered_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, 'Students retrieved successfully', $students);
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
}
?>