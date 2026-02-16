<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_range = $_GET['range'] ?? '30';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$start_date = date('Y-m-d', strtotime("-$date_range days"));
$end_date = date('Y-m-d');

// Build query
$where_conditions = ['seller_id = :seller_id'];
$params = [':seller_id' => $seller_id];

if ($status_filter !== 'all') {
    $where_conditions[] = 'admin_status = :status';
    $params[':status'] = $status_filter;
}

$where_conditions[] = 'created_at BETWEEN :start_date AND :end_date';
$params[':start_date'] = $start_date . ' 00:00:00';
$params[':end_date'] = $end_date . ' 23:59:59';

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM review_requests WHERE $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// Get orders with review tracking
$query = "
    SELECT rr.*,
           (
               SELECT COUNT(DISTINCT t.user_id)
               FROM tasks t
               WHERE t.review_request_id = rr.id
                  OR (t.review_request_id IS NULL AND t.seller_id = rr.seller_id AND t.product_link = rr.product_link)
           ) as assigned_users,
           (
               SELECT COUNT(*)
               FROM tasks t
               LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
               WHERE ts3.step_status = 'completed'
                 AND (
                        t.review_request_id = rr.id
                        OR (t.review_request_id IS NULL AND t.seller_id = rr.seller_id AND t.product_link = rr.product_link)
                 )
           ) as completed_reviews
    FROM review_requests rr
    WHERE $where_clause
    ORDER BY rr.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(reviews_needed) as total_reviews_ordered,
        SUM(
            (
                SELECT COUNT(*)
                FROM tasks t
                LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
                WHERE ts3.step_status = 'completed'
                  AND (
                        t.review_request_id = rr.id
                        OR (t.review_request_id IS NULL AND t.seller_id = rr.seller_id AND t.product_link = rr.product_link)
                  )
            )
        ) as total_reviews_completed,
        SUM(CASE WHEN admin_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN admin_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN admin_status = 'approved' THEN 1 ELSE 0 END) as active_orders
    FROM review_requests rr
    WHERE rr.seller_id = :seller_id 
    AND rr.created_at BETWEEN :start_date AND :end_date
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([
    ':seller_id' => $seller_id,
    ':start_date' => $start_date . ' 00:00:00',
    ':end_date' => $end_date . ' 23:59:59'
]);
$stats = $stmt->fetch();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Review Tracking Dashboard</h3>
            <p class="text-muted">Monitor progress of your review orders</p>
        </div>
        <div class="col-auto">
            <select class="form-select" onchange="window.location.href='?status=<?= $status_filter ?>&range='+this.value">
                <option value="7" <?= $date_range == '7' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30" <?= $date_range == '30' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="90" <?= $date_range == '90' ? 'selected' : '' ?>>Last 90 Days</option>
            </select>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Orders</div>
                        <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Reviews Ordered</div>
                        <h3 class="mb-0"><?= $stats['total_reviews_ordered'] ?? 0 ?></h3>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-star"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Reviews Completed</div>
                        <h3 class="mb-0"><?= $stats['total_reviews_completed'] ?? 0 ?></h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Completion Rate</div>
                        <h3 class="mb-0">
                            <?php 
                            $rate = $stats['total_reviews_ordered'] > 0 
                                ? round(($stats['total_reviews_completed'] / $stats['total_reviews_ordered']) * 100, 1) 
                                : 0;
                            echo $rate . '%';
                            ?>
                        </h3>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" 
                   href="?status=all&range=<?= $date_range ?>">
                    All Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" 
                   href="?status=pending&range=<?= $date_range ?>">
                    Pending (<?= $stats['pending_orders'] ?? 0 ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'approved' ? 'active' : '' ?>" 
                   href="?status=approved&range=<?= $date_range ?>">
                    Active (<?= $stats['active_orders'] ?? 0 ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $status_filter === 'completed' ? 'active' : '' ?>" 
                   href="?status=completed&range=<?= $date_range ?>">
                    Completed (<?= $stats['completed_orders'] ?? 0 ?>)
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Orders Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (count($orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Platform</th>
                            <th>Reviews</th>
                            <th>Progress</th>
                            <th>Assigned Users</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <?php
                        $completed_reviews = isset($order['completed_reviews']) 
                            ? (int)$order['completed_reviews'] 
                            : (int)$order['reviews_completed'];
                        $completion_percentage = $order['reviews_needed'] > 0 
                            ? round(($completed_reviews / $order['reviews_needed']) * 100) 
                            : 0;
                        ?>
                        <tr>
                            <td><strong>#<?= $order['id'] ?></strong></td>
                            <td>
                                <div class="text-truncate" style="max-width: 200px;">
                                    <?= htmlspecialchars($order['product_name']) ?>
                                </div>
                                <?php if ($order['brand_name']): ?>
                                <small class="text-muted"><?= htmlspecialchars($order['brand_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= strtoupper($order['platform']) ?></span></td>
                            <td>
                                <strong><?= $completed_reviews ?></strong> / <?= $order['reviews_needed'] ?>
                            </td>
                            <td>
                                <div class="progress" style="height: 25px; min-width: 100px;">
                                    <div class="progress-bar <?= $completion_percentage == 100 ? 'bg-success' : 'bg-primary' ?>" 
                                         style="width: <?= $completion_percentage ?>%">
                                        <?= $completion_percentage ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <i class="bi bi-people"></i> <?= $order['assigned_users'] ?? 0 ?> users
                            </td>
                            <td>
                                <?php
                                $status_badges = [
                                    'pending' => 'warning',
                                    'approved' => 'primary',
                                    'completed' => 'success',
                                    'rejected' => 'danger'
                                ];
                                $badge_class = $status_badges[$order['admin_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge_class ?>">
                                    <?= ucfirst($order['admin_status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="viewDetails(<?= $order['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center p-3">
                <nav>
                    <ul class="pagination mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?status=<?= $status_filter ?>&range=<?= $date_range ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?status=<?= $status_filter ?>&range=<?= $date_range ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?status=<?= $status_filter ?>&range=<?= $date_range ?>&page=<?= $page + 1 ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                <p class="text-muted mt-3 mb-0">No orders found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function viewDetails(orderId) {
    window.location.href = 'orders.php?id=' + orderId;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
