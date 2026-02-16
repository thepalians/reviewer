<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Get filter
$status_filter = $_GET['status'] ?? 'all';
$selected_order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]) ?: 0;
$seller_id = (int)$seller_id;
const MAX_ENTRY_FILTERS = 200;

// Build query
$where_clause = "WHERE seller_id = ?";
$params = [$seller_id];
$build_entry_link_key = static function (?string $product_link): ?string {
    $normalized_link = trim((string)$product_link);
    if ($normalized_link === '') {
        return null;
    }
    return 'link_' . hash('sha256', $normalized_link);
};
$resolve_entry_keys = static function (array $order) use ($build_entry_link_key): array {
    $keys = ['request_' . $order['id']];
    $link_key = $build_entry_link_key($order['product_link'] ?? null);
    if ($link_key !== null) {
        $keys[] = $link_key;
    }
    return $keys;
};
$determine_entry_status = static function (array $entry): string {
    if (($entry['step4_status'] ?? '') === 'completed') {
        return 'Refund Completed';
    }
    if (($entry['step3_status'] ?? '') === 'completed') {
        return 'Review Submitted';
    }
    if (($entry['step2_status'] ?? '') === 'completed') {
        return 'Delivered';
    }
    if (($entry['step1_status'] ?? '') === 'completed') {
        return 'Order Placed';
    }
    return 'Pending';
};

if ($status_filter !== 'all') {
    $where_clause .= " AND admin_status = ?";
    $params[] = $status_filter;
}

try {
    // Get orders
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
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
            ) as computed_reviews_completed
        FROM review_requests rr
        $where_clause
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    if ($selected_order_id > 0) {
        $order_ids_for_modal = array_map('intval', array_column($orders, 'id'));
        if (!in_array($selected_order_id, $order_ids_for_modal, true)) {
            $selected_order_id = 0;
        }
    }
    
    $entry_tasks = [];
    if (!empty($orders)) {
        $order_ids = array_column($orders, 'id');
        $product_links = array_column($orders, 'product_link');
        $product_links = array_filter($product_links);
        $product_links = array_values(array_unique($product_links));
        $max_entry_filters = MAX_ENTRY_FILTERS;
        if (count($order_ids) > $max_entry_filters) {
            error_log("Seller entry lookup truncated order_ids for seller {$seller_id}");
            $order_ids = array_slice($order_ids, 0, $max_entry_filters);
        }
        if (count($product_links) > $max_entry_filters) {
            error_log("Seller entry lookup truncated product_links for seller {$seller_id}");
            $product_links = array_slice($product_links, 0, $max_entry_filters);
        }
        $entry_conditions = [];
        $entry_params = [];
        
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $entry_conditions[] = "t.review_request_id IN ($placeholders)";
            $entry_params = array_merge($entry_params, $order_ids);
        }
        
        if (!empty($product_links)) {
            $placeholders = implode(',', array_fill(0, count($product_links), '?'));
            $entry_conditions[] = "(t.review_request_id IS NULL AND t.seller_id = ? AND t.product_link IN ($placeholders))";
            $entry_params[] = $seller_id;
            $entry_params = array_merge($entry_params, $product_links);
        }
        
        if (!empty($entry_conditions)) {
            $entry_query = "
                SELECT 
                    t.id,
                    t.review_request_id,
                    t.product_link,
                    t.task_status,
                    t.created_at,
                    u.name as reviewer_name,
                    ts1.order_number,
                    ts1.order_amount,
                    ts1.order_screenshot,
                    ts1.step_status as step1_status,
                    ts2.delivery_screenshot,
                    ts2.step_status as step2_status,
                    ts3.review_screenshot,
                    ts3.step_status as step3_status,
                    ts4.review_live_screenshot,
                    ts4.step_status as step4_status
                FROM tasks t
                JOIN users u ON t.user_id = u.id
                LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
                LEFT JOIN task_steps ts2 ON t.id = ts2.task_id AND ts2.step_number = 2
                LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
                LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
                WHERE (" . implode(' OR ', $entry_conditions) . ")
                ORDER BY t.created_at DESC
            ";
            
            $stmt = $pdo->prepare($entry_query);
            $stmt->execute($entry_params);
            $entry_rows = $stmt->fetchAll();
            
            foreach ($entry_rows as $entry) {
                if (!empty($entry['review_request_id'])) {
                    $entry_key = 'request_' . $entry['review_request_id'];
                    $entry_tasks[$entry_key][] = $entry;
                    continue;
                }
                $entry_key = $build_entry_link_key($entry['product_link']);
                if ($entry_key === null) {
                    continue;
                }
                $entry_tasks[$entry_key][] = $entry;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Orders fetch error: ' . $e->getMessage());
    $orders = [];
    $entry_tasks = [];
}
?>

<div class="container-fluid">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php
            $success_messages = [
                'payment_completed' => 'Payment completed successfully! Your review request is now being processed.',
                'payment_completed_wallet' => 'Payment completed successfully using wallet! Your review request is now being processed.'
            ];
            echo $success_messages[$_GET['success']] ?? 'Operation completed successfully!';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php
            $error_messages = [
                'invalid_session' => 'Invalid session. Please try again.',
                'request_not_found' => 'Review request not found.',
                'invalid_request' => 'Invalid request.'
            ];
            echo isset($error_messages[$_GET['error']]) ? $error_messages[$_GET['error']] : htmlspecialchars($_GET['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Orders</li>
                </ol>
            </nav>
            <h3 class="mb-0">Order History</h3>
            <p class="text-muted">View and track all your review requests</p>
        </div>
        <div class="col-auto">
            <a href="new-request.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Request
            </a>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="card mb-4">
        <div class="card-body">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter === 'all' ? 'active' : '' ?>" 
                       href="?status=all">
                        All Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" 
                       href="?status=pending">
                        <i class="bi bi-clock"></i> Pending
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter === 'approved' ? 'active' : '' ?>" 
                       href="?status=approved">
                        <i class="bi bi-check-circle"></i> Approved
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter === 'completed' ? 'active' : '' ?>" 
                       href="?status=completed">
                        <i class="bi bi-check-all"></i> Completed
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status_filter === 'rejected' ? 'active' : '' ?>" 
                       href="?status=rejected">
                        <i class="bi bi-x-circle"></i> Rejected
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No orders found</p>
                    <a href="new-request.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle"></i> Create New Request
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Product Details</th>
                                <th>Platform</th>
                                <th>Reviews</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $order['id'] ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($order['product_name']) ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($order['brand_name']) ?> 
                                            | ₹<?= number_format($order['product_price'], 2) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= strtoupper($order['platform']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 8px; width: 60px;">
                                                <?php 
                                                $completed_reviews = isset($order['computed_reviews_completed']) 
                                                    ? (int)$order['computed_reviews_completed'] 
                                                    : (int)$order['reviews_completed'];
                                                $progress = $order['reviews_needed'] > 0 
                                                    ? ($completed_reviews / $order['reviews_needed']) * 100 
                                                    : 0;
                                                ?>
                                                <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                                            </div>
                                            <span class="ms-2 small">
                                                <?= $completed_reviews ?>/<?= $order['reviews_needed'] ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>₹<?= number_format($order['grand_total'], 2) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            (₹<?= number_format($order['total_amount'], 2) ?> + GST)
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_badges = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info'
                                        ];
                                        $badge_class = $payment_badges[$order['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'warning',
                                            'approved' => 'primary',
                                            'completed' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $status_badge = $status_badges[$order['admin_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $status_badge ?>">
                                            <?= ucfirst($order['admin_status']) ?>
                                        </span>
                                        <?php if ($order['admin_status'] === 'rejected' && $order['rejection_reason']): ?>
                                            <i class="bi bi-info-circle text-danger" 
                                               data-bs-toggle="tooltip" 
                                               title="<?= htmlspecialchars($order['rejection_reason']) ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                            <br>
                                            <?= date('H:i', strtotime($order['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#orderModal<?= $order['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($order['payment_status'] === 'pending'): ?>
                                                <a href="payment-callback.php?request_id=<?= $order['id'] ?>&action=initiate" 
                                                   class="btn btn-outline-success">
                                                    <i class="bi bi-credit-card"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Order Details Modal -->
                                <div class="modal fade" id="orderModal<?= $order['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Order #<?= $order['id'] ?> Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Product Name:</strong>
                                                        <p><?= htmlspecialchars($order['product_name']) ?></p>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <strong>Brand:</strong>
                                                        <p><?= htmlspecialchars($order['brand_name']) ?></p>
                                                    </div>
                                                    <div class="col-12 mb-3">
                                                        <strong>Product Link:</strong>
                                                        <p><a href="<?= htmlspecialchars($order['product_link']) ?>" target="_blank" class="text-break">
                                                            <?= htmlspecialchars($order['product_link']) ?>
                                                        </a></p>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <strong>Product Price:</strong>
                                                        <p>₹<?= number_format($order['product_price'], 2) ?></p>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <strong>Platform:</strong>
                                                        <p><?= strtoupper($order['platform']) ?></p>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <strong>Reviews:</strong>
                                                        <p><?= $completed_reviews ?> / <?= $order['reviews_needed'] ?></p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <h6>Payment Details</h6>
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td>Base Amount:</td>
                                                        <td class="text-end">₹<?= number_format($order['total_amount'], 2) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>GST (<?= GST_RATE ?>%):</td>
                                                        <td class="text-end">₹<?= number_format($order['gst_amount'], 2) ?></td>
                                                    </tr>
                                                    <tr class="fw-bold">
                                                        <td>Grand Total:</td>
                                                        <td class="text-end">₹<?= number_format($order['grand_total'], 2) ?></td>
                                                    </tr>
                                                </table>
                                                
                                                <?php if ($order['payment_id']): ?>
                                                    <p class="mb-0">
                                                        <strong>Payment ID:</strong> <?= htmlspecialchars($order['payment_id']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php
                                                $order_entries = [];
                                                foreach ($resolve_entry_keys($order) as $entry_key) {
                                                    if (!empty($entry_tasks[$entry_key])) {
                                                        $order_entries = $entry_tasks[$entry_key];
                                                        break;
                                                    }
                                                }
                                                ?>
                                                
                                                <hr>
                                                <h6>Reviewer Entries</h6>
                                                <?php if (!empty($order_entries)): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Task</th>
                                                                    <th>Reviewer</th>
                                                                    <th>Order ID</th>
                                                                    <th>Order Proof</th>
                                                                    <th>Review Proof</th>
                                                                    <th>Live Review</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($order_entries as $entry): ?>
                                                                    <?php
                                                                    $entry_status = $determine_entry_status($entry);
                                                                    ?>
                                                                    <tr>
                                                                        <td>#<?= htmlspecialchars($entry['id']) ?></td>
                                                                        <td><?= htmlspecialchars($entry['reviewer_name']) ?></td>
                                                                        <td><?= htmlspecialchars($entry['order_number'] ?? '-') ?></td>
                                                                        <td>
                                                                            <?php if (!empty($entry['order_screenshot'])): ?>
                                                                                <a href="<?= htmlspecialchars($entry['order_screenshot']) ?>" target="_blank">View</a>
                                                                            <?php else: ?>
                                                                                <span class="text-muted">-</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <?php if (!empty($entry['review_screenshot'])): ?>
                                                                                <a href="<?= htmlspecialchars($entry['review_screenshot']) ?>" target="_blank">View</a>
                                                                            <?php else: ?>
                                                                                <span class="text-muted">-</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <?php if (!empty($entry['review_live_screenshot'])): ?>
                                                                                <a href="<?= htmlspecialchars($entry['review_live_screenshot']) ?>" target="_blank">View</a>
                                                                            <?php else: ?>
                                                                                <span class="text-muted">-</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td><?= htmlspecialchars($entry_status) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted small mb-0">No reviewer entries yet.</p>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['admin_status'] === 'rejected' && $order['rejection_reason']): ?>
                                                    <hr>
                                                    <div class="alert alert-danger mb-0">
                                                        <strong>Rejection Reason:</strong>
                                                        <p class="mb-0"><?= nl2br(htmlspecialchars($order['rejection_reason'])) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    <?php if ($selected_order_id > 0): ?>
    var selectedOrderId = <?= (int)$selected_order_id ?>;
    var modalElement = document.getElementById('orderModal' + selectedOrderId);
    if (modalElement) {
        var orderModal = new bootstrap.Modal(modalElement);
        orderModal.show();
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
