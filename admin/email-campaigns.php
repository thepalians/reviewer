<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email-marketing-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $template_id = (int)($_POST['template_id'] ?? 0);
            $audience = $_POST['audience'] ?? 'all';
            $scheduled_at = $_POST['scheduled_at'] ?? null;
            
            if (empty($name) || empty($subject)) {
                $error = 'Name and subject are required';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO email_campaigns (name, subject, template_id, segment_type, status, scheduled_at, created_by, created_at) VALUES (?, ?, ?, ?, 'draft', ?, ?, NOW())");
                    $stmt->execute([$name, $subject, $template_id > 0 ? $template_id : null, $audience, $scheduled_at, $admin_id]);
                    $success = 'Campaign created successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to create campaign: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'send') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE email_campaigns SET status = 'sending', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Campaign queued for sending';
                } catch (PDOException $e) {
                    $error = 'Failed to send campaign';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM email_campaigns WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Campaign deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete campaign';
                }
            }
        }
    }
}

// Get campaigns
$campaigns = [];
$stats = ['total' => 0, 'draft' => 0, 'sent' => 0, 'scheduled' => 0];
try {
    $stmt = $pdo->query("
        SELECT c.*, et.name as template_name, u.username as creator_name
        FROM email_campaigns c
        LEFT JOIN email_templates et ON c.template_id = et.id
        LEFT JOIN users u ON c.created_by = u.id
        ORDER BY c.created_at DESC
    ");
    $campaigns = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
        FROM email_campaigns
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get email templates for dropdown
$templates = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM email_templates WHERE is_active = 1 ORDER BY name ASC");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail
}

$csrf_token = generateCSRFToken();
$current_page = 'email-campaigns';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaigns - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h2{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:20px}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px;transition:all 0.3s}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .sidebar .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px}
        .sidebar-divider{border-top:1px solid rgba(255,255,255,0.1);margin:15px 0}
        .menu-section-label{color:#888;font-size:11px;text-transform:uppercase;padding:10px 15px;font-weight:600}
        .content{padding:30px}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.total .val{color:#667eea}.stat.draft .val{color:#f39c12}.stat.sent .val{color:#27ae60}.stat.scheduled .val{color:#3498db}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-sm{padding:6px 12px;font-size:13px}
        table{width:100%;border-collapse:collapse}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;font-size:13px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
        table tr:hover{background:#f8f9fa}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.danger{background:#f8d7da;color:#721c24}
        .badge.warning{background:#fff3cd;color:#856404}
        .badge.info{background:#d1ecf1;color:#0c5460}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-header{padding:20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
        .modal-body{padding:20px}
        .modal-footer{padding:20px;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📧 Email Campaigns</h4>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Create Campaign</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat total">
                <div class="val"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="lbl">Total Campaigns</div>
            </div>
            <div class="stat draft">
                <div class="val"><?php echo number_format($stats['draft'] ?? 0); ?></div>
                <div class="lbl">Draft</div>
            </div>
            <div class="stat sent">
                <div class="val"><?php echo number_format($stats['sent'] ?? 0); ?></div>
                <div class="lbl">Sent</div>
            </div>
            <div class="stat scheduled">
                <div class="val"><?php echo number_format($stats['scheduled'] ?? 0); ?></div>
                <div class="lbl">Scheduled</div>
            </div>
        </div>

        <!-- Campaigns Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Template</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Sent/Total</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999">No campaigns found</td></tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr>
                                <td>#<?php echo $campaign['id']; ?></td>
                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                <td><?php echo $campaign['template_name'] ? htmlspecialchars($campaign['template_name']) : '<em>None</em>'; ?></td>
                                <td>
                                    <?php 
                                    $audience_labels = [
                                        'all' => 'All Users',
                                        'active' => 'Active Users',
                                        'inactive' => 'Inactive Users',
                                        'new' => 'New Users',
                                        'custom' => 'Custom'
                                    ];
                                    echo $audience_labels[$campaign['segment_type']] ?? ucfirst($campaign['segment_type']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_classes = [
                                        'draft' => 'warning',
                                        'scheduled' => 'info',
                                        'sending' => 'info',
                                        'sent' => 'success',
                                        'failed' => 'danger'
                                    ];
                                    $badge_class = $status_classes[$campaign['status']] ?? 'info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($campaign['status']); ?></span>
                                </td>
                                <td><?php echo number_format($campaign['sent_count'] ?? 0); ?> / <?php echo number_format($campaign['total_recipients'] ?? 0); ?></td>
                                <td><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></td>
                                <td>
                                    <?php if ($campaign['status'] === 'draft'): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Send this campaign now?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="send">
                                            <input type="hidden" name="id" value="<?php echo $campaign['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Send</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this campaign?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $campaign['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Campaign Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5>Create Email Campaign</h5>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label>Campaign Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Subject *</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Template</label>
                    <select name="template_id" class="form-control">
                        <option value="0">-- Custom Email --</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="audience" class="form-control">
                        <option value="all">All Users</option>
                        <option value="active">Active Users</option>
                        <option value="inactive">Inactive Users</option>
                        <option value="new">New Users</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Schedule For (Optional)</label>
                    <input type="datetime-local" name="scheduled_at" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Campaign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>
</body>
</html>
