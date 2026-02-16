<?php
/**
 * Admin Data Export - Version 2.0
 * Export review data to Excel with brand and date filtering
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Get brands for dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT brand_name FROM review_requests WHERE brand_name IS NOT NULL AND brand_name != '' ORDER BY brand_name");
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $brands = [];
    error_log('Failed to fetch brands: ' . $e->getMessage());
}

// Handle export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $brand = $_POST['brand'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    
    if (empty($brand)) {
        $errors[] = 'Please select a brand';
    }
    
    if (empty($errors)) {
        try {
            // Build query
            $sql = "
                SELECT 
                    rr.id as request_id,
                    rr.brand_name,
                    rr.product_name,
                    rr.product_link,
                    rr.platform,
                    rr.reviews_needed,
                    rr.reviews_completed,
                    rr.admin_commission,
                    rr.grand_total,
                    rr.created_at as request_date,
                    s.name as seller_name,
                    s.company_name,
                    s.email as seller_email,
                    t.id as task_id,
                    t.created_at as task_assigned_date,
                    t.task_status,
                    u.name as reviewer_name,
                    u.email as reviewer_email,
                    u.mobile as reviewer_mobile,
                    o.order_number,
                    o.order_date,
                    o.order_amount,
                    o.review_text,
                    o.review_rating,
                    o.created_at as review_submitted_date
                FROM review_requests rr
                LEFT JOIN sellers s ON rr.seller_id = s.id
                LEFT JOIN tasks t ON t.review_request_id = rr.id
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN orders o ON t.id = o.task_id
                WHERE rr.brand_name = ?
            ";
            
            $params = [$brand];
            
            if (!empty($date_from)) {
                $sql .= " AND DATE(rr.created_at) >= ?";
                $params[] = $date_from;
            }
            
            if (!empty($date_to)) {
                $sql .= " AND DATE(rr.created_at) <= ?";
                $params[] = $date_to;
            }
            
            $sql .= " ORDER BY rr.created_at DESC, t.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            if (empty($data)) {
                $errors[] = 'No data found for the selected criteria';
            } else {
                // Generate CSV
                $filename = 'export_' . preg_replace('/[^a-z0-9]/i', '_', $brand) . '_' . date('Y-m-d_His') . '.csv';
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                $output = fopen('php://output', 'w');
                
                // UTF-8 BOM for Excel
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Headers
                fputcsv($output, [
                    'Request ID',
                    'Brand Name',
                    'Product Name',
                    'Product Link',
                    'Platform',
                    'Reviews Needed',
                    'Reviews Completed',
                    'Commission per Review',
                    'Total Amount',
                    'Request Date',
                    'Seller Name',
                    'Company Name',
                    'Seller Email',
                    'Task ID',
                    'Task Assigned Date',
                    'Task Status',
                    'Reviewer Name',
                    'Reviewer Email',
                    'Reviewer Mobile',
                    'Order Number',
                    'Order Date',
                    'Order Amount',
                    'Review Text',
                    'Review Rating',
                    'Review Submitted Date'
                ]);
                
                // Data rows
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['request_id'],
                        $row['brand_name'],
                        $row['product_name'],
                        $row['product_link'],
                        $row['platform'],
                        $row['reviews_needed'],
                        $row['reviews_completed'],
                        $row['admin_commission'],
                        $row['grand_total'],
                        $row['request_date'],
                        $row['seller_name'],
                        $row['company_name'],
                        $row['seller_email'],
                        $row['task_id'],
                        $row['task_assigned_date'],
                        $row['task_status'],
                        $row['reviewer_name'],
                        $row['reviewer_email'],
                        $row['reviewer_mobile'],
                        $row['order_number'],
                        $row['order_date'],
                        $row['order_amount'],
                        $row['review_text'],
                        $row['review_rating'],
                        $row['review_submitted_date']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
        } catch (PDOException $e) {
            error_log('Export error: ' . $e->getMessage());
            $errors[] = 'Failed to export data. Please try again.';
        }
    }
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
    $pending_wallet_recharges = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $pending_wallet_recharges = 0;
}

$current_page = 'export-data';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .export-form {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .export-icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Export Data</li>
                    </ol>
                </nav>
                <h3 class="page-title">Export Review Data</h3>
                <p class="text-muted">Export review data by brand and date range to Excel/CSV format</p>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="export-form">
                    <div class="text-center">
                        <i class="bi bi-file-earmark-spreadsheet export-icon"></i>
                        <h4 class="mb-4">Select Export Criteria</h4>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="brand">
                                <i class="bi bi-tags"></i> Brand Name *
                            </label>
                            <select name="brand" id="brand" class="form-control" required>
                                <option value="">-- Select Brand --</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="date_from">
                                <i class="bi bi-calendar"></i> From Date
                            </label>
                            <input type="date" name="date_from" id="date_from" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="date_to">
                                <i class="bi bi-calendar"></i> To Date
                            </label>
                            <input type="date" name="date_to" id="date_to" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="export" class="btn btn-primary" style="width: 100%; padding: 14px;">
                                <i class="bi bi-download"></i> Export to CSV
                            </button>
                        </div>
                        
                        <div class="text-center text-muted" style="font-size: 12px;">
                            <i class="bi bi-info-circle"></i> 
                            Export includes all review data, tasks, and reviewer information for the selected brand
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
