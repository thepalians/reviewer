<?php
require_once 'config.php';

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // User Registration
    public function register($name, $email, $mobile, $password) {
        // Validation
        if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            return ['success' => false, 'message' => 'Invalid mobile number'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        try {
            // Check if email or mobile already exists
            $checkQuery = "SELECT id FROM users WHERE email = :email OR mobile = :mobile";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([':email' => $email, ':mobile' => $mobile]);
            
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email or Mobile already registered'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (name, email, mobile, password) 
                     VALUES (:name, :email, :mobile, :password)";
            $stmt = $this->db->prepare($query);
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':mobile' => $mobile,
                ':password' => $hashedPassword
            ]);
            
            return ['success' => true, 'message' => 'Registration successful! Please login.'];
            
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // User Login
    public function login($username, $password) {
        try {
            // Check if username is email or mobile
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $query = "SELECT * FROM users WHERE email = :username AND status = 'active'";
            } else {
                $query = "SELECT * FROM users WHERE mobile = :username AND status = 'active'";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([':id' => $user['id']]);
                
                return ['success' => true, 'user_type' => $user['user_type']];
            } else {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Admin Login
    public function adminLogin($username, $password) {
        // Load admin credentials from environment
        $admin_username = env('ADMIN_EMAIL', 'admin@reviewflow.com');
        $admin_password = env('ADMIN_PASSWORD', '');
        
        // Support both plain text and hashed passwords
        $isValidPassword = false;
        if (strpos($admin_password, '$2y$') === 0) {
            $isValidPassword = password_verify($password, $admin_password);
        } else {
            $isValidPassword = ($password === $admin_password);
            if ($isValidPassword) {
                error_log("WARNING: Admin password is not hashed in .env file");
            }
        }
        
        if ($username === $admin_username && $isValidPassword) {
            // Create or update admin user in database
            $query = "SELECT * FROM users WHERE email = :email AND user_type = 'admin'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':email' => $username . '@admin.com']);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                // Create admin user if not exists
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertQuery = "INSERT INTO users (name, email, mobile, password, user_type) 
                               VALUES (:name, :email, :mobile, :password, 'admin')";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->execute([
                    ':name' => 'Admin',
                    ':email' => $username . '@admin.com',
                    ':mobile' => '0000000000',
                    ':password' => $hashedPassword
                ]);
                
                $admin_id = $this->db->lastInsertId();
            } else {
                $admin_id = $admin['id'];
            }
            
            // Set admin session
            $_SESSION['user_id'] = $admin_id;
            $_SESSION['user_name'] = 'Admin';
            $_SESSION['user_email'] = $username . '@admin.com';
            $_SESSION['user_type'] = 'admin';
            
            return ['success' => true, 'user_type' => 'admin'];
        } else {
            return ['success' => false, 'message' => 'Invalid admin credentials'];
        }
    }
    
    // Logout
    public static function logout() {
        session_destroy();
        redirect(APP_URL . '/index.php');
    }
}
?>
