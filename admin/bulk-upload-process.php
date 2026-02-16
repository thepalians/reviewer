<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$filename = basename($file['name']);
$admin_name = $_SESSION['admin_name'];

// Validate file type
if (!preg_match('/\.csv$/i', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only CSV files are allowed']);
    exit;
}

// Initialize counters
$total_rows = 0;
$success_count = 0;
$error_count = 0;
$errors = [];
$upload_id = null;

try {
    // Create upload history record
    $stmt = $pdo->prepare("
        INSERT INTO bulk_upload_history (filename, status, created_at) 
        VALUES (?, 'processing', NOW())
    ");
    $stmt->execute([$filename]);
    $upload_id = $pdo->lastInsertId();

    // Open and parse CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        throw new Exception('Unable to open CSV file');
    }

    try {
        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new Exception('Invalid CSV format - unable to read headers');
        }

        // Normalize headers (trim and lowercase)
        $headers = array_map(function($h) {
            return strtolower(trim($h));
        }, $headers);

        // Validate required headers
        $required_fields = ['brand_name', 'product_name', 'product_url', 'reward_amount'];
        $optional_fields = ['amazon_link', 'order_id', 'seller_id', 'seller_name', 'reviewer_mobile', 'reviewer_email', 'task_description'];
        
        foreach ($required_fields as $field) {
            if (!in_array($field, $headers)) {
                throw new Exception("Missing required column: $field");
            }
        }

        $row_number = 1; // Start from 1 (data rows)

        // Process each row
        while (($data = fgetcsv($handle)) !== false) {
        $row_number++;
        $total_rows++;

        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }

        // Map data to associative array
        $row = array_combine($headers, $data);

        // Validate and process row
        $validation = validateRow($row, $row_number);
        
        if (!$validation['valid']) {
            $error_count++;
            $errors[] = [
                'row' => $row_number,
                'error' => $validation['error']
            ];
            continue;
        }

        // Find or create user
        $user_id = findOrCreateUser($pdo, $row);
        
        if (!$user_id) {
            $error_count++;
            $errors[] = [
                'row' => $row_number,
                'error' => 'Could not find or create user with provided email/mobile'
            ];
            continue;
        }

        // Insert task
        try {
            $pdo->beginTransaction();

            $brand_name = trim($row['brand_name']);
            $product_name = trim($row['product_name']);
            $product_url = trim($row['product_url']);
            $reward_amount = floatval($row['reward_amount']);
            $amazon_link = isset($row['amazon_link']) ? trim($row['amazon_link']) : null;
            $order_id = isset($row['order_id']) ? trim($row['order_id']) : null;
            $seller_id = isset($row['seller_id']) && !empty($row['seller_id']) ? intval($row['seller_id']) : null;
            $task_description = isset($row['task_description']) ? trim($row['task_description']) : null;

            // Insert task
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    user_id, 
                    product_link, 
                    brand_name, 
                    seller_id,
                    task_status, 
                    commission, 
                    admin_notes,
                    assigned_by,
                    created_at
                ) VALUES (
                    :user_id, 
                    :product_link, 
                    :brand_name,
                    :seller_id,
                    'pending', 
                    :commission,
                    :admin_notes,
                    :assigned_by,
                    NOW()
                )
            ");

            $admin_notes = "Bulk Upload: $product_name";
            if ($task_description) {
                $admin_notes .= " - $task_description";
            }
            if ($order_id) {
                $admin_notes .= " (Order: $order_id)";
            }
            if ($amazon_link) {
                $admin_notes .= " | Amazon: $amazon_link";
            }

            $stmt->execute([
                ':user_id' => $user_id,
                ':product_link' => $product_url,
                ':brand_name' => $brand_name,
                ':seller_id' => $seller_id,
                ':commission' => $reward_amount,
                ':admin_notes' => $admin_notes,
                ':assigned_by' => $admin_name
            ]);

            $task_id = $pdo->lastInsertId();

            // Create task steps
            foreach (TASK_STEPS as $index => $step) {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, step_status, created_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$task_id, $index + 1, $step]);
            }

            $pdo->commit();

            // Send notification to user
            try {
                createNotification(
                    $user_id,
                    'task',
                    '📋 New Task Assigned',
                    "New task assigned: $product_name from $brand_name. Reward: ₹" . number_format($reward_amount, 2),
                    APP_URL . '/user/task-detail.php?task_id=' . $task_id
                );
            } catch (Exception $e) {
                error_log("Notification error: " . $e->getMessage());
            }

            $success_count++;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Task insert error at row $row_number: " . $e->getMessage());
            $error_count++;
            $errors[] = [
                'row' => $row_number,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    } finally {
        // Ensure file handle is always closed
        fclose($handle);
    }

    // Update upload history
    $error_log = !empty($errors) ? json_encode($errors) : null;
    $stmt = $pdo->prepare("
        UPDATE bulk_upload_history 
        SET total_rows = ?, 
            success_count = ?, 
            error_count = ?, 
            status = 'completed',
            error_log = ?,
            completed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$total_rows, $success_count, $error_count, $error_log, $upload_id]);

    // Return success response
    echo json_encode([
        'success' => true,
        'total_rows' => $total_rows,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    error_log("Bulk upload error: " . $e->getMessage());
    
    // Update upload history as failed
    if ($upload_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE bulk_upload_history 
                SET status = 'failed', 
                    error_log = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([json_encode(['error' => $e->getMessage()]), $upload_id]);
        } catch (PDOException $e2) {
            error_log("Failed to update upload history: " . $e2->getMessage());
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Validate a CSV row
 */
function validateRow(array $row, int $row_number): array {
    // Check required fields
    $required_fields = ['brand_name', 'product_name', 'product_url', 'reward_amount'];
    
    foreach ($required_fields as $field) {
        if (!isset($row[$field]) || trim($row[$field]) === '') {
            return [
                'valid' => false,
                'error' => "Missing required field: $field"
            ];
        }
    }

    // Validate reward_amount is numeric
    if (!is_numeric($row['reward_amount']) || floatval($row['reward_amount']) <= 0) {
        return [
            'valid' => false,
            'error' => 'reward_amount must be a positive number'
        ];
    }

    // Validate product_url format
    $product_url = trim($row['product_url']);
    if (!filter_var($product_url, FILTER_VALIDATE_URL)) {
        return [
            'valid' => false,
            'error' => 'Invalid product_url format'
        ];
    }

    // Validate amazon_link if provided
    if (isset($row['amazon_link']) && !empty(trim($row['amazon_link']))) {
        $amazon_link = trim($row['amazon_link']);
        if (!filter_var($amazon_link, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'error' => 'Invalid amazon_link format'
            ];
        }
    }

    // Must have either reviewer_email or reviewer_mobile
    $has_email = isset($row['reviewer_email']) && !empty(trim($row['reviewer_email']));
    $has_mobile = isset($row['reviewer_mobile']) && !empty(trim($row['reviewer_mobile']));

    if (!$has_email && !$has_mobile) {
        return [
            'valid' => false,
            'error' => 'Either reviewer_email or reviewer_mobile is required'
        ];
    }

    // Validate email format if provided
    if ($has_email) {
        $email = trim($row['reviewer_email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Invalid email format'
            ];
        }
    }

    // Validate mobile format if provided (10 digits)
    if ($has_mobile) {
        $mobile = trim($row['reviewer_mobile']);
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            return [
                'valid' => false,
                'error' => 'Invalid mobile number (must be 10 digits)'
            ];
        }
    }

    // Validate seller_id if provided
    if (isset($row['seller_id']) && !empty(trim($row['seller_id']))) {
        if (!is_numeric($row['seller_id']) || intval($row['seller_id']) <= 0) {
            return [
                'valid' => false,
                'error' => 'seller_id must be a positive integer'
            ];
        }
    }

    return ['valid' => true];
}

/**
 * Find or create user based on email/mobile
 */
function findOrCreateUser(PDO $pdo, array $row): ?int {
    $email = isset($row['reviewer_email']) ? trim($row['reviewer_email']) : null;
    $mobile = isset($row['reviewer_mobile']) ? trim($row['reviewer_mobile']) : null;

    // Try to find existing user by email or mobile
    $where_clauses = [];
    $params = [];

    if (!empty($email)) {
        $where_clauses[] = "email = ?";
        $params[] = $email;
    }

    if (!empty($mobile)) {
        $where_clauses[] = "mobile = ?";
        $params[] = $mobile;
    }

    if (empty($where_clauses)) {
        return null;
    }

    $where_sql = implode(' OR ', $where_clauses);
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE ($where_sql) AND user_type = 'user' AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute($params);
        $user = $stmt->fetch();

        if ($user) {
            return (int)$user['id'];
        }

        // User not found - we don't auto-create users for security reasons
        // If auto-creation is needed, uncomment the code below and ensure:
        // 1. Both email AND mobile are provided
        // 2. A secure mechanism to send credentials to users is implemented
        // 3. Users are notified via email/SMS with their login details
        
        return null;

    } catch (PDOException $e) {
        error_log("Find/create user error: " . $e->getMessage());
        return null;
    }
}
