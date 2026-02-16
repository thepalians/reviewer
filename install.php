<?php
// Database installation script - RUN THIS ONLY ONCE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
require_once __DIR__ . '/includes/env-loader.php';

$host = env('DB_HOST', 'localhost');
$dbname = env('DB_NAME', 'reviewflow');
$username = env('DB_USER', 'reviewflow_user');
$password = env('DB_PASS', '');

try {
    // Create database connection
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE `$dbname`");
    
    // Create users table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) UNIQUE NOT NULL,
            `mobile` VARCHAR(15) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `user_type` ENUM('user', 'admin') DEFAULT 'user',
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `last_login` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Create tasks table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `tasks` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `product_link` TEXT NOT NULL,
            `instructions` TEXT,
            `status` ENUM('pending', 'assigned', 'in_progress', 'completed') DEFAULT 'pending',
            `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_date` TIMESTAMP NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_user_status` (`user_id`, `status`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Create orders table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `task_id` INT NOT NULL,
            `order_date` DATE NOT NULL,
            `order_name` VARCHAR(255) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `order_number` VARCHAR(100) UNIQUE NOT NULL,
            `order_screenshot` TEXT NOT NULL,
            `order_amount` DECIMAL(10,2) NOT NULL,
            `delivered_screenshot` TEXT,
            `review_submitted_screenshot` TEXT,
            `review_live_screenshot` TEXT,
            `refund_status` ENUM('pending', 'requested', 'approved', 'completed') DEFAULT 'pending',
            `payment_screenshot` TEXT,
            `step1_status` ENUM('pending', 'approved') DEFAULT 'pending',
            `step2_status` ENUM('pending', 'approved') DEFAULT 'pending',
            `step3_status` ENUM('pending', 'approved') DEFAULT 'pending',
            `step4_status` ENUM('pending', 'approved') DEFAULT 'pending',
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
            INDEX `idx_task_status` (`task_id`, `refund_status`),
            INDEX `idx_order_number` (`order_number`),
            INDEX `idx_refund_status` (`refund_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Create admin settings table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `admin_settings` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `admin_username` VARCHAR(100) UNIQUE NOT NULL,
            `admin_password` VARCHAR(255) NOT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Insert default admin credentials from environment
    $adminEmail = env('ADMIN_EMAIL', 'admin@reviewflow.com');
    $adminPasswordPlain = env('ADMIN_PASSWORD', 'ChangeMe123!');
    $adminPassword = password_hash($adminPasswordPlain, PASSWORD_DEFAULT);
    
    // Use prepared statement for admin insertion
    $stmt = $conn->prepare("
        INSERT IGNORE INTO `admin_settings` (`admin_username`, `admin_password`) 
        VALUES (?, ?)
    ");
    $stmt->execute([$adminEmail, $adminPassword]);
    
    // Create activity log table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `user_id` INT,
            `action` VARCHAR(255) NOT NULL,
            `details` TEXT,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_action` (`user_id`, `action`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    echo "<h2>Database Installation Successful!</h2>";
    echo "<p>All tables have been created successfully.</p>";
    echo "<p>You can now access the application at: <a href='https://palians.com/reviewer'>https://palians.com/reviewer</a></p>";
    echo "<p>Admin Login: <a href='https://palians.com/reviewer/admin'>https://palians.com/reviewer/admin</a></p>";
    echo "<p><strong>IMPORTANT:</strong> Delete this install.php file after installation.</p>";
    
} catch(PDOException $e) {
    echo "Installation failed: " . $e->getMessage();
}
?>
