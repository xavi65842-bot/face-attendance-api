<?php
// Get Attendance Records API
// Location: C:\xampp\htdocs\face-attendance-api\api\attendance.php

require_once 'config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method allowed', null, 405);
}

$db = getDB();

try {
    $date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    
    // Validate date format to prevent unexpected query behaviour
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $date = date('Y-m-d');
    }
    
    $query = "SELECT a.*, s.full_name, s.department 
              FROM attendance a 
              JOIN students s ON a.student_id = s.student_id 
              WHERE a.date = ? 
              ORDER BY a.time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$date]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, 'Attendance records retrieved', [
        'date' => $date,
        'count' => count($attendance),
        'records' => $attendance
    ]);
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
}
?>