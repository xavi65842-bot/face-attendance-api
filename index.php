<?php
/**
 * Face Attendance System - Homepage
 * URL: http://localhost/face-attendance-api/
 */
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Attendance System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #333; text-align: center; }
        .container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .card p {
            color: #666;
            line-height: 1.5;
        }
        .api { border-left: 4px solid #3498db; }
        .test { border-left: 4px solid #2ecc71; }
        .db { border-left: 4px solid #e74c3c; }
        .diagnostic { border-left: 4px solid #f39c12; }
        .models { border-left: 4px solid #9b59b6; }
        .uploads { border-left: 4px solid #1abc9c; }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header p {
            color: #666;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👨‍🎓 Face Recognition Attendance System</h1>
        <p>PHP-based attendance system with Face++ API integration</p>
    </div>
    
    <div class="container">
        <a href="test-api.html" class="card test">
            <h3>📡 API Test Panel</h3>
            <p>Comprehensive test interface for all API endpoints including student registration, face recognition, and attendance tracking.</p>
        </a>
        
        <a href="api/check-quota.php" class="card diagnostic">
            <h3>🔑 API Key Status</h3>
            <p>Check your Face++ API key status, quota usage, and troubleshoot API connection issues.</p>
        </a>
        
        <a href="db.php" class="card db">
            <h3>🗄️ Database Viewer</h3>
            <p>View all students and attendance records in the database. Check if data is being stored correctly.</p>
        </a>
        
        <a href="diagnose.php" class="card diagnostic">
            <h3>🔧 System Diagnostics</h3>
            <p>Run system diagnostics to check PHP configuration, file permissions, and Face++ API connectivity.</p>
        </a>
        
        <a href="download-models.html" class="card models">
            <h3>🤖 Face Models</h3>
            <p>Download and manage face recognition models for face-api.js integration.</p>
        </a>
        
        <a href="direct-face-test.php" class="card test">
            <h3>📸 Direct Face Test</h3>
            <p>Test face detection and recognition directly with your webcam or uploaded images.</p>
        </a>
        
        <a href="minimal-test.php" class="card test">
            <h3>⚡ Minimal Test</h3>
            <p>Lightweight test page for quick API endpoint testing without the full interface.</p>
        </a>
        
        <a href="check-faceplus.php" class="card diagnostic">
            <h3>🌐 Face++ Connection Test</h3>
            <p>Test direct connection to Face++ API servers and check network connectivity.</p>
        </a>
    </div>
    
    <div style="margin-top: 40px; text-align: center; color: #666; font-size: 14px;">
        <p>System Status: ✅ API endpoints are working | Project Path: C:\xampp\htdocs\face-attendance-api\</p>
        <p>Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>