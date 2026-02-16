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

// Handle GST settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gst'])) {
    $gst_number = strtoupper(sanitizeInput($_POST['gst_number'] ?? ''));
    $legal_name = sanitizeInput($_POST['legal_name'] ?? '');
    $registered_address = sanitizeInput($_POST['registered_address'] ?? '');
    $state_code = sanitizeInput($_POST['state_code'] ?? '');
    
    // Validate GST number format (15 characters)
    if (strlen($gst_number) !== 15) {
        $errors[] = "GST number must be exactly 15 characters";
    } elseif (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst_number)) {
        $errors[] = "Invalid GST number format";
    } elseif (empty($legal_name)) {
        $errors[] = "Legal name is required";
    } elseif (empty($registered_address)) {
        $errors[] = "Registered address is required";
    } elseif (empty($state_code) || strlen($state_code) !== 2) {
        $errors[] = "Valid state code (2 digits) is required";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Deactivate current active GST
            $stmt = $pdo->prepare("UPDATE gst_settings SET is_active = 0 WHERE is_active = 1");
            $stmt->execute();
            
            // Insert new GST settings
            $stmt = $pdo->prepare("
                INSERT INTO gst_settings (gst_number, legal_name, registered_address, state_code, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmt->execute([$gst_number, $legal_name, $registered_address, $state_code, $admin_name]);
            
            $pdo->commit();
            $success = "GST settings saved successfully!";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to save GST settings: " . $e->getMessage();
            error_log("GST Settings Error: " . $e->getMessage());
        }
    }
}

// Handle delete GST setting
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM gst_settings WHERE id = ? AND is_active = 0");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $success = "GST setting deleted successfully!";
        } else {
            $errors[] = "Cannot delete active GST setting";
        }
    } catch (PDOException $e) {
        $errors[] = "Failed to delete GST setting";
    }
    header('Location: gst-settings.php');
    exit;
}

// Get current active GST settings
try {
    $stmt = $pdo->query("SELECT * FROM gst_settings WHERE is_active = 1 LIMIT 1");
    $active_gst = $stmt->fetch();
    
    // Get all GST settings history
    $stmt = $pdo->query("SELECT * FROM gst_settings ORDER BY created_at DESC");
    $gst_history = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $active_gst = null;
    $gst_history = [];
    error_log("GST Fetch Error: " . $e->getMessage());
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
    <title>GST Settings - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
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
        
        /* Header */
        .page-header{margin-bottom:25px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        
        /* Cards */
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        
        /* Form */
        .form-grid{display:grid;gap:20px}
        .form-group{display:flex;flex-direction:column}
        .form-label{font-size:14px;font-weight:500;color:#475569;margin-bottom:8px}
        .form-label.required::after{content:" *";color:#ef4444}
        .form-input,.form-textarea{padding:12px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:all 0.2s}
        .form-input:focus,.form-textarea:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .form-textarea{resize:vertical;min-height:100px;font-family:inherit}
        .form-help{font-size:12px;color:#64748b;margin-top:5px}
        
        .btn{padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.3)}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-danger:hover{background:#dc2626;transform:translateY(-2px)}
        
        /* Table */
        .data-table{width:100%;border-collapse:collapse;margin-top:20px}
        .data-table th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:13px;font-weight:600;color:#475569;border-bottom:2px solid #e2e8f0}
        .data-table td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        .data-table tr:hover{background:#f8fafc}
        
        .badge-active{background:#dcfce7;color:#166534;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600}
        .badge-inactive{background:#f1f5f9;color:#64748b;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600}
        
        .info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:15px;margin-bottom:20px}
        .info-box h4{color:#1e40af;font-size:14px;margin-bottom:8px}
        .info-box p{color:#1e40af;font-size:13px;line-height:1.6;margin:4px 0}
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">🧾 GST Settings</h1>
                <p class="page-subtitle">Manage GST/Tax configuration for invoices</p>
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
            
            <!-- Current Active GST -->
            <?php if ($active_gst): ?>
            <div class="info-box">
                <h4>📌 Current Active GST Configuration</h4>
                <p><strong>GST Number:</strong> <?php echo htmlspecialchars($active_gst['gst_number']); ?></p>
                <p><strong>Legal Name:</strong> <?php echo htmlspecialchars($active_gst['legal_name']); ?></p>
                <p><strong>State Code:</strong> <?php echo htmlspecialchars($active_gst['state_code']); ?></p>
                <p><strong>Activated on:</strong> <?php echo date('d M Y, h:i A', strtotime($active_gst['created_at'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Add New GST Settings -->
            <div class="card">
                <h3 class="card-title">Add/Update GST Settings</h3>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">GST Number</label>
                            <input type="text" name="gst_number" class="form-input" placeholder="22AAAAA0000A1Z5" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}" required>
                            <span class="form-help">15 character GST number (e.g., 22AAAAA0000A1Z5)</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Legal Name</label>
                            <input type="text" name="legal_name" class="form-input" placeholder="Company Private Limited" required>
                            <span class="form-help">Registered legal name as per GST certificate</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Registered Address</label>
                            <textarea name="registered_address" class="form-textarea" placeholder="Full registered address with pincode" required></textarea>
                            <span class="form-help">Complete address as per GST registration</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">State Code</label>
                            <input type="text" name="state_code" class="form-input" placeholder="22" maxlength="2" pattern="[0-9]{2}" required>
                            <span class="form-help">2 digit state code (e.g., 22 for Chhattisgarh, 09 for Uttar Pradesh)</span>
                        </div>
                    </div>
                    
                    <div style="margin-top:25px">
                        <button type="submit" name="save_gst" class="btn btn-primary">💾 Save GST Settings</button>
                    </div>
                </form>
            </div>
            
            <!-- GST History -->
            <div class="card">
                <h3 class="card-title">GST Configuration History</h3>
                
                <?php if (empty($gst_history)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No GST settings found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>GST Number</th>
                                <th>Legal Name</th>
                                <th>State Code</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gst_history as $gst): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($gst['gst_number']); ?></td>
                                <td><?php echo htmlspecialchars($gst['legal_name']); ?></td>
                                <td><?php echo htmlspecialchars($gst['state_code']); ?></td>
                                <td><?php echo htmlspecialchars($gst['created_by']); ?></td>
                                <td><?php echo date('d M Y', strtotime($gst['created_at'])); ?></td>
                                <td>
                                    <?php if ($gst['is_active']): ?>
                                        <span class="badge-active">✓ Active</span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$gst['is_active']): ?>
                                        <a href="?action=delete&id=<?php echo $gst['id']; ?>" 
                                           onclick="return confirm('Delete this GST setting?')" 
                                           class="btn btn-danger" style="font-size:12px;padding:6px 12px">🗑️ Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
