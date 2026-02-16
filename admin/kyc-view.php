<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/kyc-functions.php';
require_once __DIR__ . '/../includes/Notifications.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Get KYC ID
$kyc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$kyc_id) {
    $_SESSION['error'] = 'Invalid KYC ID.';
    header('Location: ' . ADMIN_URL . '/kyc-verification.php');
    exit;
}

// Get KYC data
$kyc = getKYCById($pdo, $kyc_id);

if (!$kyc) {
    $_SESSION['error'] = 'KYC application not found.';
    header('Location: ' . ADMIN_URL . '/kyc-verification.php');
    exit;
}

// Handle POST request for approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            if (updateKYCStatus($pdo, $kyc_id, 'verified', null, $_SESSION['admin_id'] ?? null)) {
                // Send notification
                try {
                    $notifications = new Notifications($pdo);
                    $notifications->sendTemplateNotification(
                        'kyc_verified',
                        $kyc['user_id'],
                        $kyc['email'],
                        [
                            'user_name' => $kyc['username'],
                            'full_name' => $kyc['full_name']
                        ],
                        'email'
                    );
                } catch (Exception $e) {
                    error_log("KYC approval notification error: {$e->getMessage()}");
                }
                $_SESSION['success'] = 'KYC approved successfully.';
                header('Location: ' . ADMIN_URL . '/kyc-verification.php');
                exit;
            } else {
                $_SESSION['error'] = 'Failed to approve KYC.';
            }
        } elseif ($action === 'reject') {
            $reason = $_POST['reason'] ?? 'Documents verification failed';
            if (updateKYCStatus($pdo, $kyc_id, 'rejected', $reason, $_SESSION['admin_id'] ?? null)) {
                // Send notification
                try {
                    $notifications = new Notifications($pdo);
                    $notifications->sendTemplateNotification(
                        'kyc_rejected',
                        $kyc['user_id'],
                        $kyc['email'],
                        [
                            'user_name' => $kyc['username'],
                            'full_name' => $kyc['full_name'],
                            'rejection_reason' => $reason
                        ],
                        'email'
                    );
                } catch (Exception $e) {
                    error_log("KYC rejection notification error: {$e->getMessage()}");
                }
                $_SESSION['success'] = 'KYC rejected successfully.';
                header('Location: ' . ADMIN_URL . '/kyc-verification.php');
                exit;
            } else {
                $_SESSION['error'] = 'Failed to reject KYC.';
            }
        }
        
        // Refresh KYC data
        $kyc = getKYCById($pdo, $kyc_id);
    }
}

// Get badges for sidebar
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
    $pending_wallet_recharges = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_kyc WHERE status = 'pending'");
    $pending_kyc = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Badge count error: {$e->getMessage()}");
    $pending_tasks = $pending_withdrawals = $pending_wallet_recharges = $unread_messages = $unanswered_questions = $pending_kyc = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View KYC Application - <?php echo APP_NAME; ?></title>
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
        .sidebar-menu a.logout:hover{background:rgba(231,76,60,0.1)}
        
        .main-content{padding:25px;overflow-x:hidden;max-width:1400px}
        
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        .back-link{display:inline-flex;align-items:center;gap:8px;color:#667eea;text-decoration:none;font-weight:600;font-size:14px;padding:10px 20px;background:#fff;border-radius:10px;transition:all 0.2s}
        .back-link:hover{transform:translateY(-2px);box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert.success{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0}
        .alert.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        
        .kyc-container{display:grid;grid-template-columns:2fr 1fr;gap:20px}
        
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:20px}
        .card-header{font-size:18px;font-weight:700;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .info-item{margin-bottom:20px}
        .info-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;font-weight:600}
        .info-value{font-size:15px;color:#1e293b;font-weight:500}
        
        .status-badge{padding:8px 16px;border-radius:20px;font-size:12px;font-weight:600;display:inline-block}
        .status-badge.pending{background:#fffbeb;color:#d97706}
        .status-badge.verified{background:#ecfdf5;color:#059669}
        .status-badge.rejected{background:#fef2f2;color:#dc2626}
        
        .document-preview{border:2px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:20px}
        .document-preview img{width:100%;height:auto;display:block}
        .document-preview iframe{width:100%;height:400px;border:none}
        .document-preview-label{background:#f8fafc;padding:12px;font-weight:600;color:#334155;font-size:14px;border-bottom:2px solid #e2e8f0}
        .document-link{display:inline-flex;align-items:center;gap:8px;color:#667eea;text-decoration:none;font-weight:600;font-size:14px;padding:10px 16px;background:#eff6ff;border-radius:8px;margin-top:10px;transition:all 0.2s}
        .document-link:hover{background:#dbeafe}
        
        .user-info-card{text-align:center}
        .user-avatar-large{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:36px;margin:0 auto 20px}
        .user-name-large{font-size:22px;font-weight:700;color:#1e293b;margin-bottom:5px}
        .user-email-large{font-size:14px;color:#64748b;margin-bottom:20px}
        
        .action-section{background:#f8fafc;border-radius:10px;padding:20px;margin-top:20px}
        .action-title{font-size:16px;font-weight:600;color:#1e293b;margin-bottom:15px}
        
        .form-group{margin-bottom:15px}
        .form-label{display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:8px}
        .form-control{width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit}
        .form-control:focus{outline:none;border-color:#667eea}
        
        .btn-group{display:flex;gap:10px;margin-top:20px}
        .btn{padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all 0.2s;justify-content:center;flex:1}
        .btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,0.2)}
        .btn-approve{background:#059669;color:#fff}
        .btn-reject{background:#dc2626;color:#fff}
        .btn:disabled{opacity:0.5;cursor:not-allowed;transform:none}
        
        .rejection-info{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:15px;margin-top:15px}
        .rejection-title{font-size:14px;font-weight:600;color:#dc2626;margin-bottom:8px}
        .rejection-text{font-size:14px;color:#991b1b}
        
        .timeline-item{display:flex;gap:15px;margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid #f1f5f9}
        .timeline-item:last-child{border-bottom:none}
        .timeline-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
        .timeline-icon.submitted{background:#eff6ff;color:#3b82f6}
        .timeline-icon.verified{background:#ecfdf5;color:#10b981}
        .timeline-icon.rejected{background:#fef2f2;color:#ef4444}
        .timeline-content{flex:1}
        .timeline-title{font-weight:600;color:#1e293b;font-size:14px;margin-bottom:3px}
        .timeline-time{font-size:12px;color:#64748b}
        
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .kyc-container{grid-template-columns:1fr}
            .info-grid{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">KYC Application Details</div>
                <div class="page-subtitle">Review KYC information and documents</div>
            </div>
            <a href="<?php echo ADMIN_URL; ?>/kyc-verification.php" class="back-link">← Back to List</a>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="kyc-container">
            <div>
                <div class="card">
                    <div class="card-header">📋 Personal Information</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo escape($kyc['full_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo date('d M Y', strtotime($kyc['dob'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Mobile Number</div>
                            <div class="info-value"><?php echo escape($kyc['mobile']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo escape($kyc['email']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">🆔 Identity Documents</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Aadhaar Number</div>
                            <div class="info-value"><?php echo maskAadhaar($kyc['aadhaar_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">PAN Number</div>
                            <div class="info-value"><?php echo maskPAN($kyc['pan_number']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($kyc['aadhaar_file']): ?>
                    <div class="document-preview">
                        <div class="document-preview-label">📄 Aadhaar Document</div>
                        <?php 
                        $aadhaar_path = APP_URL . '/uploads/kyc/' . $kyc['aadhaar_file'];
                        $aadhaar_ext = strtolower(pathinfo($kyc['aadhaar_file'], PATHINFO_EXTENSION));
                        if ($aadhaar_ext === 'pdf'): ?>
                            <iframe src="<?php echo $aadhaar_path; ?>"></iframe>
                        <?php else: ?>
                            <img src="<?php echo $aadhaar_path; ?>" alt="Aadhaar Document">
                        <?php endif; ?>
                        <div style="padding:15px;">
                            <a href="<?php echo $aadhaar_path; ?>" target="_blank" class="document-link">📥 Open in New Tab</a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($kyc['pan_file']): ?>
                    <div class="document-preview">
                        <div class="document-preview-label">📄 PAN Document</div>
                        <?php 
                        $pan_path = APP_URL . '/uploads/kyc/' . $kyc['pan_file'];
                        $pan_ext = strtolower(pathinfo($kyc['pan_file'], PATHINFO_EXTENSION));
                        if ($pan_ext === 'pdf'): ?>
                            <iframe src="<?php echo $pan_path; ?>"></iframe>
                        <?php else: ?>
                            <img src="<?php echo $pan_path; ?>" alt="PAN Document">
                        <?php endif; ?>
                        <div style="padding:15px;">
                            <a href="<?php echo $pan_path; ?>" target="_blank" class="document-link">📥 Open in New Tab</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <div class="card-header">🏦 Bank Details</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Bank Name</div>
                            <div class="info-value"><?php echo escape($kyc['bank_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Account Number</div>
                            <div class="info-value"><?php echo escape($kyc['bank_account'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">IFSC Code</div>
                            <div class="info-value"><?php echo escape($kyc['ifsc_code'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($kyc['passbook_file']): ?>
                    <div class="document-preview">
                        <div class="document-preview-label">📄 Bank Passbook/Statement</div>
                        <?php 
                        $passbook_path = APP_URL . '/uploads/kyc/' . $kyc['passbook_file'];
                        $passbook_ext = strtolower(pathinfo($kyc['passbook_file'], PATHINFO_EXTENSION));
                        if ($passbook_ext === 'pdf'): ?>
                            <iframe src="<?php echo $passbook_path; ?>"></iframe>
                        <?php else: ?>
                            <img src="<?php echo $passbook_path; ?>" alt="Bank Passbook">
                        <?php endif; ?>
                        <div style="padding:15px;">
                            <a href="<?php echo $passbook_path; ?>" target="_blank" class="document-link">📥 Open in New Tab</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="card user-info-card">
                    <div class="user-avatar-large"><?php echo strtoupper(substr($kyc['username'], 0, 2)); ?></div>
                    <div class="user-name-large"><?php echo escape($kyc['username']); ?></div>
                    <div class="user-email-large"><?php echo escape($kyc['email']); ?></div>
                    <span class="status-badge <?php echo $kyc['status']; ?>">
                        <?php echo ucfirst($kyc['status']); ?>
                    </span>
                </div>
                
                <div class="card">
                    <div class="card-header">⏱️ Timeline</div>
                    <div class="timeline-item">
                        <div class="timeline-icon submitted">📝</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Application Submitted</div>
                            <div class="timeline-time"><?php echo date('d M Y, H:i', strtotime($kyc['submitted_at'])); ?></div>
                        </div>
                    </div>
                    <?php if ($kyc['verified_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?php echo $kyc['status'] === 'verified' ? 'verified' : 'rejected'; ?>">
                            <?php echo $kyc['status'] === 'verified' ? '✅' : '❌'; ?>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title"><?php echo $kyc['status'] === 'verified' ? 'Verified' : 'Rejected'; ?></div>
                            <div class="timeline-time"><?php echo date('d M Y, H:i', strtotime($kyc['verified_at'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($kyc['status'] === 'rejected' && $kyc['rejection_reason']): ?>
                <div class="card">
                    <div class="card-header">❌ Rejection Details</div>
                    <div class="rejection-info">
                        <div class="rejection-title">Reason for Rejection:</div>
                        <div class="rejection-text"><?php echo escape($kyc['rejection_reason']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($kyc['status'] === 'pending'): ?>
                <div class="card">
                    <div class="card-header">⚡ Quick Actions</div>
                    <div class="action-section">
                        <div class="action-title">Approve Application</div>
                        <p style="color:#64748b;font-size:13px;margin-bottom:15px;">Verify all documents before approving.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-approve" onclick="return confirm('Are you sure you want to approve this KYC application?')">
                                ✓ Approve KYC
                            </button>
                        </form>
                    </div>
                    
                    <div class="action-section" style="margin-top:15px;">
                        <div class="action-title">Reject Application</div>
                        <p style="color:#64748b;font-size:13px;margin-bottom:15px;">Provide a reason for rejection.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="reject">
                            <div class="form-group">
                                <label class="form-label">Rejection Reason</label>
                                <textarea name="reason" class="form-control" rows="4" placeholder="Enter reason for rejection..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this KYC application?')">
                                ✗ Reject KYC
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
