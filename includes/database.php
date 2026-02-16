<?php
// Load environment variables if not already loaded
if (!function_exists('env')) {
    require_once __DIR__ . '/env-loader.php';
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        $this->host = env('DB_HOST', 'localhost');
        $this->db_name = env('DB_NAME', 'reviewflow');
        $this->username = env('DB_USER', 'reviewflow_user');
        $this->password = env('DB_PASS', '');
    }

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $e) {
            $errorId = uniqid('db_', true);
            error_log("Database Connection Error [$errorId]: " . $e->getMessage());
            echo "Connection Error. Please contact administrator with error ID: $errorId";
        }
        
        return $this->conn;
    }
}
?>
