<?php
// Database Connection Class
// Location: C:\xampp\htdocs\face-attendance-api\includes\Database.php

class Database {
    private $host = "localhost";
    private $db_name = "attendance_system";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            // Return JSON error so API callers get a readable response
            header("Content-Type: application/json");
            echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
            exit();
        }
        
        return $this->conn;
    }
}
?>
