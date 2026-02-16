<?php
/**
 * Brand-wise Task Organization - Version 2.0
 * Groups completed tasks by brand with collapsible sections
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

try {
    // Get completed tasks with brand information
    $stmt = $pdo->query("
        SELECT 
            t.id,
            t.created_at,
            t.brand_name,
            t.seller_id,
            t.task_status,
            u.name as user_name,
            u.email,
            s.name as seller_name,
            s.company_name,
            ts1.order_number,
            ts1.order_amount,
            ts4.refund_amount,
            ts4.refund_processed_at,
            ts4.refund_processed_by
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN sellers s ON t.seller_id = s.id
        LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
        LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
        WHERE ts4.step_status = 'completed'
        ORDER BY COALESCE(t.brand_name, 'Unbranded'), ts4.refund_processed_at DESC
    ");
    $tasks = $stmt->fetchAll();
    
    // Group by brand
    $brands = [];
    foreach ($tasks as $task) {
        $brand = $task['brand_name'] ?: 'Unbranded';
        if (!isset($brands[$brand])) {
            $brands[$brand] = [
                'tasks' => [],
                'count' => 0,
                'total_amount' => 0
            ];
        }
        $brands[$brand]['tasks'][] = $task;
        $brands[$brand]['count']++;
        $brands[$brand]['total_amount'] += $task['order_amount'] ?: 0;
    }
    
    // Sort brands alphabetically
    ksort($brands);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $brands = [];
}

$current_page = 'task-completed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tasks (Brand-wise) - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .brand-folder {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .brand-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .brand-header:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8a 100%);
        }
        
        .brand-header.collapsed {
            background: linear-gradient(90deg, #64748b 0%, #475569 100%);
        }
        
        .brand-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .brand-icon {
            font-size: 32px;
        }
        
        .brand-details h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        
        .brand-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .brand-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .brand-toggle i {
            font-size: 20px;
            transition: transform 0.3s;
        }
        
        .brand-header.collapsed .brand-toggle i {
            transform: rotate(-90deg);
        }
        
        .brand-content {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.5s ease-in-out;
        }
        
        .brand-content.collapsed {
            max-height: 0;
        }
        
        .task-list {
            padding: 0;
        }
        
        .task-item {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: grid;
            grid-template-columns: 100px 1fr 150px 150px 120px;
            gap: 20px;
            align-items: center;
            transition: background 0.2s;
        }
        
        .task-item:hover {
            background: #f8fafc;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-id {
            font-weight: 700;
            color: #667eea;
            font-size: 16px;
        }
        
        .task-user {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .user-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .user-email {
            font-size: 12px;
            color: #64748b;
        }
        
        .task-amount {
            font-weight: 700;
            color: #10b981;
            font-size: 16px;
        }
        
        .task-date {
            font-size: 13px;
            color: #64748b;
        }
        
        .task-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-view {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-view:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 968px) {
            .task-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }
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
                        <li class="breadcrumb-item active">Completed Tasks (Brand-wise)</li>
                    </ol>
                </nav>
                <h3 class="page-title">✅ Completed Tasks - Brand Organization</h3>
                <p class="text-muted">Tasks grouped by brand with date sorting (recent first)</p>
            </div>
        </div>
        
        <?php if (empty($brands)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h4>No Completed Tasks</h4>
                        <p>Completed tasks will appear here organized by brand</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($brands as $brand_name => $brand_data): ?>
                <div class="brand-folder">
                    <div class="brand-header" onclick="toggleBrand(this)">
                        <div class="brand-info">
                            <div class="brand-icon">📁</div>
                            <div class="brand-details">
                                <h3><?= htmlspecialchars($brand_name) ?></h3>
                                <div class="brand-stats">
                                    <span><i class="bi bi-check-circle"></i> <?= $brand_data['count'] ?> Tasks</span>
                                    <span><i class="bi bi-currency-rupee"></i> <?= number_format($brand_data['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="brand-toggle">
                            <span>Click to expand</span>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                    </div>
                    
                    <div class="brand-content collapsed">
                        <div class="task-list">
                            <?php foreach ($brand_data['tasks'] as $task): ?>
                                <div class="task-item">
                                    <div class="task-id">#<?= $task['id'] ?></div>
                                    <div class="task-user">
                                        <span class="user-name"><?= htmlspecialchars($task['user_name']) ?></span>
                                        <span class="user-email"><?= htmlspecialchars($task['email']) ?></span>
                                        <?php if ($task['seller_name']): ?>
                                            <span class="user-email">Seller: <?= htmlspecialchars($task['seller_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-amount">₹<?= number_format($task['order_amount'] ?: 0, 2) ?></div>
                                    <div class="task-date">
                                        <?= date('d M Y', strtotime($task['refund_processed_at'])) ?><br>
                                        <small><?= date('h:i A', strtotime($task['refund_processed_at'])) ?></small>
                                    </div>
                                    <div class="task-actions">
                                        <a href="task-detail.php?task_id=<?= $task['id'] ?>" class="btn-view">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleBrand(header) {
    header.classList.toggle('collapsed');
    const content = header.nextElementSibling;
    content.classList.toggle('collapsed');
    
    const toggleText = header.querySelector('.brand-toggle span');
    if (content.classList.contains('collapsed')) {
        toggleText.textContent = 'Click to expand';
    } else {
        toggleText.textContent = 'Click to collapse';
    }
}

// Auto-expand first brand on load
document.addEventListener('DOMContentLoaded', function() {
    const firstBrand = document.querySelector('.brand-header');
    if (firstBrand) {
        toggleBrand(firstBrand);
    }
});
</script>
</body>
</html>
