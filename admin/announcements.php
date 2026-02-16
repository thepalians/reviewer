<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/announcement-functions.php';

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
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $audience = $_POST['audience'] ?? 'all';
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title) || empty($message)) {
                $error = 'Title and message are required';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target_audience, is_active, start_date, end_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$title, $message, $audience, $is_active, $start_date, $end_date, $admin_id]);
                    $success = 'Announcement created successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to create announcement';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $audience = $_POST['audience'] ?? 'all';
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title) || empty($message) || $id <= 0) {
                $error = 'Invalid data';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE announcements SET title = ?, message = ?, target_audience = ?, is_active = ?, start_date = ?, end_date = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$title, $message, $audience, $is_active, $start_date, $end_date, $id]);
                    $success = 'Announcement updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update announcement';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Announcement deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete announcement';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update status';
                }
            }
        }
    }
}

// Get filter
$filter = $_GET['audience'] ?? 'all';

// Get announcements with try-catch
$announcements = [];
$stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'all_users' => 0];
try {
    $query = "SELECT * FROM announcements WHERE 1=1";
    if ($filter !== 'all') {
        $query .= " AND target_audience = :audience";
    }
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    if ($filter !== 'all') {
        $stmt->execute([':audience' => $filter]);
    } else {
        $stmt->execute();
    }
    $announcements = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM announcements");
    $stats_row = $stmt->fetch();
    $stats['total'] = (int)$stats_row['total'];
    $stats['active'] = (int)$stats_row['active'];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM announcements WHERE end_date IS NOT NULL AND end_date < CURDATE()");
    $stats['expired'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM announcements WHERE target_audience = 'all'");
    $stats['all_users'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin Panel</title>
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
        .stat.p .val{color:#667eea}.stat.s .val{color:#27ae60}.stat.w .val{color:#f39c12}.stat.d .val{color:#e74c3c}
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
        .filter-bar{background:#fff;padding:15px;border-radius:10px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap}
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
        textarea.form-control{min-height:100px;resize:vertical}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
        .text-truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📢 Announcements</h4>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Create Announcement</button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat p">
                <div class="val"><?php echo $stats['total']; ?></div>
                <div class="lbl">Total Announcements</div>
            </div>
            <div class="stat s">
                <div class="val"><?php echo $stats['active']; ?></div>
                <div class="lbl">Active</div>
            </div>
            <div class="stat d">
                <div class="val"><?php echo $stats['expired']; ?></div>
                <div class="lbl">Expired</div>
            </div>
            <div class="stat w">
                <div class="val"><?php echo $stats['all_users']; ?></div>
                <div class="lbl">All Users</div>
            </div>
        </div>
        
        <div class="filter-bar">
            <strong>Filter by Audience:</strong>
            <a href="?audience=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-warning'; ?>">All</a>
            <a href="?audience=users" class="btn btn-sm <?php echo $filter === 'users' ? 'btn-primary' : 'btn-warning'; ?>">Users</a>
            <a href="?audience=sellers" class="btn btn-sm <?php echo $filter === 'sellers' ? 'btn-primary' : 'btn-warning'; ?>">Sellers</a>
            <a href="?audience=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-warning'; ?>">All Roles</a>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Audience</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="7" style="text-align:center;color:#999">No announcements found</td></tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <tr>
                                <td><strong><?php echo escape($ann['title']); ?></strong></td>
                                <td><div class="text-truncate"><?php echo escape($ann['message']); ?></div></td>
                                <td><span class="badge info"><?php echo escape($ann['target_audience']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></td>
                                <td><?php echo $ann['end_date'] ? date('M d, Y', strtotime($ann['end_date'])) : 'Never'; ?></td>
                                <td>
                                    <?php if ($ann['is_active']): ?>
                                        <span class="badge success">Active</span>
                                    <?php else: ?>
                                        <span class="badge danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick='editAnnouncement(<?php echo json_encode($ann); ?>)'>Edit</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this announcement?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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

<!-- Create/Edit Modal -->
<div id="announcementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 id="modalTitle">Create Announcement</h5>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="announcementForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="announcementId">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" id="message" class="form-control" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="audience" id="audience" class="form-control">
                        <option value="all">All Users & Sellers</option>
                        <option value="users">Users Only</option>
                        <option value="sellers">Sellers Only</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control">
                </div>
                <div class="mb-3">
                    <label>End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Announcement';
    document.getElementById('formAction').value = 'create';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcementId').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('announcementModal').classList.add('show');
}

function editAnnouncement(ann) {
    document.getElementById('modalTitle').textContent = 'Edit Announcement';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('announcementId').value = ann.id;
    document.getElementById('title').value = ann.title;
    document.getElementById('message').value = ann.message;
    document.getElementById('audience').value = ann.target_audience;
    document.getElementById('start_date').value = ann.start_date || '';
    document.getElementById('end_date').value = ann.end_date || '';
    document.getElementById('is_active').checked = ann.is_active == 1;
    document.getElementById('announcementModal').classList.add('show');
}

function closeModal() {
    document.getElementById('announcementModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target == document.getElementById('announcementModal')) {
        closeModal();
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
