<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/proof-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Get user's tasks that need proof submission
try {
    $tasks_query = "
        SELECT DISTINCT t.id, t.title, t.amount, t.assigned_date
        FROM tasks t
        JOIN orders o ON t.id = o.task_id
        WHERE t.user_id = ? 
        AND o.step3_status = 'approved'
        AND NOT EXISTS (
            SELECT 1 FROM task_proofs tp WHERE tp.task_id = t.id AND tp.user_id = ?
        )
        ORDER BY t.assigned_date DESC
    ";
    $tasks_stmt = $pdo->prepare($tasks_query);
    $tasks_stmt->execute([$user_id, $user_id]);
    $available_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_tasks = [];
}

// Handle proof submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proof'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $task_id = filter_input(INPUT_POST, 'task_id', FILTER_SANITIZE_NUMBER_INT);
        $proof_type = $_POST['proof_type'] ?? '';
        
        // Validate proof type
        $valid_proof_types = ['screenshot', 'order_id', 'review_link'];
        if (!in_array($proof_type, $valid_proof_types)) {
            $error = 'Invalid proof type';
        } else {
            $proof_text = $_POST['proof_text'] ?? '';
            
            $proof_file = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $proof_file = $_FILES['proof_file'];
            }
            
            try {
                $result = submitProof($pdo, $user_id, $task_id, $proof_type, $proof_file, $proof_text);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        }
    }
}

// Get user's submitted proofs
try {
    $proofs = getUserProofs($pdo, $user_id, 20);
} catch (PDOException $e) {
    $proofs = [];
}

// Set current page for sidebar
$current_page = 'submit-proof';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Task Proof - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
    /* Sidebar Styles */
    .sidebar {
        width: 260px;
        position: fixed;
        left: 0;
        top: 60px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        overflow-y: auto;
        transition: all 0.3s ease;
        z-index: 999;
    }
    .sidebar-header {
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header h2 {
        color: #fff;
        font-size: 18px;
        margin: 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu li {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-menu li a {
        display: block;
        padding: 15px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
        padding-left: 25px;
    }
    .sidebar-menu li a.active {
        background: linear-gradient(90deg, rgba(66,153,225,0.2) 0%, transparent 100%);
        color: #4299e1;
        border-left: 3px solid #4299e1;
    }
    .sidebar-menu li a.logout {
        color: #fc8181;
    }
    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 10px 0;
    }
    .menu-section-label {
        padding: 15px 20px 5px;
        color: rgba(255,255,255,0.5);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .badge {
        background: #e53e3e;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 8px;
    }
    .admin-layout {
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 60px);
    }
    @media (max-width: 768px) {
        .sidebar {
            left: -260px;
        }
        .sidebar.active {
            left: 0;
        }
        .admin-layout {
            margin-left: 0;
        }
    }
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid mt-4">
            <h2 class="mb-4"><i class="bi bi-file-earmark-check"></i> Submit Task Proof</h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Submit Proof Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-upload"></i> Upload New Proof</h5>
                </div>
                <div class="card-body">
                    <?php if (count($available_tasks) > 0): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="task_id" class="form-label">Select Task *</label>
                                <select class="form-select" id="task_id" name="task_id" required>
                                    <option value="">Choose a task...</option>
                                    <?php foreach ($available_tasks as $task): ?>
                                    <option value="<?php echo $task['id']; ?>">
                                        <?php echo htmlspecialchars($task['title']); ?> (₹<?php echo $task['amount']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="proof_type" class="form-label">Proof Type *</label>
                                <select class="form-select" id="proof_type" name="proof_type" required onchange="toggleProofFields()">
                                    <option value="">Choose type...</option>
                                    <option value="screenshot">Screenshot</option>
                                    <option value="order_id">Order ID</option>
                                    <option value="review_link">Review Link</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3" id="fileUploadDiv" style="display: none;">
                            <label for="proof_file" class="form-label">Upload Screenshot *</label>
                            <input type="file" class="form-control" id="proof_file" name="proof_file" accept="image/*">
                            <div class="form-text">Accepted formats: JPG, PNG, WEBP. Max size: 5MB</div>
                        </div>

                        <div class="mb-3" id="textInputDiv" style="display: none;">
                            <label for="proof_text" class="form-label">Enter Details *</label>
                            <textarea class="form-control" id="proof_text" name="proof_text" rows="3" 
                                placeholder="Enter Order ID or Review Link"></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Note:</strong> 
                            Submit clear proof of task completion. Screenshots should show order details, review submission, or refund status.
                        </div>

                        <button type="submit" name="submit_proof" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Submit Proof
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No tasks available for proof submission. 
                            Complete review submission step first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submitted Proofs -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Submitted Proofs</h5>
                </div>
                <div class="card-body">
                    <?php if (count($proofs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Proof Type</th>
                                    <th>Submitted Date</th>
                                    <th>AI Score</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proofs as $proof): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($proof['task_title']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($proof['proof_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($proof['created_at'])); ?></td>
                                    <td>
                                        <?php if ($proof['ai_score']): ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?php 
                                                    echo $proof['ai_score'] >= 80 ? 'bg-success' : 
                                                        ($proof['ai_score'] >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                ?>" role="progressbar" 
                                                    style="width: <?php echo $proof['ai_score']; ?>%">
                                                    <?php echo number_format($proof['ai_score'], 1); ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        $status_text = ucfirst(str_replace('_', ' ', $proof['status']));
                                        
                                        if ($proof['status'] == 'approved' || $proof['status'] == 'auto_approved') {
                                            $badge_class = 'success';
                                        } elseif ($proof['status'] == 'rejected') {
                                            $badge_class = 'danger';
                                        } elseif ($proof['status'] == 'manual_review') {
                                            $badge_class = 'warning';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($proof['proof_file']): ?>
                                            <a href="../<?php echo htmlspecialchars($proof['proof_file']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($proof['rejection_reason']): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="alert('Rejection Reason: <?php echo addslashes($proof['rejection_reason']); ?>')">
                                                <i class="bi bi-info-circle"></i> Reason
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-x" style="font-size: 4rem; color: #ccc;"></i>
                            <h4 class="mt-3">No Proofs Submitted</h4>
                            <p class="text-muted">Submit proof after completing tasks to get verified and paid!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleProofFields() {
    const proofType = document.getElementById('proof_type').value;
    const fileDiv = document.getElementById('fileUploadDiv');
    const textDiv = document.getElementById('textInputDiv');
    const fileInput = document.getElementById('proof_file');
    const textInput = document.getElementById('proof_text');
    
    // Reset
    fileDiv.style.display = 'none';
    textDiv.style.display = 'none';
    fileInput.required = false;
    textInput.required = false;
    
    if (proofType === 'screenshot') {
        fileDiv.style.display = 'block';
        fileInput.required = true;
    } else if (proofType === 'order_id' || proofType === 'review_link') {
        textDiv.style.display = 'block';
        textInput.required = true;
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
