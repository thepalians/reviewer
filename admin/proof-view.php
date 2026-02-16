<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/proof-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['admin_name'];
$proof_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$proof_id) {
    header('Location: verify-proofs.php');
    exit;
}

// Get proof details
try {
    $proof = getProofDetails($pdo, $proof_id);
} catch (PDOException $e) {
    $proof = null;
}

if (!$proof) {
    die('Proof not found');
}

// Get verification history
try {
    $history = getVerificationHistory($pdo, $proof_id);
} catch (PDOException $e) {
    $history = [];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        if (isset($_POST['approve'])) {
            try {
                approveProof($pdo, $proof_id, $admin_id);
                header('Location: verify-proofs.php?msg=approved');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        }
        
        if (isset($_POST['reject'])) {
            $reason = $_POST['reason'] ?? '';
            try {
                rejectProof($pdo, $proof_id, $admin_id, $reason);
                header('Location: verify-proofs.php?msg=rejected');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        }
    }
}

// Set current page for sidebar
$current_page = 'proof-view';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proof Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
/* Admin Layout */
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}

/* Sidebar styles */
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
.sidebar-menu{list-style:none;padding:15px 0}
.sidebar-menu li{margin-bottom:5px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
.sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
.sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
.menu-section-label{padding:8px 20px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
.sidebar-menu a.logout{color:#e74c3c}

/* Main Content */
.main-content{padding:25px;overflow-x:hidden}
</style>
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
            <div class="mb-3">
                <a href="verify-proofs.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>

            <h2 class="mb-4"><i class="bi bi-file-earmark-check-fill"></i> Proof Details</h2>

            <!-- Proof Information -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <!-- Proof Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <h5>Proof #<?php echo $proof['id']; ?></h5>
                            <span class="badge bg-<?php 
                                $status_colors = [
                                    'pending' => 'warning',
                                    'manual_review' => 'info',
                                    'approved' => 'success',
                                    'auto_approved' => 'primary',
                                    'rejected' => 'danger'
                                ];
                                echo $status_colors[$proof['status']] ?? 'secondary';
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $proof['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if ($proof['proof_file']): ?>
                                <div class="text-center mb-3">
                                    <img src="../<?php echo htmlspecialchars($proof['proof_file']); ?>" 
                                         class="img-fluid" style="max-height: 500px; border: 1px solid #ddd;" 
                                         alt="Proof Screenshot">
                                    <div class="mt-2">
                                        <a href="../<?php echo htmlspecialchars($proof['proof_file']); ?>" 
                                           target="_blank" class="btn btn-primary">
                                            <i class="bi bi-arrows-fullscreen"></i> View Full Size
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($proof['proof_text']): ?>
                                <div class="alert alert-info">
                                    <strong>Proof Text:</strong>
                                    <pre class="mb-0 mt-2"><?php echo htmlspecialchars($proof['proof_text']); ?></pre>
                                </div>
                            <?php endif; ?>

                            <?php if ($proof['rejection_reason']): ?>
                                <div class="alert alert-danger">
                                    <strong>Rejection Reason:</strong>
                                    <p class="mb-0 mt-2"><?php echo htmlspecialchars($proof['rejection_reason']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI Analysis -->
                    <?php if ($proof['ai_result']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="bi bi-robot"></i> AI Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Confidence Score:</strong>
                                <div class="progress mt-2" style="height: 30px;">
                                    <div class="progress-bar <?php 
                                        echo $proof['ai_score'] >= 80 ? 'bg-success' : 
                                            ($proof['ai_score'] >= 50 ? 'bg-warning' : 'bg-danger'); 
                                    ?>" style="width: <?php echo $proof['ai_score']; ?>%">
                                        <?php echo number_format($proof['ai_score'], 1); ?>%
                                    </div>
                                </div>
                            </div>

                            <?php
                            $ai_details = json_decode($proof['ai_result'], true);
                            if ($ai_details): 
                            ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Recommendation:</th>
                                        <td><?php echo htmlspecialchars($ai_details['recommendation'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php if (isset($ai_details['is_screenshot'])): ?>
                                    <tr>
                                        <th>Screenshot Detected:</th>
                                        <td>
                                            <?php echo $ai_details['is_screenshot'] ? 
                                                '<span class="badge bg-success">Yes</span>' : 
                                                '<span class="badge bg-warning">No</span>'; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($ai_details['keyword_matches'])): ?>
                                    <tr>
                                        <th>Keywords Found:</th>
                                        <td><?php echo $ai_details['keyword_matches']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($ai_details['image_width']) && isset($ai_details['image_height'])): ?>
                                    <tr>
                                        <th>Image Dimensions:</th>
                                        <td><?php echo $ai_details['image_width']; ?> x <?php echo $ai_details['image_height']; ?> pixels</td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Side Panel -->
                <div class="col-md-4">
                    <!-- User & Task Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="bi bi-info-circle"></i> Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th>User:</th>
                                    <td>
                                        <strong><?php echo htmlspecialchars($proof['username']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($proof['email']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Task:</th>
                                    <td><?php echo htmlspecialchars($proof['task_title']); ?></td>
                                </tr>
                                <tr>
                                    <th>Proof Type:</th>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($proof['proof_type']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Submitted:</th>
                                    <td><?php echo date('M d, Y H:i', strtotime($proof['created_at'])); ?></td>
                                </tr>
                                <?php if ($proof['verified_at']): ?>
                                <tr>
                                    <th>Verified:</th>
                                    <td>
                                        <?php echo date('M d, Y H:i', strtotime($proof['verified_at'])); ?><br>
                                        <small class="text-muted">by <?php echo htmlspecialchars($proof['verifier_name'] ?? 'System'); ?></small>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <?php if (in_array($proof['status'], ['pending', 'manual_review'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="bi bi-gear"></i> Actions</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button type="submit" name="approve" class="btn btn-success w-100 mb-2"
                                        onclick="return confirm('Approve this proof?')">
                                    <i class="bi bi-check-circle"></i> Approve Proof
                                </button>
                            </form>

                            <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle"></i> Reject Proof
                            </button>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Proof</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Rejection Reason *</label>
                                                    <textarea class="form-control" name="reason" rows="4" required
                                                              placeholder="Provide a clear reason for rejection..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reject" class="btn btn-danger">Reject Proof</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Verification History -->
                    <?php if (count($history) > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="bi bi-clock-history"></i> Verification History</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($history as $entry): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo ucfirst($entry['verification_type']); ?></strong>
                                        <small class="text-muted">
                                            <?php echo date('M d, H:i', strtotime($entry['created_at'])); ?>
                                        </small>
                                    </div>
                                    <?php if ($entry['confidence_score']): ?>
                                        <div class="mt-1">
                                            <small>Confidence: <?php echo number_format($entry['confidence_score'], 1); ?>%</small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($entry['verifier_name']): ?>
                                        <small class="text-muted">by <?php echo htmlspecialchars($entry['verifier_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
