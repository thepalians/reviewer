<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Only regular logged-in users
if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    redirect('tasks.php');
}

$order_id = sanitizeInput($_GET['order_id']);

// Fetch task details
try {
    $stmt = $pdo->prepare("SELECT t.*, u.name AS user_name, u.email, u.account_name, u.account_number, u.bank_name, u.ifsc_code, u.upi_id 
                           FROM tasks t 
                           JOIN users u ON t.user_id = u.id 
                           WHERE t.order_id = ? AND t.user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        die("Task not found!");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Entry - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">📄 Entry Details: <?php echo htmlspecialchars($order_id); ?></h2>

        <!-- Status Badge -->
        <div class="mb-4">
            <?php
            $status_color = 'secondary';
            $status_text = ucfirst(str_replace('_', ' ', $task['status']));

            switch ($task['status']) {
                case 'assigned': $status_color = 'danger'; break;
                case 'step1_completed': $status_color = 'warning'; break;
                case 'step2_completed': $status_color = 'info'; break;
                case 'step3_completed': $status_color = 'primary'; break;
                case 'refund_requested': $status_color = 'success'; $status_text = 'Refund Requested'; break;
                case 'completed': $status_color = 'success'; $status_text = 'Refunded'; break;
            }
            ?>
            <span class="badge bg-<?php echo $status_color; ?> status-badge">Status: <?php echo $status_text; ?></span>

            <?php if (!empty($task['refund_date'])): ?>
                <span class="badge bg-success status-badge">Refunded on: <?php echo date('d-m-Y', strtotime($task['refund_date'])); ?></span>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Task Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Task Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Product</div>
                            <div><?php echo htmlspecialchars($task['product_name'] ?? ''); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Platform</div>
                            <div><?php echo htmlspecialchars($task['platform'] ?? ''); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Assigned On</div>
                            <div><?php echo $task['created_at'] ? formatDate($task['created_at'], 'd-m-Y') : '-'; ?></div>
                        </div>
                        <?php if (!empty($task['instructions'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Special Instructions</div>
                            <div><?php echo htmlspecialchars($task['instructions']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Review / Payment Info -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">User / Payment Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Reviewer</div>
                            <div><?php echo htmlspecialchars($task['user_name'] ?? ''); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div><?php echo htmlspecialchars($task['email'] ?? ''); ?></div>
                        </div>
                        <!-- Payment details if available -->
                        <?php if (!empty($task['account_name'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Account Name</div>
                            <div><?php echo htmlspecialchars($task['account_name']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Details -->
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Review & Order Details</h5></div>
                    <div class="card-body">
                        <?php if (!empty($task['order_id'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Order ID</div>
                                <div><?php echo htmlspecialchars($task['order_id']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($task['review_text'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Review</div>
                                <div><?php echo nl2br(htmlspecialchars($task['review_text'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($task['review_rating'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Rating</div>
                                <div><?php echo intval($task['review_rating']); ?>/5</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
