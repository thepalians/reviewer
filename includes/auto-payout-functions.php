<?php
declare(strict_types=1);

/**
 * Auto Payout Functions
 * Automated payout scheduling and processing
 */

/**
 * Get gateway configuration
 */
function getGatewayConfig(string $gatewayType): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT config FROM payment_gateways 
            WHERE gateway_type = ? AND is_active = 1
        ");
        $stmt->execute([$gatewayType]);
        $result = $stmt->fetchColumn();
        
        return $result ? json_decode($result, true) : null;
    } catch (PDOException $e) {
        error_log("Error getting gateway config: " . $e->getMessage());
        return null;
    }
}

/**
 * Log gateway transaction
 */
function logGatewayTransaction(array $data): bool {
    global $pdo;
    
    try {
        // Get gateway ID
        $stmt = $pdo->prepare("SELECT id FROM payment_gateways WHERE gateway_type = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$data['gateway_type']]);
        $gatewayId = $stmt->fetchColumn();
        
        if (!$gatewayId) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO gateway_transactions 
            (gateway_id, transaction_type, internal_ref, gateway_ref, amount, currency, status, response_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $gatewayId,
            $data['transaction_type'],
            $data['internal_ref'],
            $data['gateway_ref'] ?? null,
            $data['amount'],
            $data['currency'] ?? 'INR',
            $data['status'],
            json_encode($data['response_data'] ?? [])
        ]);
    } catch (PDOException $e) {
        error_log("Error logging gateway transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Get active auto payout schedules
 */
function getActiveAutoPayouts(): array {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT ap.*, pg.name as gateway_name, pg.gateway_type
            FROM auto_payouts ap
            JOIN payment_gateways pg ON ap.gateway_id = pg.id
            WHERE ap.is_active = 1 AND ap.next_run <= NOW()
            ORDER BY ap.next_run
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting auto payouts: " . $e->getMessage());
        return [];
    }
}

/**
 * Process auto payout
 */
function processAutoPayout(int $autoPayoutId): bool {
    global $pdo;
    
    try {
        // Get auto payout details
        $stmt = $pdo->prepare("
            SELECT ap.*, pg.gateway_type
            FROM auto_payouts ap
            JOIN payment_gateways pg ON ap.gateway_id = pg.id
            WHERE ap.id = ?
        ");
        $stmt->execute([$autoPayoutId]);
        $autoPayout = $stmt->fetch();
        
        if (!$autoPayout) {
            return false;
        }
        
        // Get pending withdrawal requests within amount limits
        $stmt = $pdo->prepare("
            SELECT * FROM withdrawal_requests
            WHERE status = 'pending' 
            AND amount >= ? 
            AND (? IS NULL OR amount <= ?)
            ORDER BY created_at
        ");
        $stmt->execute([
            $autoPayout['min_amount'],
            $autoPayout['max_amount'],
            $autoPayout['max_amount']
        ]);
        $requests = $stmt->fetchAll();
        
        if (empty($requests)) {
            // No requests to process, update next run
            updateNextPayoutRun($autoPayoutId, $autoPayout['frequency']);
            return true;
        }
        
        // Create payout batch
        $batchNumber = 'BATCH' . time();
        $totalAmount = array_sum(array_column($requests, 'amount'));
        $totalCount = count($requests);
        
        $stmt = $pdo->prepare("
            INSERT INTO payout_batches 
            (batch_number, total_amount, total_count, gateway_id, status)
            VALUES (?, ?, ?, ?, 'processing')
        ");
        $stmt->execute([$batchNumber, $totalAmount, $totalCount, $autoPayout['gateway_id']]);
        $batchId = $pdo->lastInsertId();
        
        $successCount = 0;
        $failedCount = 0;
        
        // Process each request
        foreach ($requests as $request) {
            $result = processIndividualPayout($request, $autoPayout['gateway_type']);
            
            if ($result) {
                $successCount++;
                // Update withdrawal request status
                $stmt = $pdo->prepare("UPDATE withdrawal_requests SET status = 'completed', processed_at = NOW() WHERE id = ?");
                $stmt->execute([$request['id']]);
            } else {
                $failedCount++;
            }
        }
        
        // Update batch status
        $batchStatus = ($successCount === $totalCount) ? 'completed' : (($successCount > 0) ? 'partial' : 'failed');
        $stmt = $pdo->prepare("
            UPDATE payout_batches 
            SET status = ?, success_count = ?, failed_count = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$batchStatus, $successCount, $failedCount, $batchId]);
        
        // Update auto payout last run and calculate next run
        $stmt = $pdo->prepare("UPDATE auto_payouts SET last_run = NOW() WHERE id = ?");
        $stmt->execute([$autoPayoutId]);
        
        updateNextPayoutRun($autoPayoutId, $autoPayout['frequency']);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error processing auto payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Process individual payout
 */
function processIndividualPayout(array $request, string $gatewayType): bool {
    try {
        $payoutData = [
            'amount' => $request['amount'],
            'reference_id' => 'WD' . $request['id'],
            'beneficiary_id' => $request['beneficiary_id'] ?? null,
            'account_number' => $request['account_number'] ?? null,
            'ifsc_code' => $request['ifsc_code'] ?? null,
            'name' => $request['name'] ?? 'User',
            'purpose' => 'withdrawal',
            'remarks' => 'Withdrawal payout'
        ];
        
        switch ($gatewayType) {
            case 'razorpay':
                return razorpayCreatePayout($payoutData) !== null;
                
            case 'cashfree':
                return cashfreeCreatePayout($payoutData) !== null;
                
            default:
                return false;
        }
    } catch (Exception $e) {
        error_log("Error processing individual payout: " . $e->getMessage());
        return false;
    }
}

/**
 * Update next payout run time
 */
function updateNextPayoutRun(int $autoPayoutId, string $frequency): bool {
    global $pdo;
    
    try {
        $nextRun = null;
        
        switch ($frequency) {
            case 'daily':
                $nextRun = date('Y-m-d H:i:s', strtotime('+1 day'));
                break;
            case 'weekly':
                $nextRun = date('Y-m-d H:i:s', strtotime('+1 week'));
                break;
            case 'biweekly':
                $nextRun = date('Y-m-d H:i:s', strtotime('+2 weeks'));
                break;
            case 'monthly':
                $nextRun = date('Y-m-d H:i:s', strtotime('+1 month'));
                break;
        }
        
        if ($nextRun) {
            $stmt = $pdo->prepare("UPDATE auto_payouts SET next_run = ? WHERE id = ?");
            return $stmt->execute([$nextRun, $autoPayoutId]);
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error updating next payout run: " . $e->getMessage());
        return false;
    }
}

/**
 * Get available payment gateways
 */
function getAvailableGateways(): array {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT * FROM payment_gateways 
            WHERE is_active = 1 
            ORDER BY priority DESC, is_default DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting available gateways: " . $e->getMessage());
        return [];
    }
}

/**
 * Get default payment gateway
 */
function getDefaultGateway(): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT * FROM payment_gateways 
            WHERE is_active = 1 AND is_default = 1
            LIMIT 1
        ");
        $result = $stmt->fetch();
        
        if (!$result) {
            // If no default, get first active gateway
            $stmt = $pdo->query("
                SELECT * FROM payment_gateways 
                WHERE is_active = 1 
                ORDER BY priority DESC
                LIMIT 1
            ");
            $result = $stmt->fetch();
        }
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting default gateway: " . $e->getMessage());
        return null;
    }
}

/**
 * Switch to fallback gateway
 */
function switchToFallbackGateway(int $currentGatewayId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM payment_gateways 
            WHERE is_active = 1 AND id != ?
            ORDER BY priority DESC
            LIMIT 1
        ");
        $stmt->execute([$currentGatewayId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Error switching to fallback gateway: " . $e->getMessage());
        return null;
    }
}
