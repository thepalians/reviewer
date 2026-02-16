<?php
// Enhanced error handling for dashboard
try {
    require_once '../includes/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Check authentication
    if (!isUser()) {
        redirect(APP_URL . '/index.php');
    }
    
    // Validate user_id exists in session
    if (!isset($_SESSION['user_id'])) {
        error_log('Dashboard accessed without valid user_id in session');
        redirect(APP_URL . '/index.php');
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    // Log user access for debugging (only in debug mode)
    if (DEBUG) {
        error_log("Dashboard accessed by user_id: {$user_id}");
    }
} catch (Exception $e) {
    error_log("Dashboard initialization error: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred while loading the dashboard. Please try again later.');
}

$allowedStatusColumns = ['status', 'task_status'];
$allowedDateColumns = ['assigned_date', 'created_at'];
$pendingStatusValues = ['pending', 'in_progress', 'assigned'];
$taskColumns = null;

if (!isset($_SESSION['dashboard_task_status_column']) || !isset($_SESSION['dashboard_task_assigned_column'])) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tasks");
        $taskColumns = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        error_log("Dashboard column lookup error: " . $e->getMessage());
        $taskColumns = [];
    }
}

$validateTaskColumn = static function (
    string $column,
    string $defaultColumn,
    array $allowedColumns,
    ?array $taskColumns
): string {
    if (!in_array($column, $allowedColumns, true)) {
        return $defaultColumn;
    }
    if ($taskColumns !== null && !isset($taskColumns[$column])) {
        return $defaultColumn;
    }
    return $column;
};

$resolveTaskColumnWithCache = static function (
    string $sessionKey,
    array $allowedColumns,
    string $defaultColumn,
    array $candidateOrder,
    ?array $taskColumns,
    callable $validator
): string {
    $column = $_SESSION[$sessionKey] ?? $defaultColumn;

    $column = $validator($column, $defaultColumn, $allowedColumns, $taskColumns);

    if ($taskColumns !== null) {
        foreach ($candidateOrder as $candidate) {
            if (isset($taskColumns[$candidate])) {
                $column = $candidate;
                break;
            }
        }

        $column = $validator($column, $defaultColumn, $allowedColumns, $taskColumns);
        $_SESSION[$sessionKey] = $column;
    }

    return $column;
};

$taskStatusColumn = $resolveTaskColumnWithCache(
    'dashboard_task_status_column',
    $allowedStatusColumns,
    'status',
    ['task_status', 'status'],
    $taskColumns,
    $validateTaskColumn
);
$taskAssignedColumn = $resolveTaskColumnWithCache(
    'dashboard_task_assigned_column',
    $allowedDateColumns,
    'assigned_date',
    ['assigned_date', 'created_at'],
    $taskColumns,
    $validateTaskColumn
);
$taskQueryTemplates = [
    'status|assigned_date' => "
        SELECT t.*, 
               t.`status` AS status,
               t.`assigned_date` AS assigned_date,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id) as order_count,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id AND step4_status = 'approved') as completed_count
        FROM tasks t
        WHERE t.user_id = :user_id
        ORDER BY t.`assigned_date` DESC
    ",
    'status|created_at' => "
        SELECT t.*, 
               t.`status` AS status,
               t.`created_at` AS assigned_date,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id) as order_count,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id AND step4_status = 'approved') as completed_count
        FROM tasks t
        WHERE t.user_id = :user_id
        ORDER BY t.`created_at` DESC
    ",
    'task_status|assigned_date' => "
        SELECT t.*, 
               t.`task_status` AS status,
               t.`assigned_date` AS assigned_date,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id) as order_count,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id AND step4_status = 'approved') as completed_count
        FROM tasks t
        WHERE t.user_id = :user_id
        ORDER BY t.`assigned_date` DESC
    ",
    'task_status|created_at' => "
        SELECT t.*, 
               t.`task_status` AS status,
               t.`created_at` AS assigned_date,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id) as order_count,
               (SELECT COUNT(*) FROM orders WHERE task_id = t.id AND step4_status = 'approved') as completed_count
        FROM tasks t
        WHERE t.user_id = :user_id
        ORDER BY t.`created_at` DESC
    ",
];
$taskQueryKey = $taskStatusColumn . '|' . $taskAssignedColumn;
if (!isset($taskQueryTemplates[$taskQueryKey])) {
    $taskQueryKey = 'status|assigned_date';
}
// Get user's tasks
try {
    $query = $taskQueryTemplates[$taskQueryKey];

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard tasks query error: " . $e->getMessage());
    $tasks = [];
}

// Get pending orders
try {
    $query = "
        SELECT o.* 
        FROM orders o
        JOIN tasks t ON o.task_id = t.id
        WHERE t.user_id = :user_id 
        AND o.refund_status != 'completed'
        ORDER BY o.submitted_at DESC
        LIMIT 5
    ";

    $orders_stmt = $pdo->prepare($query);
    $orders_stmt->execute([':user_id' => $user_id]);
    $pending_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard pending orders query error: " . $e->getMessage());
    $pending_orders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ReviewFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            position: fixed;
            left: 0;
            top: 60px;
            height: calc(100vh - 60px);
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 999;
        }
        .sidebar-header {
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h2 {
            color: #fff;
            font-size: 18px;
            margin: 0;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .sidebar-menu li a {
            display: block;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            padding-left: 25px;
        }
        .sidebar-menu li a.active {
            background: linear-gradient(90deg, rgba(66,153,225,0.2) 0%, transparent 100%);
            color: #4299e1;
            border-left: 3px solid #4299e1;
        }
        .sidebar-menu li a.logout {
            color: #fc8181;
        }
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 10px 0;
        }
        .menu-section-label {
            padding: 15px 20px 5px;
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge {
            background: #e53e3e;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 8px;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="light-mode">
    <?php 
    include '../includes/header.php'; 
    
    // Set current page for sidebar
    $current_page = 'dashboard';
    
    // Include unified sidebar
    require_once __DIR__ . '/includes/sidebar.php';
    ?>
    
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-tachometer-alt"></i> User Dashboard</h2>
                <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4361ee;">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($tasks); ?></h3>
                        <p>Total Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php 
                            $pending = 0;
                            foreach($tasks as $task) {
                                $task_status = $task['status'] ?? '';
                                if(in_array($task_status, $pendingStatusValues, true)) {
                                    $pending++;
                                }
                            }
                            echo $pending;
                            ?>
                        </h3>
                        <p>Pending Tasks</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php 
                            $completed = 0;
                            foreach($tasks as $task) {
                                $completed += $task['completed_count'];
                            }
                            echo $completed;
                            ?>
                        </h3>
                        <p>Completed Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($pending_orders); ?></h3>
                        <p>Pending Refunds</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Pending Actions</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Product</th>
                            <th>Current Step</th>
                            <th>Next Action</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                            <td>
                                <?php 
                                if($order['step3_status'] == 'pending' && $order['step2_status'] == 'approved') {
                                    echo 'Step 3 - Review Submitted';
                                } elseif($order['step2_status'] == 'pending' && $order['step1_status'] == 'approved') {
                                    echo 'Step 2 - Delivery Proof';
                                } elseif($order['step1_status'] == 'pending') {
                                    echo 'Step 1 - Order Details';
                                } elseif($order['step3_status'] == 'approved') {
                                    echo 'Step 4 - Refund Request';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if($order['step3_status'] == 'pending' && $order['step2_status'] == 'approved') {
                                    echo 'Submit Review Screenshot';
                                } elseif($order['step2_status'] == 'pending' && $order['step1_status'] == 'approved') {
                                    echo 'Submit Delivery Screenshot';
                                } elseif($order['step1_status'] == 'pending') {
                                    echo 'Submit Order Details';
                                } elseif($order['step3_status'] == 'approved') {
                                    echo 'Request Refund';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-pending">Pending</span>
                            </td>
                            <td>
                                <a href="update_order.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-edit"></i> Update
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Available Tasks -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn"></i> Available Tasks</h3>
            </div>
            <?php if(count($tasks) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Product Link</th>
                            <th>Assigned Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tasks as $task): ?>
                        <tr>
                            <td>#<?php echo $task['id']; ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($task['product_link']); ?>" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View Product
                                </a>
                            </td>
                            <td><?php echo date('d M Y', strtotime($task['assigned_date'])); ?></td>
                            <td>
                                <?php 
                                $task_status = $task['status'] ?? 'pending';
                                $status_class = $task_status == 'completed' ? 'status-completed' :
                                              ($task_status == 'in_progress' ? 'status-approved' : 'status-pending');
                                echo '<span class="status-badge ' . $status_class . '">' . ucfirst($task_status) . '</span>';
                                ?>
                            </td>
                            <td>
                                <a href="submit_order.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-plus"></i> Start Order
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No tasks assigned yet. Please wait for admin to assign tasks.
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Version Display -->
    <?php require_once __DIR__ . '/../includes/version-display.php'; ?>
    
    <!-- Include Theme CSS and JS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
    <script src="<?= APP_URL ?>/assets/js/theme.js"></script>
    
    <!-- Include Chatbot Widget -->
    <?php require_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/theme-toggle.js"></script>
    <script src="../assets/js/chatbot.js"></script>
</body>
</html>
