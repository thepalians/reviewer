<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Handle inventory adjustments
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'adjust_inventory') {
                $product_id = $_POST['product_id'];
                $adjustment_type = $_POST['adjustment_type'];
                $quantity = abs((int)$_POST['quantity']);
                $reason = $_POST['reason'] ?? '';
                
                $pdo->beginTransaction();
                
                // Get current stock
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock === false) {
                    throw new Exception('Product not found');
                }
                
                // Calculate new stock
                $new_stock = $adjustment_type === 'add' ? $current_stock + $quantity : $current_stock - $quantity;
                
                if ($new_stock < 0) {
                    throw new Exception('Insufficient stock for reduction');
                }
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ? AND seller_id = ?");
                $stmt->execute([$new_stock, $product_id, $seller_id]);
                
                // Log inventory movement
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_logs (product_id, action, quantity, previous_stock, new_stock, notes, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $product_id,
                    $adjustment_type,
                    $quantity,
                    $current_stock,
                    $new_stock,
                    $reason,
                    $seller_id
                ]);
                
                $pdo->commit();
                $success_message = 'Inventory adjusted successfully';
                
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Inventory adjustment error: ' . $e->getMessage());
            $error_message = $e->getMessage();
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get inventory data
try {
    $where_conditions = ["p.seller_id = ?"];
    $params = [$seller_id];
    
    if ($filter_status === 'low_stock') {
        $where_conditions[] = "p.stock_quantity <= p.low_stock_threshold";
    } elseif ($filter_status === 'out_of_stock') {
        $where_conditions[] = "p.stock_quantity = 0";
    }
    
    if ($search) {
        $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM inventory_logs WHERE product_id = p.id) as movement_count
        FROM products p
        WHERE $where_clause AND p.status IN ('active', 'out_of_stock')
        ORDER BY p.stock_quantity ASC, p.name ASC
    ");
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
    
    // Get inventory statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(stock_quantity) as total_stock,
            SUM(CASE WHEN stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM products
        WHERE seller_id = ? AND status IN ('active', 'out_of_stock')
    ");
    $stmt->execute([$seller_id]);
    $stats = $stmt->fetch();
    
    // Get recent movements
    $stmt = $pdo->prepare("
        SELECT il.*, p.name as product_name, p.sku
        FROM inventory_logs il
        JOIN products p ON p.id = il.product_id
        WHERE p.seller_id = ?
        ORDER BY il.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$seller_id]);
    $recent_movements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Inventory fetch error: ' . $e->getMessage());
    $inventory = [];
    $stats = [
        'total_products' => 0,
        'total_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
    $recent_movements = [];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Inventory Tracking</h3>
            <p class="text-muted">Monitor and manage your product inventory</p>
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
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Products</div>
                            <h3 class="mb-0"><?= number_format($stats['total_products']) ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-box-seam fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Stock</div>
                            <h3 class="mb-0"><?= number_format($stats['total_stock']) ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="bi bi-boxes fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Low Stock</div>
                            <h3 class="mb-0 text-warning"><?= number_format($stats['low_stock']) ?></h3>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-exclamation-triangle fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Out of Stock</div>
                            <h3 class="mb-0 text-danger"><?= number_format($stats['out_of_stock']) ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="bi bi-x-circle fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Products</option>
                        <option value="low_stock" <?= $filter_status === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out_of_stock" <?= $filter_status === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="inventory.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Inventory Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Current Inventory</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Current Stock</th>
                            <th>Low Stock Alert</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No inventory items found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                            </td>
                            <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                            <td>
                                <h5 class="mb-0">
                                    <?php if ($item['stock_quantity'] == 0): ?>
                                    <span class="badge bg-danger"><?= $item['stock_quantity'] ?></span>
                                    <?php elseif ($item['stock_quantity'] <= $item['low_stock_threshold']): ?>
                                    <span class="badge bg-warning text-dark"><?= $item['stock_quantity'] ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><?= $item['stock_quantity'] ?></span>
                                    <?php endif; ?>
                                </h5>
                            </td>
                            <td><?= $item['low_stock_threshold'] ?></td>
                            <td>
                                <?php if ($item['stock_quantity'] == 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                                <?php elseif ($item['stock_quantity'] <= $item['low_stock_threshold']): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                                <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($item['updated_at'] ?? $item['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary adjust-inventory" 
                                        data-id="<?= $item['id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-stock="<?= $item['stock_quantity'] ?>">
                                    <i class="bi bi-plus-minus"></i> Adjust
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
    
    <!-- Recent Movements -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Recent Inventory Movements</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Previous</th>
                            <th>New</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_movements)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No inventory movements yet.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_movements as $movement): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($movement['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($movement['product_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($movement['sku']) ?></small>
                            </td>
                            <td>
                                <?php if ($movement['action'] === 'add'): ?>
                                <span class="badge bg-success"><i class="bi bi-arrow-up"></i> Add</span>
                                <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-arrow-down"></i> Remove</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $movement['quantity'] ?></td>
                            <td><?= $movement['previous_stock'] ?></td>
                            <td><?= $movement['new_stock'] ?></td>
                            <td><?= htmlspecialchars($movement['notes']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Inventory Modal -->
<div class="modal fade" id="adjustInventoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="adjust_inventory">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="product_id" id="adjust_product_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="adjust_product_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="adjust_current_stock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type *</label>
                        <select class="form-select" name="adjustment_type" required>
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" class="form-control" name="quantity" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Enter reason for adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Adjust inventory
document.querySelectorAll('.adjust-inventory').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('adjust_product_id').value = this.dataset.id;
        document.getElementById('adjust_product_name').value = this.dataset.name;
        document.getElementById('adjust_current_stock').value = this.dataset.stock;
        new bootstrap.Modal(document.getElementById('adjustInventoryModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
