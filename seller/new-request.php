<?php
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

// Get admin commission from settings
$admin_commission = (float) getSetting('admin_commission_per_review', 50);
$gst_rate = (float) getSetting('gst_rate', 18);

// Check seller session
if (!isset($_SESSION['seller_id'])) {
    header('Location: index.php');
    exit;
}
$seller_id = $_SESSION['seller_id'];

// Process form BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_link = trim($_POST['product_link'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $product_price = (float) ($_POST['product_price'] ?? 0);
    $brand_name = trim($_POST['brand_name'] ?? '');
    $platform = $_POST['platform'] ?? 'amazon';
    $reviews_needed = (int) ($_POST['reviews_needed'] ?? 0);
    
    // Validation
    if (empty($product_link) || empty($product_name) || $product_price <= 0 || empty($brand_name) || $reviews_needed <= 0) {
        $error = 'Please fill all required fields with valid values.';
    } elseif (!filter_var($product_link, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid product URL.';
    } elseif ($reviews_needed > 100) {
        $error = 'Maximum 100 reviews per order.';
    } else {
        try {
            // Calculate amounts
            $total_amount = ($product_price + $admin_commission) * $reviews_needed;
            $gst_amount = ($total_amount * $gst_rate) / 100;
            $grand_total = $total_amount + $gst_amount;
            
            // Insert review request
            $stmt = $pdo->prepare("
                INSERT INTO review_requests 
                (seller_id, product_link, product_name, product_price, brand_name, platform, 
                 reviews_needed, admin_commission, total_amount, gst_amount, grand_total, 
                 payment_status, admin_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            
            $stmt->execute([
                $seller_id,
                $product_link,
                $product_name,
                $product_price,
                $brand_name,
                $platform,
                $reviews_needed,
                $admin_commission,
                $total_amount,
                $gst_amount,
                $grand_total
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            // Redirect to payment page - BEFORE any HTML output!
            header('Location: payment-callback.php?request_id=' . $request_id . '&action=initiate');
            exit;
            
        } catch (PDOException $e) {
            error_log('New request error: ' . $e->getMessage());
            $error = 'Failed to create review request. Please try again.';
        }
    }
}

// NOW include header (after all possible redirects)
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">New Review Request</li>
                </ol>
            </nav>
            <h3 class="mb-0">Create New Review Request</h3>
            <p class="text-muted">Fill in the details to request product reviews</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="" id="reviewRequestForm">
                        <h5 class="mb-3"><i class="bi bi-box"></i> Product Information</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Product Link <span class="text-danger">*</span></label>
                            <input type="url" name="product_link" class="form-control" 
                                   placeholder="https://www.amazon.in/product..." 
                                   value="<?= htmlspecialchars($_POST['product_link'] ?? '') ?>" required>
                            <small class="text-muted">Complete URL of the product</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" 
                                   placeholder="Enter product name" 
                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                                <input type="text" name="brand_name" class="form-control" 
                                       placeholder="Enter brand name" 
                                       value="<?= htmlspecialchars($_POST['brand_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Price (₹) <span class="text-danger">*</span></label>
                                <input type="number" name="product_price" class="form-control" 
                                       placeholder="0.00" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($_POST['product_price'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="mb-3"><i class="bi bi-star"></i> Review Details</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Platform <span class="text-danger">*</span></label>
                                <select name="platform" class="form-select" required>
                                    <option value="amazon" <?= ($_POST['platform'] ?? '') === 'amazon' ? 'selected' : '' ?>>Amazon</option>
                                    <option value="flipkart" <?= ($_POST['platform'] ?? '') === 'flipkart' ? 'selected' : '' ?>>Flipkart</option>
                                    <option value="other" <?= ($_POST['platform'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Number of Reviews Needed <span class="text-danger">*</span></label>
                                <input type="number" name="reviews_needed" id="reviews_needed" class="form-control" 
                                       placeholder="5" min="1" max="100" 
                                       value="<?= htmlspecialchars($_POST['reviews_needed'] ?? '') ?>" required>
                                <small class="text-muted">Max 100 reviews per order</small>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-right-circle"></i> Proceed to Payment
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-calculator"></i> Price Calculator</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Commission per Review:</span>
                        <strong>₹<?= number_format($admin_commission, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Number of Reviews:</span>
                        <strong id="calc_reviews">0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="calc_subtotal">₹0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>GST (<?= $gst_rate ?>%):</span>
                        <span id="calc_gst">₹0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total Amount:</strong>
                        <strong class="text-primary" id="calc_total">₹0.00</strong>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-info-circle"></i> Important Notes</h6>
                    <ul class="small text-muted mb-0">
                        <li class="mb-2">Reviews will be assigned to active reviewers after admin approval</li>
                        <li class="mb-2">Payment must be completed for order processing</li>
                        <li class="mb-2">Commission: ₹<?= number_format($admin_commission, 2) ?> per review</li>
                        <li class="mb-2">GST of <?= $gst_rate ?>% will be added to the total</li>
                        <li>Maximum 100 reviews per order</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewsInput = document.getElementById('reviews_needed');
    const adminCommission = <?= $admin_commission ?>;
    const gstRate = <?= $gst_rate ?>;
    
    function updateCalculator() {
        const reviews = parseInt(reviewsInput.value) || 0;
        const productPrice = parseFloat(document.querySelector('input[name="product_price"]').value) || 0;
        const subtotal = (productPrice + adminCommission) * reviews;
        const gst = (subtotal * gstRate) / 100;
        const total = subtotal + gst;
        
        document.getElementById('calc_reviews').textContent = reviews;
        document.getElementById('calc_subtotal').textContent = '₹' + subtotal.toFixed(2);
        document.getElementById('calc_gst').textContent = '₹' + gst.toFixed(2);
        document.getElementById('calc_total').textContent = '₹' + total.toFixed(2);
    }
    
    reviewsInput.addEventListener('input', updateCalculator);
    document.querySelector('input[name="product_price"]').addEventListener('input', updateCalculator);
    updateCalculator();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
