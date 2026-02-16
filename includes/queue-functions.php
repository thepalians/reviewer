<?php
declare(strict_types=1);

/**
 * Job Queue Functions
 * Background job processing system
 */

/**
 * Add job to queue
 */
function queueJob(string $jobType, array $payload, int $priority = 5, ?string $scheduledAt = null): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO job_queue 
            (job_type, payload, priority, scheduled_at, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([
            $jobType,
            json_encode($payload),
            $priority,
            $scheduledAt
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error queuing job: " . $e->getMessage());
        return null;
    }
}

/**
 * Get next pending job
 */
function getNextJob(): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM job_queue 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $job = $stmt->fetch();
        
        if ($job) {
            // Mark as processing
            $stmt = $pdo->prepare("
                UPDATE job_queue 
                SET status = 'processing', started_at = NOW(), attempts = attempts + 1
                WHERE id = ?
            ");
            $stmt->execute([$job['id']]);
        }
        
        return $job ?: null;
    } catch (PDOException $e) {
        error_log("Error getting next job: " . $e->getMessage());
        return null;
    }
}

/**
 * Complete job
 */
function completeJob(int $jobId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE job_queue 
            SET status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$jobId]);
    } catch (PDOException $e) {
        error_log("Error completing job: " . $e->getMessage());
        return false;
    }
}

/**
 * Fail job
 */
function failJob(int $jobId, string $errorMessage): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT attempts, max_attempts FROM job_queue WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        
        if (!$job) return false;
        
        $status = ($job['attempts'] >= $job['max_attempts']) ? 'failed' : 'pending';
        
        $stmt = $pdo->prepare("
            UPDATE job_queue 
            SET status = ?, error_message = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $errorMessage, $jobId]);
    } catch (PDOException $e) {
        error_log("Error failing job: " . $e->getMessage());
        return false;
    }
}

/**
 * Process job
 */
function processJob(array $job): bool {
    try {
        $payload = json_decode($job['payload'], true);
        
        switch ($job['job_type']) {
            case 'send_email':
                return processEmailJob($payload);
                
            case 'send_notification':
                return processNotificationJob($payload);
                
            case 'generate_report':
                return processReportJob($payload);
                
            case 'auto_payout':
                return processAutoPayout($payload['auto_payout_id']);
                
            case 'cleanup_cache':
                return cacheCleanExpired() >= 0;
                
            case 'update_kpi':
                if (isset($payload['kpi_id'], $payload['value'])) {
                    return recordKPIHistory($payload['kpi_id'], $payload['value']);
                }
                return false;
                
            default:
                error_log("Unknown job type: " . $job['job_type']);
                return false;
        }
    } catch (Exception $e) {
        error_log("Job processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process email job
 */
function processEmailJob(array $payload): bool {
    // Implement email sending logic
    // This is a placeholder
    return true;
}

/**
 * Process notification job
 */
function processNotificationJob(array $payload): bool {
    // Implement notification sending logic
    // This is a placeholder
    return true;
}

/**
 * Process report generation job
 */
function processReportJob(array $payload): bool {
    // Implement report generation logic
    // This is a placeholder
    return true;
}

/**
 * Get job statistics
 */
function getJobStats(): array {
    global $pdo;
    
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM job_queue GROUP BY status");
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        // Add defaults for missing statuses
        foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
            if (!isset($stats[$status])) {
                $stats[$status] = 0;
            }
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting job stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean old completed jobs
 */
function cleanOldJobs(int $daysOld = 30): int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM job_queue 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error cleaning old jobs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Retry failed job
 */
function retryJob(int $jobId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE job_queue 
            SET status = 'pending', attempts = 0, error_message = NULL
            WHERE id = ? AND status = 'failed'
        ");
        return $stmt->execute([$jobId]);
    } catch (PDOException $e) {
        error_log("Error retrying job: " . $e->getMessage());
        return false;
    }
}
