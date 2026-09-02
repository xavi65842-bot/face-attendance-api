<?php
// Database Connection Class
// Location: C:\xampp\htdocs\face-attendance-api\includes\Database.php

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Load .env if present
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($k, $v) = array_map('trim', explode('=', $line, 2));
                $v = trim($v, '"\'');
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }

        // Support Railway, Render, Docker and local XAMPP
        $this->host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
        $this->port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: '3306';
        $this->db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'attendance_system';
        $this->username = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
        $this->password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';

        // If MYSQL_URL or DATABASE_URL is set (standard Railway connection string)
        $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            $parsed = parse_url($url);
            if ($parsed) {
                if (isset($parsed['host'])) $this->host = $parsed['host'];
                if (isset($parsed['port'])) $this->port = $parsed['port'];
                if (isset($parsed['user'])) $this->username = $parsed['user'];
                if (isset($parsed['pass'])) $this->password = $parsed['pass'];
                if (isset($parsed['path'])) $this->db_name = ltrim($parsed['path'], '/');
            }
        }
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $exception) {
            error_log('Connection error: ' . $exception->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $exception->getMessage()]);
            exit();
        }
        
        return $this->conn;
    }
}
?>