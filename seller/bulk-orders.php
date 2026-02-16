<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle bulk order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bulk'])) {
    $orders_data = $_POST['orders'] ?? [];
    $created_count = 0;
    $failed_count = 0;
    
    foreach ($orders_data as $order) {
        if (empty($order['product_name']) || empty($order['platform']) || empty($order['reviews_needed'])) {
            $failed_count++;
            continue;
        }
        
        try {
            // Calculate costs
            $reviews_needed = intval($order['reviews_needed']);
            $price_per_product = floatval($order['price_per_product'] ?? 0);
            $commission_per_review = floatval($order['commission_per_review'] ?? DEFAULT_ADMIN_COMMISSION_PER_REVIEW);
            
            $subtotal = ($price_per_product * $reviews_needed) + ($commission_per_review * $reviews_needed);
            $gst_amount = ($subtotal * GST_RATE) / 100;
            $grand_total = $subtotal + $gst_amount;
            
            // Insert order
            $stmt = $pdo->prepare("
                INSERT INTO review_requests 
                (seller_id, product_name, product_url, brand_name, platform, 
                 reviews_needed, price_per_product, commission_per_review,
                 subtotal, gst_amount, grand_total, payment_status, admin_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            
            $stmt->execute([
                $seller_id,
                $order['product_name'],
                $order['product_url'] ?? '',
                $order['brand_name'] ?? '',
                $order['platform'],
                $reviews_needed,
                $price_per_product,
                $commission_per_review,
                $subtotal,
                $gst_amount,
                $grand_total
            ]);
            
            $created_count++;
        } catch (PDOException $e) {
            error_log('Bulk order creation error: ' . $e->getMessage());
            $failed_count++;
        }
    }
    
    $_SESSION['success_message'] = "$created_count orders created successfully!";
    if ($failed_count > 0) {
        $_SESSION['error_message'] = "$failed_count orders failed to create.";
    }
    header('Location: bulk-orders.php');
    exit;
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Bulk Order Creation</h3>
            <p class="text-muted">Create multiple review orders at once</p>
        </div>
        <div class="col-auto">
            <a href="orders.php" class="btn btn-outline-primary">
                <i class="bi bi-list"></i> View All Orders
            </a>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Order Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="bulkOrderForm">
                <div id="ordersContainer">
                    <!-- Order rows will be added here -->
                </div>
                
                <div class="row mb-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="addOrderRow()">
                            <i class="bi bi-plus-circle"></i> Add Order
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="loadFromCSV()">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Import from CSV
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" name="create_bulk" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create All Orders
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- CSV Upload Modal -->
    <div class="modal fade" id="csvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Upload a CSV file with the following columns:</p>
                    <code>product_name, product_url, brand_name, platform, reviews_needed, price_per_product, commission_per_review</code>
                    <div class="mt-3">
                        <input type="file" id="csvFile" accept=".csv" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processCSV()">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let orderCount = 0;

function addOrderRow() {
    orderCount++;
    const container = document.getElementById('ordersContainer');
    const row = document.createElement('div');
    row.className = 'card mb-3';
    row.innerHTML = `
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Order #${orderCount}</h6>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.card').remove()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="orders[${orderCount}][product_name]" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Product URL</label>
                    <input type="url" name="orders[${orderCount}][product_url]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Brand Name</label>
                    <input type="text" name="orders[${orderCount}][brand_name]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Platform *</label>
                    <select name="orders[${orderCount}][platform]" class="form-select" required>
                        <option value="">Select Platform</option>
                        <option value="amazon">Amazon</option>
                        <option value="flipkart">Flipkart</option>
                        <option value="meesho">Meesho</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reviews Needed *</label>
                    <input type="number" name="orders[${orderCount}][reviews_needed]" class="form-control" min="1" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per Product (₹)</label>
                    <input type="number" name="orders[${orderCount}][price_per_product]" class="form-control" step="0.01" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Commission per Review (₹)</label>
                    <input type="number" name="orders[${orderCount}][commission_per_review]" class="form-control" step="0.01" value="<?= DEFAULT_ADMIN_COMMISSION_PER_REVIEW ?>">
                </div>
            </div>
        </div>
    `;
    container.appendChild(row);
}

function loadFromCSV() {
    const modal = new bootstrap.Modal(document.getElementById('csvModal'));
    modal.show();
}

function processCSV() {
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result;
        const lines = text.split('\n');
        
        // Skip header row
        for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            
            const cols = lines[i].split(',');
            if (cols.length >= 5) {
                addOrderRow();
                const lastRow = document.querySelector('#ordersContainer .card:last-child');
                lastRow.querySelector('input[name*="[product_name]"]').value = cols[0].trim();
                lastRow.querySelector('input[name*="[product_url]"]').value = cols[1].trim();
                lastRow.querySelector('input[name*="[brand_name]"]').value = cols[2].trim();
                lastRow.querySelector('select[name*="[platform]"]').value = cols[3].trim().toLowerCase();
                lastRow.querySelector('input[name*="[reviews_needed]"]').value = cols[4].trim();
                if (cols[5]) lastRow.querySelector('input[name*="[price_per_product]"]').value = cols[5].trim();
                if (cols[6]) lastRow.querySelector('input[name*="[commission_per_review]"]').value = cols[6].trim();
            }
        }
        
        bootstrap.Modal.getInstance(document.getElementById('csvModal')).hide();
    };
    reader.readAsText(file);
}

// Add initial row on page load
document.addEventListener('DOMContentLoaded', function() {
    addOrderRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
