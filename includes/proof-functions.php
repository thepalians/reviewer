<?php
/**
 * Proof Verification System Helper Functions
 * Phase 2: Review Proof System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// Proof upload directory
define('PROOF_UPLOAD_DIR', __DIR__ . '/../uploads/proofs/');
if (!is_dir(PROOF_UPLOAD_DIR)) {
    mkdir(PROOF_UPLOAD_DIR, 0755, true);
}

/**
 * Submit proof for task
 */
function submitProof($db, $user_id, $task_id, $proof_type, $proof_file = null, $proof_text = null) {
    try {
        // Verify task belongs to user
        $task_check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $task_check->execute([$task_id, $user_id]);
        if (!$task_check->fetch()) {
            return ['success' => false, 'message' => 'Invalid task'];
        }
        
        // Check if proof already submitted
        $proof_check = $db->prepare("SELECT id FROM task_proofs WHERE task_id = ? AND user_id = ?");
        $proof_check->execute([$task_id, $user_id]);
        if ($proof_check->fetch()) {
            return ['success' => false, 'message' => 'Proof already submitted for this task'];
        }
        
        $proof_file_path = null;
        
        // Handle file upload
        if ($proof_file && $proof_file['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadProofFile($proof_file, $user_id, $task_id);
            if (!$upload_result['success']) {
                return $upload_result;
            }
            $proof_file_path = $upload_result['file_path'];
        }
        
        // Insert proof record
        $stmt = $db->prepare("
            INSERT INTO task_proofs (task_id, user_id, proof_type, proof_file, proof_text, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$task_id, $user_id, $proof_type, $proof_file_path, $proof_text]);
        $proof_id = $db->lastInsertId();
        
        // Run AI verification if screenshot
        if ($proof_type === 'screenshot' && $proof_file_path) {
            runAIVerification($db, $proof_id, $proof_file_path);
        }
        
        // Send notification to admin
        notifyAdminNewProof($db, $proof_id);
        
        return ['success' => true, 'message' => 'Proof submitted successfully', 'proof_id' => $proof_id];
    } catch (Exception $e) {
        error_log("Proof submission error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error submitting proof'];
    }
}

/**
 * Upload proof file
 */
function uploadProofFile($file, $user_id, $task_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP allowed'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size too large. Max 5MB allowed'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'proof_' . $user_id . '_' . $task_id . '_' . time() . '.' . $extension;
    $target_path = PROOF_UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'file_path' => 'uploads/proofs/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Run AI verification on proof
 */
function runAIVerification($db, $proof_id, $file_path) {
    // This is a placeholder for AI verification
    // In production, integrate with actual AI service
    require_once __DIR__ . '/ai-verification.php';
    
    $result = performAIVerification($file_path);
    
    // Update proof with AI results
    $stmt = $db->prepare("
        UPDATE task_proofs 
        SET ai_score = ?, ai_result = ?, status = ?
        WHERE id = ?
    ");
    
    $status = $result['confidence'] >= 80 ? 'auto_approved' : 'manual_review';
    $stmt->execute([
        $result['confidence'],
        json_encode($result['details']),
        $status,
        $proof_id
    ]);
    
    // Log verification
    logVerification($db, $proof_id, 'ai', json_encode($result), $result['confidence']);
    
    return $result;
}

/**
 * Get proof details
 */
function getProofDetails($db, $proof_id) {
    $stmt = $db->prepare("
        SELECT 
            tp.*,
            t.title as task_title,
            u.username,
            u.email,
            v.username as verifier_name
        FROM task_proofs tp
        JOIN tasks t ON tp.task_id = t.id
        JOIN users u ON tp.user_id = u.id
        LEFT JOIN users v ON tp.verified_by = v.id
        WHERE tp.id = ?
    ");
    $stmt->execute([$proof_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get user proofs
 */
function getUserProofs($db, $user_id, $limit = 20, $offset = 0) {
    $stmt = $db->prepare("
        SELECT 
            tp.*,
            t.title as task_title,
            t.amount as task_amount
        FROM task_proofs tp
        JOIN tasks t ON tp.task_id = t.id
        WHERE tp.user_id = ?
        ORDER BY tp.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get pending proofs for admin
 */
function getPendingProofs($db, $limit = 50, $offset = 0) {
    $stmt = $db->prepare("
        SELECT 
            tp.*,
            t.title as task_title,
            u.username,
            u.email
        FROM task_proofs tp
        JOIN tasks t ON tp.task_id = t.id
        JOIN users u ON tp.user_id = u.id
        WHERE tp.status IN ('pending', 'manual_review')
        ORDER BY tp.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Approve proof (Admin)
 */
function approveProof($db, $proof_id, $admin_id) {
    try {
        $db->beginTransaction();
        
        // Get proof details
        $proof = getProofDetails($db, $proof_id);
        if (!$proof) {
            throw new Exception('Proof not found');
        }
        
        // Update proof status
        $stmt = $db->prepare("
            UPDATE task_proofs 
            SET status = 'approved', verified_by = ?, verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$admin_id, $proof_id]);
        
        // Log verification
        logVerification($db, $proof_id, 'manual', 'Approved by admin', 100, $admin_id);
        
        // Update task/order status if needed
        // This depends on your existing task completion flow
        
        // Send notification to user
        createNotification($db, $proof['user_id'], 'proof_approved', 
            "Your proof for task '{$proof['task_title']}' has been approved!");
        
        $db->commit();
        return ['success' => true, 'message' => 'Proof approved successfully'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Proof approval error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error approving proof'];
    }
}

/**
 * Reject proof (Admin)
 */
function rejectProof($db, $proof_id, $admin_id, $reason) {
    try {
        $db->beginTransaction();
        
        // Get proof details
        $proof = getProofDetails($db, $proof_id);
        if (!$proof) {
            throw new Exception('Proof not found');
        }
        
        // Update proof status
        $stmt = $db->prepare("
            UPDATE task_proofs 
            SET status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $admin_id, $proof_id]);
        
        // Log verification
        logVerification($db, $proof_id, 'manual', 'Rejected: ' . $reason, 0, $admin_id);
        
        // Send notification to user
        createNotification($db, $proof['user_id'], 'proof_rejected', 
            "Your proof for task '{$proof['task_title']}' was rejected. Reason: {$reason}");
        
        $db->commit();
        return ['success' => true, 'message' => 'Proof rejected'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Proof rejection error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error rejecting proof'];
    }
}

/**
 * Log verification attempt
 */
function logVerification($db, $proof_id, $type, $result, $confidence = 0, $verified_by = null) {
    $stmt = $db->prepare("
        INSERT INTO proof_verification_logs (proof_id, verification_type, result, confidence_score, verified_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$proof_id, $type, $result, $confidence, $verified_by]);
}

/**
 * Get proof statistics
 */
function getProofStats($db, $user_id = null) {
    $where = $user_id ? "WHERE user_id = ?" : "";
    
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'manual_review' THEN 1 ELSE 0 END) as manual_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'auto_approved' THEN 1 ELSE 0 END) as auto_approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM task_proofs
        $where
    ";
    
    $stmt = $user_id ? $db->prepare($query) : $db->query($query);
    if ($user_id) {
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Notify admin of new proof
 */
function notifyAdminNewProof($db, $proof_id) {
    // Get admin users
    $stmt = $db->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($admins as $admin_id) {
        createNotification($db, $admin_id, 'new_proof', 
            "New proof submitted for verification (ID: {$proof_id})");
    }
}

/**
 * Get verification history for proof
 */
function getVerificationHistory($db, $proof_id) {
    $stmt = $db->prepare("
        SELECT 
            pvl.*,
            u.username as verifier_name
        FROM proof_verification_logs pvl
        LEFT JOIN users u ON pvl.verified_by = u.id
        WHERE pvl.proof_id = ?
        ORDER BY pvl.created_at DESC
    ");
    $stmt->execute([$proof_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
