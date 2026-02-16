<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Handle product operations
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'add_product') {
                $stmt = $pdo->prepare("
                    INSERT INTO products (seller_id, name, sku, barcode, description, price, stock_quantity, low_stock_threshold, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $seller_id,
                    $_POST['name'],
                    $_POST['sku'],
                    $_POST['barcode'] ?? null,
                    $_POST['description'] ?? '',
                    $_POST['price'],
                    $_POST['stock_quantity'] ?? 0,
                    $_POST['low_stock_threshold'] ?? 10
                ]);
                $success_message = 'Product added successfully';
                
            } elseif ($action === 'edit_product') {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, sku = ?, barcode = ?, description = ?, price = ?, stock_quantity = ?, low_stock_threshold = ?, updated_at = NOW()
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['sku'],
                    $_POST['barcode'] ?? null,
                    $_POST['description'] ?? '',
                    $_POST['price'],
                    $_POST['stock_quantity'],
                    $_POST['low_stock_threshold'],
                    $_POST['product_id'],
                    $seller_id
                ]);
                $success_message = 'Product updated successfully';
                
            } elseif ($action === 'delete_product') {
                $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ? AND seller_id = ?");
                $stmt->execute([$_POST['product_id'], $seller_id]);
                $success_message = 'Product deleted successfully';
            }
        } catch (PDOException $e) {
            error_log('Product operation error: ' . $e->getMessage());
            $error_message = 'Operation failed. Please try again.';
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get products
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(DISTINCT r.id) as review_count,
               AVG(r.rating) as avg_rating
        FROM products p
        LEFT JOIN reviews r ON r.product_id = p.id
        WHERE p.seller_id = ? AND p.status = 'active'
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $products = $stmt->fetchAll();
    
    // Get low stock products
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM products
        WHERE seller_id = ? AND status = 'active' AND stock_quantity <= low_stock_threshold
    ");
    $stmt->execute([$seller_id]);
    $low_stock_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log('Product fetch error: ' . $e->getMessage());
    $products = [];
    $low_stock_count = 0;
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Product Management</h3>
            <p class="text-muted">Manage your products and inventory</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-circle"></i> Add Product
            </button>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($low_stock_count > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Low Stock Alert:</strong> <?= $low_stock_count ?> product(s) are running low on stock.
        <a href="inventory.php" class="alert-link">View Inventory</a>
    </div>
    <?php endif; ?>
    
    <!-- Products Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Reviews</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No products found. Add your first product to get started.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                <?php if ($product['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($product['sku']) ?></code></td>
                            <td><?= htmlspecialchars($product['barcode'] ?? '-') ?></td>
                            <td>₹<?= number_format($product['price'], 2) ?></td>
                            <td>
                                <?php if ($product['stock_quantity'] <= $product['low_stock_threshold']): ?>
                                <span class="badge bg-warning text-dark">
                                    <?= $product['stock_quantity'] ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-success">
                                    <?= $product['stock_quantity'] ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?= $product['review_count'] ?></td>
                            <td>
                                <?php if ($product['avg_rating']): ?>
                                <span class="text-warning">
                                    <?= number_format($product['avg_rating'], 1) ?>
                                    <i class="bi bi-star-fill"></i>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success">Active</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-product" 
                                        data-product='<?= json_encode($product) ?>'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-product" 
                                        data-id="<?= $product['id'] ?>"
                                        data-name="<?= htmlspecialchars($product['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU *</label>
                            <input type="text" class="form-control" name="sku" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price (₹) *</label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" name="stock_quantity" min="0" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Low Stock Alert</label>
                            <input type="number" class="form-control" name="low_stock_threshold" min="0" value="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editProductForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU *</label>
                            <input type="text" class="form-control" name="sku" id="edit_sku" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode" id="edit_barcode">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price (₹) *</label>
                            <input type="number" class="form-control" name="price" id="edit_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" name="stock_quantity" id="edit_stock_quantity" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Low Stock Alert</label>
                            <input type="number" class="form-control" name="low_stock_threshold" id="edit_low_stock_threshold" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    
                    <p>Are you sure you want to delete <strong id="delete_product_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit product
document.querySelectorAll('.edit-product').forEach(btn => {
    btn.addEventListener('click', function() {
        const product = JSON.parse(this.dataset.product);
        document.getElementById('edit_product_id').value = product.id;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_sku').value = product.sku;
        document.getElementById('edit_barcode').value = product.barcode || '';
        document.getElementById('edit_description').value = product.description || '';
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_stock_quantity').value = product.stock_quantity;
        document.getElementById('edit_low_stock_threshold').value = product.low_stock_threshold;
        new bootstrap.Modal(document.getElementById('editProductModal')).show();
    });
});

// Delete product
document.querySelectorAll('.delete-product').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_product_id').value = this.dataset.id;
        document.getElementById('delete_product_name').textContent = this.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteProductModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
