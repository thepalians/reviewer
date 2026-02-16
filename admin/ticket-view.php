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

$ticket_id = (int)($_GET['id'] ?? 0);

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'reply') {
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                $error = 'Message is required';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $stmt->execute([$ticket_id, $admin_id, $message]);
                    
                    // Update ticket
                    $stmt = $pdo->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$ticket_id]);
                    
                    $success = 'Reply added successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to add reply';
                }
            }
        } elseif ($action === 'change_status') {
            $new_status = $_POST['status'] ?? '';
            
            if (in_array($new_status, ['open', 'in_progress', 'closed'])) {
                try {
                    $closed_at = $new_status === 'closed' ? 'NOW()' : 'NULL';
                    $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, closed_at = $closed_at, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_status, $ticket_id]);
                    
                    $success = 'Status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update status';
                }
            }
        } elseif ($action === 'change_priority') {
            $new_priority = $_POST['priority'] ?? '';
            
            if (in_array($new_priority, ['low', 'medium', 'high'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE support_tickets SET priority = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_priority, $ticket_id]);
                    
                    $success = 'Priority updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update priority';
                }
            }
        } elseif ($action === 'assign') {
            try {
                $stmt = $pdo->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$admin_id, $ticket_id]);
                
                $success = 'Ticket assigned to you';
            } catch (PDOException $e) {
                $error = 'Failed to assign ticket';
            }
        }
    }
}

// Get ticket details
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.email as user_email,
               a.username as assigned_name
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Database error';
}

// Get ticket replies
$replies = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM ticket_replies r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.ticket_id = ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $replies = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail
}

$csrf_token = generateCSRFToken();
$current_page = 'tickets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket_id; ?> - Admin Panel</title>
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
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s;text-decoration:none;display:inline-block}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3;color:#fff}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-secondary{background:#6c757d;color:#fff}.btn-secondary:hover{background:#5a6268}
        .btn-sm{padding:6px 12px;font-size:13px}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.danger{background:#f8d7da;color:#721c24}
        .badge.warning{background:#fff3cd;color:#856404}
        .badge.info{background:#d1ecf1;color:#0c5460}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .ticket-details{display:grid;grid-template-columns:1fr 300px;gap:20px}
        .ticket-main{}
        .ticket-sidebar{}
        .info-item{margin-bottom:15px}
        .info-item label{display:block;font-weight:600;margin-bottom:5px;font-size:13px;color:#666}
        .info-item .value{font-size:14px}
        .reply{background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:15px}
        .reply.admin{background:#e3f2fd}
        .reply-header{display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px}
        .reply-header .author{font-weight:600}
        .reply-header .time{color:#999}
        .reply-message{font-size:14px;line-height:1.6}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:120px;resize:vertical}
        select.form-control{cursor:pointer}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div style="margin-bottom:20px">
            <a href="tickets.php" class="btn btn-secondary">← Back to Tickets</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="ticket-details">
            <!-- Main Content -->
            <div class="ticket-main">
                <div class="card">
                    <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                    <p style="color:#666;font-size:13px;margin-top:10px">
                        Ticket #<?php echo $ticket_id; ?> • Created <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                    </p>
                    <hr style="margin:20px 0">
                    <div class="reply-message">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>
                </div>

                <!-- Replies -->
                <div class="card">
                    <h5>Replies (<?php echo count($replies); ?>)</h5>
                    <hr>
                    
                    <?php if (empty($replies)): ?>
                        <p style="color:#999;text-align:center;padding:20px">No replies yet</p>
                    <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                            <div class="reply <?php echo $reply['is_internal'] ? 'admin' : ''; ?>">
                                <div class="reply-header">
                                    <span class="author">
                                        <?php echo $reply['is_internal'] ? '👨‍💼 ' : '👤 '; ?>
                                        <?php echo htmlspecialchars($reply['username']); ?>
                                        <?php echo $reply['is_internal'] ? '<small>(Admin)</small>' : ''; ?>
                                    </span>
                                    <span class="time"><?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?></span>
                                </div>
                                <div class="reply-message">
                                    <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Reply Form -->
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="card">
                        <h5>Add Reply</h5>
                        <hr>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="reply">
                            <div class="form-group">
                                <label>Your Reply</label>
                                <textarea name="message" class="form-control" required placeholder="Type your reply here..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Reply</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="ticket-sidebar">
                <div class="card">
                    <h5>Ticket Information</h5>
                    <hr>
                    
                    <div class="info-item">
                        <label>Status</label>
                        <div class="value">
                            <?php
                            $status_classes = [
                                'open' => 'info',
                                'in_progress' => 'warning',
                                'closed' => 'success'
                            ];
                            $badge_class = $status_classes[$ticket['status']] ?? 'info';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?></span>
                        </div>
                    </div>

                    <div class="info-item">
                        <label>Priority</label>
                        <div class="value">
                            <span style="color:<?php echo $ticket['priority'] === 'high' ? '#e74c3c' : ($ticket['priority'] === 'medium' ? '#f39c12' : '#95a5a6'); ?>;font-weight:bold">
                                <?php echo strtoupper($ticket['priority']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <label>Category</label>
                        <div class="value"><?php echo ucfirst($ticket['category']); ?></div>
                    </div>

                    <div class="info-item">
                        <label>User</label>
                        <div class="value">
                            <?php echo htmlspecialchars($ticket['username']); ?><br>
                            <small style="color:#999"><?php echo htmlspecialchars($ticket['user_email']); ?></small>
                        </div>
                    </div>

                    <div class="info-item">
                        <label>Assigned To</label>
                        <div class="value">
                            <?php echo $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<em>Unassigned</em>'; ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Actions -->
                    <?php if (!$ticket['assigned_to'] && $ticket['status'] !== 'closed'): ?>
                        <form method="post" style="margin-bottom:10px">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="assign">
                            <button type="submit" class="btn btn-success" style="width:100%">Assign to Me</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($ticket['status'] !== 'closed'): ?>
                        <form method="post" style="margin-bottom:10px">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="change_status">
                            <div class="form-group">
                                <label>Change Status</label>
                                <select name="status" class="form-control" onchange="this.form.submit()">
                                    <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                        </form>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="change_priority">
                            <div class="form-group">
                                <label>Change Priority</label>
                                <select name="priority" class="form-control" onchange="this.form.submit()">
                                    <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $ticket['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
