<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket-functions.php';

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
        
        if ($action === 'assign') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            if ($ticket_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$admin_id, $ticket_id]);
                    $success = 'Ticket assigned to you';
                } catch (PDOException $e) {
                    $error = 'Failed to assign ticket';
                }
            }
        } elseif ($action === 'close') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            if ($ticket_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$ticket_id]);
                    $success = 'Ticket closed';
                } catch (PDOException $e) {
                    $error = 'Failed to close ticket';
                }
            }
        } elseif ($action === 'reopen') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            if ($ticket_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'open', closed_at = NULL, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$ticket_id]);
                    $success = 'Ticket reopened';
                } catch (PDOException $e) {
                    $error = 'Failed to reopen ticket';
                }
            }
        }
    }
}

// Get filter
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

// Get tickets
$tickets = [];
$stats = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'closed' => 0, 'high_priority' => 0];
try {
    $query = "SELECT t.*, u.username, u.email as user_email,
              a.username as assigned_name
              FROM support_tickets t
              LEFT JOIN users u ON t.user_id = u.id
              LEFT JOIN users a ON t.assigned_to = a.id
              WHERE 1=1";
    
    $params = [];
    if ($status_filter !== 'all') {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    if ($priority_filter !== 'all') {
        $query .= " AND t.priority = ?";
        $params[] = $priority_filter;
    }
    
    $query .= " ORDER BY t.priority DESC, t.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
        FROM support_tickets
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'tickets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Admin Panel</title>
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
        .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.total .val{color:#667eea}.stat.open .val{color:#3498db}.stat.progress .val{color:#f39c12}.stat.closed .val{color:#27ae60}.stat.high .val{color:#e74c3c}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s;text-decoration:none;display:inline-block}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3;color:#fff}
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
        .filter-bar{background:#fff;padding:15px;border-radius:10px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .priority-high{color:#e74c3c;font-weight:bold}
        .priority-medium{color:#f39c12}
        .priority-low{color:#95a5a6}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>🎫 Support Tickets</h4>
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
                <div class="lbl">Total Tickets</div>
            </div>
            <div class="stat open">
                <div class="val"><?php echo number_format($stats['open'] ?? 0); ?></div>
                <div class="lbl">Open</div>
            </div>
            <div class="stat progress">
                <div class="val"><?php echo number_format($stats['in_progress'] ?? 0); ?></div>
                <div class="lbl">In Progress</div>
            </div>
            <div class="stat closed">
                <div class="val"><?php echo number_format($stats['closed'] ?? 0); ?></div>
                <div class="lbl">Closed</div>
            </div>
            <div class="stat high">
                <div class="val"><?php echo number_format($stats['high_priority'] ?? 0); ?></div>
                <div class="lbl">High Priority</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <span><strong>Filter by:</strong></span>
            <select onchange="location.href='?status='+this.value+'&priority=<?php echo $priority_filter; ?>'" style="padding:8px;border-radius:6px;border:1px solid #ddd">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <select onchange="location.href='?status=<?php echo $status_filter; ?>&priority='+this.value" style="padding:8px;border-radius:6px;border:1px solid #ddd">
                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999">No tickets found</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>#<?php echo $ticket['id']; ?></td>
                                <td>
                                    <a href="ticket-view.php?id=<?php echo $ticket['id']; ?>" style="color:#667eea;text-decoration:none">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                                <td><?php echo ucfirst($ticket['category']); ?></td>
                                <td>
                                    <span class="priority-<?php echo $ticket['priority']; ?>">
                                        <?php echo strtoupper($ticket['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_classes = [
                                        'open' => 'info',
                                        'in_progress' => 'warning',
                                        'closed' => 'success'
                                    ];
                                    $badge_class = $status_classes[$ticket['status']] ?? 'info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?></span>
                                </td>
                                <td><?php echo $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<em>Unassigned</em>'; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <a href="ticket-view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    <?php if ($ticket['status'] !== 'closed' && !$ticket['assigned_to']): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="assign">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Assign</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($ticket['status'] !== 'closed'): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Close this ticket?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="close">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Close</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="reopen">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Reopen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
