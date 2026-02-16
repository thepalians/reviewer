<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle template creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_template'])) {
    $template_name = trim($_POST['template_name']);
    $product_name = trim($_POST['product_name']);
    $brand_name = trim($_POST['brand_name']);
    $platform = $_POST['platform'];
    $reviews_needed = intval($_POST['reviews_needed']);
    $price_per_product = floatval($_POST['price_per_product']);
    $commission_per_review = floatval($_POST['commission_per_review']);
    
    if (!empty($template_name) && !empty($product_name) && !empty($platform)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_templates 
                (seller_id, template_name, product_name, brand_name, platform, 
                 reviews_needed, price_per_product, commission_per_review)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $seller_id, $template_name, $product_name, $brand_name,
                $platform, $reviews_needed, $price_per_product, $commission_per_review
            ]);
            $_SESSION['success_message'] = 'Template created successfully!';
            header('Location: order-templates.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Failed to create template.';
            error_log('Template creation error: ' . $e->getMessage());
        }
    } else {
        $error_message = 'Please fill all required fields.';
    }
}

// Handle template deletion
if (isset($_GET['delete'])) {
    $template_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM order_templates WHERE id = ? AND seller_id = ?");
        $stmt->execute([$template_id, $seller_id]);
        $_SESSION['success_message'] = 'Template deleted successfully!';
        header('Location: order-templates.php');
        exit;
    } catch (PDOException $e) {
        $error_message = 'Failed to delete template.';
    }
}

// Handle use template
if (isset($_GET['use'])) {
    $template_id = intval($_GET['use']);
    $stmt = $pdo->prepare("SELECT * FROM order_templates WHERE id = ? AND seller_id = ?");
    $stmt->execute([$template_id, $seller_id]);
    $template = $stmt->fetch();
    
    if ($template) {
        $_SESSION['template_data'] = $template;
        header('Location: new-request.php');
        exit;
    }
}

// Get all templates
try {
    $stmt = $pdo->prepare("
        SELECT * FROM order_templates 
        WHERE seller_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    $templates = [];
    error_log('Get templates error: ' . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Order Templates</h3>
            <p class="text-muted">Save and reuse common order configurations</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="bi bi-plus-circle"></i> Create Template
            </button>
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
    
    <?php if (count($templates) > 0): ?>
    <div class="row g-4">
        <?php foreach ($templates as $template): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <h5 class="mb-0"><?= htmlspecialchars($template['template_name']) ?></h5>
                    <span class="badge bg-secondary"><?= strtoupper($template['platform']) ?></span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-muted small mb-1">Product</h6>
                        <p class="mb-0"><?= htmlspecialchars($template['product_name']) ?></p>
                        <?php if ($template['brand_name']): ?>
                        <small class="text-muted"><?= htmlspecialchars($template['brand_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Reviews</h6>
                            <p class="mb-0 fw-bold"><?= $template['reviews_needed'] ?></p>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted small mb-1">Price/Product</h6>
                            <p class="mb-0 fw-bold">₹<?= number_format($template['price_per_product'], 2) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted small mb-1">Commission/Review</h6>
                        <p class="mb-0 fw-bold">₹<?= number_format($template['commission_per_review'], 2) ?></p>
                    </div>
                    
                    <div class="text-muted small">
                        <i class="bi bi-clock"></i> Created <?= date('M d, Y', strtotime($template['created_at'])) ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-top">
                    <div class="d-flex gap-2">
                        <a href="?use=<?= $template['id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="bi bi-play-circle"></i> Use Template
                        </a>
                        <button class="btn btn-outline-secondary btn-sm" onclick="editTemplate(<?= $template['id'] ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?delete=<?= $template['id'] ?>" class="btn btn-outline-danger btn-sm" 
                           onclick="return confirm('Delete this template?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #cbd5e1;"></i>
            <h5 class="mt-3">No Templates Yet</h5>
            <p class="text-muted">Create templates to quickly reuse common order configurations</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="bi bi-plus-circle"></i> Create Your First Template
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Order Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template Name *</label>
                        <input type="text" name="template_name" class="form-control" 
                               placeholder="e.g., Standard Amazon Order" required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand Name</label>
                            <input type="text" name="brand_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Platform *</label>
                            <select name="platform" class="form-select" required>
                                <option value="">Select Platform</option>
                                <option value="amazon">Amazon</option>
                                <option value="flipkart">Flipkart</option>
                                <option value="meesho">Meesho</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reviews Needed *</label>
                            <input type="number" name="reviews_needed" class="form-control" 
                                   min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Price per Product (₹)</label>
                            <input type="number" name="price_per_product" class="form-control" 
                                   step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Commission per Review (₹)</label>
                            <input type="number" name="commission_per_review" class="form-control" 
                                   step="0.01" min="0" value="<?= DEFAULT_ADMIN_COMMISSION_PER_REVIEW ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_template" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(id) {
    alert('Edit functionality coming soon!');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
