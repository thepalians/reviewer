<?php
declare(strict_types=1);

/**
 * KYC Helper Functions
 */

if (!function_exists('validateAadhaar')) {
    /**
     * Validate Aadhaar number (12 digits)
     */
    function validateAadhaar(string $aadhaar): bool {
        return preg_match('/^[0-9]{12}$/', $aadhaar) === 1;
    }
}

if (!function_exists('validatePAN')) {
    /**
     * Validate PAN number (10 characters - 5 letters, 4 digits, 1 letter)
     */
    function validatePAN(string $pan): bool {
        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($pan)) === 1;
    }
}

if (!function_exists('validateIFSC')) {
    /**
     * Validate IFSC code (11 characters)
     */
    function validateIFSC(string $ifsc): bool {
        return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifsc)) === 1;
    }
}

if (!function_exists('getUserKYC')) {
    /**
     * Get KYC information for a user
     */
    function getUserKYC($pdo, int $userId): ?array {
        try {
            $stmt = $pdo->prepare("
                SELECT k.*, u.username, u.email, u.mobile 
                FROM user_kyc k
                JOIN users u ON k.user_id = u.id
                WHERE k.user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Get user KYC error: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('getKYCById')) {
    /**
     * Get KYC by ID
     */
    function getKYCById($pdo, int $kycId): ?array {
        try {
            $stmt = $pdo->prepare("
                SELECT k.*, u.username, u.email, u.mobile 
                FROM user_kyc k
                JOIN users u ON k.user_id = u.id
                WHERE k.id = ?
            ");
            $stmt->execute([$kycId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Get KYC by ID error: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('getAllPendingKYC')) {
    /**
     * Get all pending KYC applications
     */
    function getAllPendingKYC($pdo): array {
        try {
            $stmt = $pdo->query("
                SELECT k.*, u.username, u.email, u.mobile 
                FROM user_kyc k
                JOIN users u ON k.user_id = u.id
                WHERE k.status = 'pending'
                ORDER BY k.submitted_at DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get pending KYC error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getAllKYC')) {
    /**
     * Get all KYC applications with optional filter
     */
    function getAllKYC($pdo, ?string $status = null): array {
        try {
            if ($status) {
                $stmt = $pdo->prepare("
                    SELECT k.*, u.username, u.email, u.mobile 
                    FROM user_kyc k
                    JOIN users u ON k.user_id = u.id
                    WHERE k.status = ?
                    ORDER BY k.submitted_at DESC
                ");
                $stmt->execute([$status]);
            } else {
                $stmt = $pdo->query("
                    SELECT k.*, u.username, u.email, u.mobile 
                    FROM user_kyc k
                    JOIN users u ON k.user_id = u.id
                    ORDER BY k.submitted_at DESC
                ");
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get all KYC error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('updateKYCStatus')) {
    /**
     * Update KYC status
     */
    function updateKYCStatus(
        $pdo, 
        int $kycId, 
        string $status, 
        ?string $rejectionReason = null, 
        ?int $verifiedBy = null
    ): bool {
        try {
            $pdo->beginTransaction();
            
            // Update KYC record
            $stmt = $pdo->prepare("
                UPDATE user_kyc 
                SET status = ?, 
                    verified_at = ?, 
                    verified_by = ?,
                    rejection_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $status,
                $status !== 'pending' ? date('Y-m-d H:i:s') : null,
                $verifiedBy,
                $rejectionReason,
                $kycId
            ]);
            
            // Update user's KYC status
            $kycData = getKYCById($pdo, $kycId);
            if ($kycData) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET kyc_status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$status, $kycData['user_id']]);
            }
            
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update KYC status error: {$e->getMessage()}");
            return false;
        }
    }
}

if (!function_exists('maskAadhaar')) {
    /**
     * Mask Aadhaar number for display (show only last 4 digits)
     */
    function maskAadhaar(?string $aadhaar): string {
        if (!$aadhaar || strlen($aadhaar) !== 12) {
            return 'N/A';
        }
        return 'XXXX XXXX ' . substr($aadhaar, -4);
    }
}

if (!function_exists('maskPAN')) {
    /**
     * Mask PAN for display (show first 2 and last 2 characters)
     */
    function maskPAN(?string $pan): string {
        if (!$pan || strlen($pan) !== 10) {
            return 'N/A';
        }
        return substr($pan, 0, 2) . 'XXXXXX' . substr($pan, -2);
    }
}

if (!function_exists('uploadKYCDocument')) {
    /**
     * Upload KYC document
     */
    function uploadKYCDocument(array $file, string $type, int $userId): ?string {
        $uploadDir = __DIR__ . '/../uploads/kyc/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }
        
        if ($file['size'] > $maxSize) {
            return null;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        }
        
        return null;
    }
}

if (!function_exists('deleteKYCDocument')) {
    /**
     * Delete KYC document file
     */
    function deleteKYCDocument(string $filename): bool {
        $filepath = __DIR__ . '/../uploads/kyc/' . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
}

if (!function_exists('getKYCDocumentPath')) {
    /**
     * Get full path to KYC document
     */
    function getKYCDocumentPath(string $filename): string {
        return __DIR__ . '/../uploads/kyc/' . $filename;
    }
}

if (!function_exists('getKYCStats')) {
    /**
     * Get KYC statistics
     */
    function getKYCStats($pdo): array {
        try {
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM user_kyc
            ");
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Get KYC stats error: {$e->getMessage()}");
            return [];
        }
    }
}
?>
