<?php
declare(strict_types=1);

/**
 * Affiliate System Functions
 * Multi-tier referral and commission tracking
 */

/**
 * Create affiliate account
 */
function createAffiliate(int $userId, ?string $affiliateCode = null): ?int {
    global $pdo;
    
    try {
        // Generate unique affiliate code if not provided
        if (!$affiliateCode) {
            $affiliateCode = 'AFF' . strtoupper(substr(md5(uniqid()), 0, 8));
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO affiliates (user_id, affiliate_code, status)
            VALUES (?, ?, 'pending')
        ");
        
        if ($stmt->execute([$userId, $affiliateCode])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating affiliate: " . $e->getMessage());
        return null;
    }
}

/**
 * Get affiliate by user ID
 */
function getAffiliateByUserId(int $userId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting affiliate: " . $e->getMessage());
        return null;
    }
}

/**
 * Get affiliate by code
 */
function getAffiliateByCode(string $code): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE affiliate_code = ?");
        $stmt->execute([$code]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting affiliate by code: " . $e->getMessage());
        return null;
    }
}

/**
 * Create affiliate referral
 */
function createAffiliateReferral(int $affiliateId, int $referredUserId, int $level = 1, ?int $parentReferralId = null): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_referrals 
            (affiliate_id, referred_user_id, referral_level, parent_referral_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$affiliateId, $referredUserId, $level, $parentReferralId])) {
            // Update affiliate total referrals
            $stmt = $pdo->prepare("UPDATE affiliates SET total_referrals = total_referrals + 1 WHERE id = ?");
            $stmt->execute([$affiliateId]);
            
            // Create multi-level referrals
            if ($level < 3 && $parentReferralId) {
                createMultiLevelReferrals($referredUserId, $parentReferralId, $level + 1);
            }
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error creating affiliate referral: " . $e->getMessage());
        return false;
    }
}

/**
 * Create multi-level referrals (up to 3 levels)
 */
function createMultiLevelReferrals(int $userId, int $parentReferralId, int $level): void {
    global $pdo;
    
    if ($level > 3) return;
    
    try {
        // Get parent referral's affiliate
        $stmt = $pdo->prepare("SELECT affiliate_id FROM affiliate_referrals WHERE id = ?");
        $stmt->execute([$parentReferralId]);
        $parentAffiliate = $stmt->fetchColumn();
        
        if ($parentAffiliate) {
            // Get the next level up affiliate
            $stmt = $pdo->prepare("
                SELECT ar.affiliate_id, ar.id 
                FROM affiliate_referrals ar
                WHERE ar.referred_user_id IN (
                    SELECT u.id FROM users u
                    JOIN affiliates a ON u.id = a.user_id
                    WHERE a.id = ?
                )
                LIMIT 1
            ");
            $stmt->execute([$parentAffiliate]);
            $upperLevelRef = $stmt->fetch();
            
            if ($upperLevelRef) {
                createAffiliateReferral($upperLevelRef['affiliate_id'], $userId, $level, $upperLevelRef['id']);
            }
        }
    } catch (PDOException $e) {
        error_log("Error creating multi-level referrals: " . $e->getMessage());
    }
}

/**
 * Calculate and create commission
 */
function createAffiliateCommission(int $referralId, string $sourceType, int $sourceId, float $amount): bool {
    global $pdo;
    
    try {
        // Get referral details
        $stmt = $pdo->prepare("
            SELECT ar.*, a.commission_rate, a.level2_rate, a.level3_rate
            FROM affiliate_referrals ar
            JOIN affiliates a ON ar.affiliate_id = a.id
            WHERE ar.id = ?
        ");
        $stmt->execute([$referralId]);
        $referral = $stmt->fetch();
        
        if (!$referral) return false;
        
        // Determine commission rate based on level
        $rate = match($referral['referral_level']) {
            1 => $referral['commission_rate'],
            2 => $referral['level2_rate'],
            3 => $referral['level3_rate'],
            default => 0
        };
        
        $commissionAmount = ($amount * $rate) / 100;
        
        // Create commission record
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_commissions 
            (affiliate_id, referral_id, source_type, source_id, level, amount, rate_applied, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([
            $referral['affiliate_id'],
            $referralId,
            $sourceType,
            $sourceId,
            $referral['referral_level'],
            $commissionAmount,
            $rate
        ])) {
            // Update affiliate pending earnings
            $stmt = $pdo->prepare("
                UPDATE affiliates 
                SET pending_earnings = pending_earnings + ? 
                WHERE id = ?
            ");
            $stmt->execute([$commissionAmount, $referral['affiliate_id']]);
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error creating affiliate commission: " . $e->getMessage());
        return false;
    }
}

/**
 * Approve affiliate commission
 */
function approveAffiliateCommission(int $commissionId): bool {
    global $pdo;
    
    try {
        // Get commission details
        $stmt = $pdo->prepare("SELECT * FROM affiliate_commissions WHERE id = ?");
        $stmt->execute([$commissionId]);
        $commission = $stmt->fetch();
        
        if (!$commission || $commission['status'] !== 'pending') {
            return false;
        }
        
        // Update commission status
        $stmt = $pdo->prepare("UPDATE affiliate_commissions SET status = 'approved' WHERE id = ?");
        $stmt->execute([$commissionId]);
        
        // Update affiliate balances
        $stmt = $pdo->prepare("
            UPDATE affiliates 
            SET pending_earnings = pending_earnings - ?,
                total_earnings = total_earnings + ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $commission['amount'],
            $commission['amount'],
            $commission['affiliate_id']
        ]);
    } catch (PDOException $e) {
        error_log("Error approving commission: " . $e->getMessage());
        return false;
    }
}

/**
 * Create affiliate payout
 */
function createAffiliatePayout(int $affiliateId, float $amount, array $paymentDetails = []): ?int {
    global $pdo;
    
    try {
        // Check if affiliate has enough balance
        $stmt = $pdo->prepare("SELECT total_earnings FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $totalEarnings = (float)$stmt->fetchColumn();
        
        if ($totalEarnings < $amount) {
            return null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_payouts 
            (affiliate_id, amount, payment_method, payment_details, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([
            $affiliateId,
            $amount,
            $paymentDetails['method'] ?? 'bank_transfer',
            json_encode($paymentDetails)
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating affiliate payout: " . $e->getMessage());
        return null;
    }
}

/**
 * Process affiliate payout
 */
function processAffiliatePayout(int $payoutId, int $processedBy): bool {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get payout details
        $stmt = $pdo->prepare("SELECT * FROM affiliate_payouts WHERE id = ?");
        $stmt->execute([$payoutId]);
        $payout = $stmt->fetch();
        
        if (!$payout || $payout['status'] !== 'pending') {
            $pdo->rollBack();
            return false;
        }
        
        // Update payout status
        $stmt = $pdo->prepare("
            UPDATE affiliate_payouts 
            SET status = 'completed', processed_by = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$processedBy, $payoutId]);
        
        // Deduct from affiliate total earnings
        $stmt = $pdo->prepare("
            UPDATE affiliates 
            SET total_earnings = total_earnings - ?
            WHERE id = ?
        ");
        $stmt->execute([$payout['amount'], $payout['affiliate_id']]);
        
        // Mark related commissions as paid
        $stmt = $pdo->prepare("
            UPDATE affiliate_commissions 
            SET status = 'paid', paid_at = NOW()
            WHERE affiliate_id = ? AND status = 'approved'
            ORDER BY created_at
        ");
        $stmt->execute([$payout['affiliate_id']]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error processing affiliate payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Create affiliate tracking link
 */
function createAffiliateLink(int $affiliateId, string $name, string $destinationUrl, ?string $shortCode = null): ?array {
    global $pdo;
    
    try {
        if (!$shortCode) {
            $shortCode = 'L' . strtoupper(substr(md5(uniqid()), 0, 8));
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO affiliate_links 
            (affiliate_id, name, short_code, destination_url)
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$affiliateId, $name, $shortCode, $destinationUrl])) {
            return [
                'id' => $pdo->lastInsertId(),
                'short_code' => $shortCode,
                'url' => APP_URL . '/l/' . $shortCode
            ];
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating affiliate link: " . $e->getMessage());
        return null;
    }
}

/**
 * Track affiliate link click
 */
function trackAffiliateLinkClick(string $shortCode): ?string {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM affiliate_links WHERE short_code = ? AND is_active = 1");
        $stmt->execute([$shortCode]);
        $link = $stmt->fetch();
        
        if (!$link) return null;
        
        // Increment click count
        $stmt = $pdo->prepare("UPDATE affiliate_links SET click_count = click_count + 1 WHERE id = ?");
        $stmt->execute([$link['id']]);
        
        return $link['destination_url'];
    } catch (PDOException $e) {
        error_log("Error tracking link click: " . $e->getMessage());
        return null;
    }
}

/**
 * Get affiliate dashboard stats
 */
function getAffiliateDashboardStats(int $affiliateId): array {
    global $pdo;
    
    try {
        $stats = [];
        
        $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch();
        
        if (!$affiliate) return [];
        
        $stats['total_referrals'] = $affiliate['total_referrals'];
        $stats['total_earnings'] = $affiliate['total_earnings'];
        $stats['pending_earnings'] = $affiliate['pending_earnings'];
        
        // Active referrals
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM affiliate_referrals 
            WHERE affiliate_id = ? AND status = 'active'
        ");
        $stmt->execute([$affiliateId]);
        $stats['active_referrals'] = $stmt->fetchColumn();
        
        // Pending commissions
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions 
            WHERE affiliate_id = ? AND status = 'pending'
        ");
        $stmt->execute([$affiliateId]);
        $stats['pending_commissions'] = $stmt->fetchColumn();
        
        // This month earnings
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions 
            WHERE affiliate_id = ? 
            AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$affiliateId]);
        $stats['month_earnings'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting affiliate stats: " . $e->getMessage());
        return [];
    }
}
