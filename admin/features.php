<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Handle Add Feature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_feature'])) {
    $feature_key = sanitizeInput($_POST['feature_key'] ?? '');
    $feature_name = sanitizeInput($_POST['feature_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $is_beta = isset($_POST['is_beta']) ? 1 : 0;
    
    if (empty($feature_key) || empty($feature_name)) {
        $errors[] = "Feature key and name are required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO feature_flags (feature_key, feature_name, description, is_enabled, is_beta, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$feature_key, $feature_name, $description, $is_enabled, $is_beta, $admin_name]);
            $success = "Feature added successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "Feature key already exists";
            } else {
                $errors[] = "Failed to add feature: " . $e->getMessage();
            }
        }
    }
}

// Handle Update Feature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feature'])) {
    $id = intval($_POST['feature_id']);
    $feature_name = sanitizeInput($_POST['feature_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    $is_beta = isset($_POST['is_beta']) ? 1 : 0;
    
    if (empty($feature_name)) {
        $errors[] = "Feature name is required";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE feature_flags 
                SET feature_name = ?, description = ?, is_enabled = ?, is_beta = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$feature_name, $description, $is_enabled, $is_beta, $id]);
            $success = "Feature updated successfully!";
        } catch (PDOException $e) {
            $errors[] = "Failed to update feature";
        }
    }
}

// Handle Toggle Feature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_feature'])) {
    $id = intval($_POST['feature_id']);
    $is_enabled = intval($_POST['is_enabled']);
    
    try {
        $stmt = $pdo->prepare("UPDATE feature_flags SET is_enabled = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$is_enabled, $id]);
        $success = "Feature " . ($is_enabled ? "enabled" : "disabled") . " successfully!";
    } catch (PDOException $e) {
        $errors[] = "Failed to toggle feature";
    }
}

// Handle Toggle Beta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_beta'])) {
    $id = intval($_POST['feature_id']);
    $is_beta = intval($_POST['is_beta']);
    
    try {
        $stmt = $pdo->prepare("UPDATE feature_flags SET is_beta = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$is_beta, $id]);
        $success = "Beta mode " . ($is_beta ? "enabled" : "disabled") . " successfully!";
    } catch (PDOException $e) {
        $errors[] = "Failed to toggle beta mode";
    }
}

// Handle Delete Feature
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM feature_flags WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Feature deleted successfully!";
    } catch (PDOException $e) {
        $errors[] = "Failed to delete feature";
    }
    header('Location: features.php');
    exit;
}

// Get all features
try {
    $stmt = $pdo->query("SELECT * FROM feature_flags ORDER BY created_at DESC");
    $features = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM feature_flags WHERE is_enabled = 1");
    $enabled_count = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM feature_flags WHERE is_beta = 1");
    $beta_count = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    $features = [];
    $enabled_count = $beta_count = 0;
    error_log("Features Error: " . $e->getMessage());
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unread_messages = $unanswered_questions = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Flags - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:25px}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b}
        .stat-label{font-size:13px;color:#64748b;margin-top:5px}
        
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
        .btn-small{padding:6px 12px;font-size:12px}
        .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
        
        .form-grid{display:grid;gap:15px}
        .form-group{display:flex;flex-direction:column}
        .form-label{font-size:14px;font-weight:500;color:#475569;margin-bottom:6px}
        .form-input,.form-textarea{padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .form-textarea{resize:vertical;min-height:80px;font-family:inherit}
        .form-checkbox{display:flex;align-items:center;gap:8px}
        .form-checkbox input{width:18px;height:18px}
        
        .features-grid{display:grid;gap:15px}
        .feature-item{background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:20px;transition:all 0.2s}
        .feature-item:hover{border-color:#667eea;box-shadow:0 4px 15px rgba(102,126,234,0.1)}
        .feature-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .feature-info h4{font-size:16px;color:#1e293b;margin-bottom:5px}
        .feature-info p{font-size:13px;color:#64748b}
        .feature-meta{font-size:12px;color:#94a3b8;margin-top:10px}
        .feature-actions{display:flex;gap:8px;flex-wrap:wrap}
        
        .toggle-switch{position:relative;display:inline-block;width:50px;height:26px}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#cbd5e1;transition:0.3s;border-radius:26px}
        .toggle-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:#fff;transition:0.3s;border-radius:50%}
        .toggle-switch input:checked + .toggle-slider{background-color:#10b981 !important}
        .toggle-switch input:checked + .toggle-slider:before{transform:translateX(24px)}
        
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-enabled{background:#dcfce7;color:#166534}
        .badge-disabled{background:#fee2e2;color:#991b1b}
        .badge-beta{background:#fef3c7;color:#92400e}
        
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-title{font-size:20px;font-weight:700;color:#1e293b;margin-bottom:20px}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:25px}
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">🎯 Feature Flags</h1>
                    <p class="page-subtitle">Manage application features and beta testing</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">➕ Add Feature</button>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div>❌ <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($features); ?></div>
                    <div class="stat-label">Total Features</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $enabled_count; ?></div>
                    <div class="stat-label">Enabled Features</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $beta_count; ?></div>
                    <div class="stat-label">Beta Features</div>
                </div>
            </div>
            
            <div class="card">
                <h3 class="card-title">All Features</h3>
                
                <?php if (empty($features)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No features found. Add your first feature!</p>
                <?php else: ?>
                    <div class="features-grid">
                        <?php foreach ($features as $feature): ?>
                        <div class="feature-item">
                            <div class="feature-header">
                                <div class="feature-info">
                                    <h4><?php echo htmlspecialchars($feature['feature_name']); ?></h4>
                                    <p><code><?php echo htmlspecialchars($feature['feature_key']); ?></code></p>
                                    <?php if ($feature['description']): ?>
                                        <p style="margin-top:10px;color:#475569"><?php echo htmlspecialchars($feature['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="feature-meta">
                                        Created: <?php echo date('d M Y', strtotime($feature['created_at'])); ?>
                                        <?php if ($feature['updated_at']): ?>
                                            • Updated: <?php echo date('d M Y', strtotime($feature['updated_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="feature-actions">
                                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span style="font-size:12px;color:#64748b">Enabled:</span>
                                            <form method="POST" style="margin:0">
                                                <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                <input type="hidden" name="is_enabled" value="<?php echo $feature['is_enabled'] ? 0 : 1; ?>">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" <?php echo $feature['is_enabled'] ? 'checked' : ''; ?> onchange="this.form.submit()" style="<?php echo $feature['is_enabled'] ? '' : ''; ?>">
                                                    <input type="hidden" name="toggle_feature" value="1">
                                                    <span class="toggle-slider" <?php echo $feature['is_enabled'] ? 'style="background-color:#10b981"' : ''; ?>></span>
                                                </label>
                                            </form>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span style="font-size:12px;color:#64748b">Beta:</span>
                                            <form method="POST" style="margin:0">
                                                <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                <input type="hidden" name="is_beta" value="<?php echo $feature['is_beta'] ? 0 : 1; ?>">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" <?php echo $feature['is_beta'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                    <input type="hidden" name="toggle_beta" value="1">
                                                    <span class="toggle-slider" <?php echo $feature['is_enabled'] ? 'style="background-color:#10b981"' : ''; ?>></span>
                                                </label>
                                            </form>
                                        </div>
                                        <div style="display:flex;gap:5px;margin-top:8px">
                                            <button onclick='openEditModal(<?php echo json_encode($feature); ?>)' class="btn btn-success btn-small">✏️ Edit</button>
                                            <a href="?action=delete&id=<?php echo $feature['id']; ?>" 
                                               onclick="return confirm('Delete this feature?')" 
                                               class="btn btn-danger btn-small">🗑️</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Add Feature Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Add New Feature</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Feature Key *</label>
                        <input type="text" name="feature_key" class="form-input" placeholder="payment_gateway" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Feature Name *</label>
                        <input type="text" name="feature_name" class="form-input" placeholder="Payment Gateway Integration" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Feature description..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_enabled" value="1">
                            <span>Enable this feature</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_beta" value="1">
                            <span>Mark as Beta</span>
                        </label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="add_feature" class="btn btn-primary">➕ Add Feature</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Feature Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Feature</h3>
            <form method="POST">
                <input type="hidden" name="feature_id" id="editFeatureId">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Feature Key</label>
                        <input type="text" id="editFeatureKey" class="form-input" disabled style="background:#f1f5f9">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Feature Name *</label>
                        <input type="text" name="feature_name" id="editFeatureName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-textarea"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_enabled" id="editIsEnabled" value="1">
                            <span>Enable this feature</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_beta" id="editIsBeta" value="1">
                            <span>Mark as Beta</span>
                        </label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="update_feature" class="btn btn-success">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        function openEditModal(feature) {
            document.getElementById('editFeatureId').value = feature.id;
            document.getElementById('editFeatureKey').value = feature.feature_key;
            document.getElementById('editFeatureName').value = feature.feature_name;
            document.getElementById('editDescription').value = feature.description || '';
            document.getElementById('editIsEnabled').checked = feature.is_enabled == 1;
            document.getElementById('editIsBeta').checked = feature.is_beta == 1;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".toggle-switch input[type=checkbox]").forEach(function(cb) {
        if(cb.checked) {
            cb.nextElementSibling.style.backgroundColor = "#10b981";
            cb.nextElementSibling.querySelector(":before") && (cb.nextElementSibling.style.transform = "translateX(24px)");
        }
    });
});
</script>
</body>
</html>
