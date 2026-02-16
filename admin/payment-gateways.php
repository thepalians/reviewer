<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create_gateway') {
                $stmt = $pdo->prepare("
                    INSERT INTO payment_gateways (gateway_name, gateway_type, api_key, api_secret, 
                                                   merchant_id, webhook_secret, is_active, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    sanitize($_POST['gateway_name']),
                    sanitize($_POST['gateway_type']),
                    sanitize($_POST['api_key']),
                    sanitize($_POST['api_secret']),
                    sanitize($_POST['merchant_id'] ?? ''),
                    sanitize($_POST['webhook_secret'] ?? ''),
                    (int)($_POST['is_active'] ?? 0),
                    (int)($_POST['is_default'] ?? 0)
                ]);
                $success = 'Payment gateway created successfully!';
            } elseif ($action === 'update_gateway') {
                $stmt = $pdo->prepare("
                    UPDATE payment_gateways 
                    SET gateway_name = ?, api_key = ?, api_secret = ?, 
                        merchant_id = ?, webhook_secret = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    sanitize($_POST['gateway_name']),
                    sanitize($_POST['api_key']),
                    sanitize($_POST['api_secret']),
                    sanitize($_POST['merchant_id'] ?? ''),
                    sanitize($_POST['webhook_secret'] ?? ''),
                    (int)($_POST['is_active'] ?? 0),
                    (int)$_POST['gateway_id']
                ]);
                $success = 'Gateway updated successfully!';
            } elseif ($action === 'set_default') {
                $pdo->exec("UPDATE payment_gateways SET is_default = 0");
                $stmt = $pdo->prepare("UPDATE payment_gateways SET is_default = 1 WHERE id = ?");
                $stmt->execute([(int)$_POST['gateway_id']]);
                $success = 'Default gateway updated!';
            } elseif ($action === 'delete_gateway') {
                $stmt = $pdo->prepare("DELETE FROM payment_gateways WHERE id = ?");
                $stmt->execute([(int)$_POST['gateway_id']]);
                $success = 'Gateway deleted successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
            error_log("Payment gateway error: " . $e->getMessage());
        }
    }
}

// Get all payment gateways
$gateways = [];
try {
    $stmt = $pdo->query("
        SELECT pg.*, 
               (SELECT COUNT(*) FROM transactions WHERE gateway_id = pg.id) as transaction_count,
               (SELECT SUM(amount) FROM transactions WHERE gateway_id = pg.id AND status = 'success') as total_processed
        FROM payment_gateways pg
        ORDER BY is_default DESC, gateway_name
    ");
    $gateways = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load gateways';
    error_log("Gateway load error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'payment-gateways';

$gatewayTypes = [
    'razorpay' => 'Razorpay',
    'payu' => 'PayU',
    'stripe' => 'Stripe',
    'paypal' => 'PayPal',
    'paytm' => 'Paytm',
    'phonepe' => 'PhonePe',
    'cashfree' => 'Cashfree'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateways - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .gateway-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-bottom:30px}
        .gateway-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:20px;position:relative;transition:transform 0.3s}
        .gateway-card:hover{transform:translateY(-5px);box-shadow:0 4px 20px rgba(0,0,0,0.1)}
        .gateway-card.default{border:2px solid #10b981}
        .gateway-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .gateway-name{font-size:18px;font-weight:600;color:#333}
        .gateway-type{font-size:12px;color:#999;text-transform:uppercase}
        .gateway-stats{margin:15px 0;padding:15px 0;border-top:1px solid #e9ecef;border-bottom:1px solid #e9ecef}
        .stat-row{display:flex;justify-content:space-between;margin:8px 0;font-size:13px}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.active{background:#d1fae5;color:#065f46}
        .badge.inactive{background:#fee2e2;color:#991b1b}
        .badge.default{background:#dbeafe;color:#1e40af}
        .gateway-actions{display:flex;gap:5px;flex-wrap:wrap}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.gateway-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-credit-card"></i> Payment Gateways</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGatewayModal">
                    <i class="bi bi-plus-circle"></i> Add Gateway
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <?php if (empty($gateways)): ?>
                <div class="card text-center py-5">
                    <i class="bi bi-credit-card" style="font-size:64px;color:#ccc"></i>
                    <h4 class="mt-3">No Payment Gateways</h4>
                    <p class="text-muted">Add your first payment gateway to start accepting payments</p>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createGatewayModal">
                        Add Payment Gateway
                    </button>
                </div>
            <?php else: ?>
                <div class="gateway-grid">
                    <?php foreach ($gateways as $gateway): ?>
                        <div class="gateway-card <?= $gateway['is_default'] ? 'default' : '' ?>">
                            <div class="gateway-header">
                                <div>
                                    <div class="gateway-name">
                                        <?= escape($gateway['gateway_name']) ?>
                                    </div>
                                    <div class="gateway-type"><?= escape($gateway['gateway_type']) ?></div>
                                </div>
                                <div>
                                    <?php if ($gateway['is_default']): ?>
                                        <span class="badge default">Default</span>
                                    <?php endif; ?>
                                    <span class="badge <?= $gateway['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $gateway['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="gateway-stats">
                                <div class="stat-row">
                                    <span style="color:#888">Transactions:</span>
                                    <strong><?= number_format($gateway['transaction_count']) ?></strong>
                                </div>
                                <div class="stat-row">
                                    <span style="color:#888">Total Processed:</span>
                                    <strong style="color:#10b981">
                                        ₹<?= number_format($gateway['total_processed'] ?? 0, 2) ?>
                                    </strong>
                                </div>
                                <div class="stat-row">
                                    <span style="color:#888">Added:</span>
                                    <span><?= date('M d, Y', strtotime($gateway['created_at'])) ?></span>
                                </div>
                            </div>

                            <div class="gateway-actions">
                                <?php if (!$gateway['is_default']): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-star"></i> Set Default
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="testGateway(<?= $gateway['id'] ?>)">
                                    <i class="bi bi-play-circle"></i> Test
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="editGateway(<?= $gateway['id'] ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteGateway(<?= $gateway['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Gateway Modal -->
    <div class="modal fade" id="createGatewayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_gateway">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add Payment Gateway</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gateway Name</label>
                                <input type="text" name="gateway_name" class="form-control" 
                                       placeholder="e.g., Main Razorpay" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gateway Type</label>
                                <select name="gateway_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($gatewayTypes as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="api_key" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">API Secret</label>
                            <input type="password" name="api_secret" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Merchant ID (Optional)</label>
                            <input type="text" name="merchant_id" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Webhook Secret (Optional)</label>
                            <input type="text" name="webhook_secret" class="form-control">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3 form-check">
                                <input type="checkbox" name="is_active" value="1" 
                                       class="form-check-input" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            
                            <div class="col-md-6 mb-3 form-check">
                                <input type="checkbox" name="is_default" value="1" 
                                       class="form-check-input" id="is_default">
                                <label class="form-check-label" for="is_default">Set as Default</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Gateway</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editGateway(gatewayId) {
            alert('Edit gateway functionality - implement with gateway details');
        }

        function testGateway(gatewayId) {
            if (!confirm('Test this payment gateway connection?')) return;
            
            fetch('test-gateway.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    gateway_id: gatewayId,
                    csrf_token: '<?= $csrf_token ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                alert(data.success ? 'Gateway test successful!' : 'Gateway test failed: ' + data.error);
            });
        }

        function deleteGateway(gatewayId) {
            if (!confirm('Are you sure you want to delete this gateway?')) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="delete_gateway">
                <input type="hidden" name="gateway_id" value="${gatewayId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
