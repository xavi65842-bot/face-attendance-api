<?php
require_once 'includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Database connection: SUCCESS\n";
    
    // Test query
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "Test query: SUCCESS\n";
    
    // Check if tables exist
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: " . implode(", ", $tables) . "\n";
    
} catch (Exception $e) {
    echo "Database connection FAILED: " . $e->getMessage() . "\n";
}
?>