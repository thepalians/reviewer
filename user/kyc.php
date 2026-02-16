<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/kyc-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

$success_message = '';
$error_message = '';

// Check if user already has KYC submitted
$existingKYC = getUserKYC($pdo, $user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingKYC) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        try {
            // Validate inputs
            $full_name = sanitizeInput($_POST['full_name']);
            $dob = sanitizeInput($_POST['dob']);
            $aadhaar = sanitizeInput($_POST['aadhaar_number']);
            $pan = strtoupper(sanitizeInput($_POST['pan_number']));
            $bank_account = sanitizeInput($_POST['bank_account']);
            $ifsc_code = strtoupper(sanitizeInput($_POST['ifsc_code']));
            $bank_name = sanitizeInput($_POST['bank_name']);
            
            // Validate Aadhaar
            if (!validateAadhaar($aadhaar)) {
                throw new Exception('Invalid Aadhaar number. Must be 12 digits.');
            }
            
            // Validate PAN
            if (!validatePAN($pan)) {
                throw new Exception('Invalid PAN number. Format: ABCDE1234F');
            }
            
            // Validate IFSC
            if (!validateIFSC($ifsc_code)) {
                throw new Exception('Invalid IFSC code. Format: ABCD0123456');
            }
            
            // Validate age (minimum 18 years)
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dobDate)->y;
            
            if ($age < 18) {
                throw new Exception('You must be at least 18 years old to submit KYC.');
            }
            
            // Upload documents
            $aadhaar_file = null;
            $pan_file = null;
            $passbook_file = null;
            
            if (isset($_FILES['aadhaar_file']) && $_FILES['aadhaar_file']['error'] === UPLOAD_ERR_OK) {
                $aadhaar_file = uploadKYCDocument($_FILES['aadhaar_file'], 'aadhaar', $user_id);
                if (!$aadhaar_file) {
                    throw new Exception('Failed to upload Aadhaar document. Please ensure it is a valid image or PDF (max 5MB).');
                }
            } else {
                throw new Exception('Aadhaar document is required.');
            }
            
            if (isset($_FILES['pan_file']) && $_FILES['pan_file']['error'] === UPLOAD_ERR_OK) {
                $pan_file = uploadKYCDocument($_FILES['pan_file'], 'pan', $user_id);
                if (!$pan_file) {
                    throw new Exception('Failed to upload PAN document. Please ensure it is a valid image or PDF (max 5MB).');
                }
            } else {
                throw new Exception('PAN document is required.');
            }
            
            if (isset($_FILES['passbook_file']) && $_FILES['passbook_file']['error'] === UPLOAD_ERR_OK) {
                $passbook_file = uploadKYCDocument($_FILES['passbook_file'], 'passbook', $user_id);
                if (!$passbook_file) {
                    throw new Exception('Failed to upload Bank Passbook/Cheque. Please ensure it is a valid image or PDF (max 5MB).');
                }
            } else {
                throw new Exception('Bank Passbook/Cancelled Cheque is required.');
            }
            
            // Insert KYC record
            $stmt = $pdo->prepare("
                INSERT INTO user_kyc 
                (user_id, full_name, dob, aadhaar_number, aadhaar_file, pan_number, pan_file, 
                bank_account, ifsc_code, bank_name, passbook_file, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $user_id,
                $full_name,
                $dob,
                $aadhaar,
                $aadhaar_file,
                $pan,
                $pan_file,
                $bank_account,
                $ifsc_code,
                $bank_name,
                $passbook_file
            ]);
            
            // Update user's KYC status
            $stmt = $pdo->prepare("UPDATE users SET kyc_status = 'pending' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $success_message = 'KYC submitted successfully! Your application is under review.';
            $existingKYC = getUserKYC($pdo, $user_id);
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            // Clean up uploaded files if any error
            if (isset($aadhaar_file)) deleteKYCDocument($aadhaar_file);
            if (isset($pan_file)) deleteKYCDocument($pan_file);
            if (isset($passbook_file)) deleteKYCDocument($passbook_file);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .kyc-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .status-badge {
            font-size: 1.2rem;
            padding: 10px 20px;
        }
        .document-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="kyc-container">
            <div class="text-center mb-4">
                <h1><i class="bi bi-shield-check text-primary"></i> KYC Verification</h1>
                <p class="text-muted">Complete your KYC to enable withdrawals</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($existingKYC): ?>
                <!-- Show KYC Status -->
                <div class="text-center mb-4">
                    <?php
                    $statusClass = [
                        'pending' => 'bg-warning',
                        'verified' => 'bg-success',
                        'rejected' => 'bg-danger'
                    ];
                    $statusIcon = [
                        'pending' => 'bi-clock',
                        'verified' => 'bi-check-circle',
                        'rejected' => 'bi-x-circle'
                    ];
                    ?>
                    <span class="badge status-badge <?php echo $statusClass[$existingKYC['status']]; ?>">
                        <i class="bi <?php echo $statusIcon[$existingKYC['status']]; ?>"></i>
                        <?php echo strtoupper($existingKYC['status']); ?>
                    </span>
                </div>

                <?php if ($existingKYC['status'] === 'rejected' && $existingKYC['rejection_reason']): ?>
                    <div class="alert alert-danger">
                        <strong>Rejection Reason:</strong><br>
                        <?php echo htmlspecialchars($existingKYC['rejection_reason']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Your KYC Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Full Name:</strong><br>
                                <?php echo htmlspecialchars($existingKYC['full_name']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Date of Birth:</strong><br>
                                <?php echo date('d M Y', strtotime($existingKYC['dob'])); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Aadhaar Number:</strong><br>
                                <?php echo maskAadhaar($existingKYC['aadhaar_number']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>PAN Number:</strong><br>
                                <?php echo maskPAN($existingKYC['pan_number']); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Bank Account:</strong><br>
                                XXXX XXXX <?php echo substr($existingKYC['bank_account'], -4); ?><br>
                                <small class="text-muted">IFSC: <?php echo htmlspecialchars($existingKYC['ifsc_code']); ?> | <?php echo htmlspecialchars($existingKYC['bank_name']); ?></small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <small class="text-muted">
                                    Submitted on: <?php echo date('d M Y, h:i A', strtotime($existingKYC['submitted_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

            <?php else: ?>
                <!-- KYC Submission Form -->
                <div class="info-box">
                    <i class="bi bi-info-circle"></i> <strong>Important:</strong>
                    <ul class="mb-0 mt-2">
                        <li>All documents must be clear and readable</li>
                        <li>Maximum file size: 5MB per document</li>
                        <li>Accepted formats: JPG, PNG, PDF</li>
                        <li>You must be at least 18 years old</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <h5 class="mb-3"><i class="bi bi-person"></i> Personal Information</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name (as per Aadhaar) *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="dob" class="form-control" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4"><i class="bi bi-file-earmark-text"></i> Identity Documents</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Aadhaar Number (12 digits) *</label>
                            <input type="text" name="aadhaar_number" class="form-control" required pattern="[0-9]{12}" maxlength="12" placeholder="123456789012">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Upload Aadhaar Card *</label>
                            <input type="file" name="aadhaar_file" class="form-control" required accept="image/*,.pdf">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">PAN Number (10 characters) *</label>
                            <input type="text" name="pan_number" class="form-control" required pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" maxlength="10" placeholder="ABCDE1234F" style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Upload PAN Card *</label>
                            <input type="file" name="pan_file" class="form-control" required accept="image/*,.pdf">
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4"><i class="bi bi-bank"></i> Bank Details</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Account Number *</label>
                            <input type="text" name="bank_account" class="form-control" required maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IFSC Code (11 characters) *</label>
                            <input type="text" name="ifsc_code" class="form-control" required pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" maxlength="11" placeholder="SBIN0001234" style="text-transform: uppercase;">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name *</label>
                            <input type="text" name="bank_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Passbook/Cancelled Cheque *</label>
                            <input type="file" name="passbook_file" class="form-control" required accept="image/*,.pdf">
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I certify that all information provided is true and accurate
                        </label>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Submit KYC
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="bi bi-x"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
