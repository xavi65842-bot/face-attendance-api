<?php
// Attendance Statistics API
// Location: C:\xampp\htdocs\face-attendance-api\api\stats.php

require_once 'config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method allowed', null, 405);
}

$db = getDB();

try {
    // Get today's attendance count
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ?");
    $stmt->execute([$today]);
    $today_count    = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total students count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM students");
    $stmt->execute();
    $total_students = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get weekly attendance trend
    $stmt = $db->prepare("
        SELECT date, COUNT(*) as count 
        FROM attendance 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY date 
        ORDER BY date DESC
    ");
    $stmt->execute();
    $weekly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, 'Statistics retrieved', [
        'today' => [
            'date' => $today,
            'present' => (int)$today_count,
            'total_students' => (int)$total_students,
            'percentage' => $total_students > 0 ? round(($today_count / $total_students) * 100, 2) : 0
        ],
        'weekly_trend' => $weekly_trend
    ]);
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
}
?>