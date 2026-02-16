#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Queue Worker - Background Job Processor
 * 
 * This script processes jobs from the job queue table.
 * Run as a cron job: * * * * * /usr/bin/php /path/to/queue-worker.php
 * Or keep running: while true; do php queue-worker.php; sleep 5; done
 */

// Load configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/queue-functions.php';
require_once __DIR__ . '/../includes/auto-payout-functions.php';
require_once __DIR__ . '/../includes/redis-cache-functions.php';
require_once __DIR__ . '/../includes/bi-dashboard-functions.php';

// Set time limit
set_time_limit(300); // 5 minutes max

// Log start
error_log("[Queue Worker] Starting job processing at " . date('Y-m-d H:i:s'));

// Process jobs
$processedCount = 0;
$maxJobs = 10; // Process max 10 jobs per run

while ($processedCount < $maxJobs) {
    try {
        // Get next job
        $job = getNextJob();
        
        if (!$job) {
            // No more jobs to process
            break;
        }
        
        error_log("[Queue Worker] Processing job #{$job['id']} ({$job['job_type']})");
        
        // Process the job
        $success = processJob($job);
        
        if ($success) {
            // Mark as completed
            completeJob($job['id']);
            error_log("[Queue Worker] Job #{$job['id']} completed successfully");
            $processedCount++;
        } else {
            // Mark as failed
            $errorMessage = "Job processing failed";
            failJob($job['id'], $errorMessage);
            error_log("[Queue Worker] Job #{$job['id']} failed: $errorMessage");
        }
        
    } catch (Exception $e) {
        error_log("[Queue Worker] Error processing job: " . $e->getMessage());
        
        if (isset($job) && $job) {
            failJob($job['id'], $e->getMessage());
        }
        
        break; // Stop processing on error
    }
}

// Clean old jobs
try {
    $cleaned = cleanOldJobs(30);
    if ($cleaned > 0) {
        error_log("[Queue Worker] Cleaned $cleaned old jobs");
    }
} catch (Exception $e) {
    error_log("[Queue Worker] Error cleaning old jobs: " . $e->getMessage());
}

// Clean expired cache
try {
    $cleaned = cacheCleanExpired();
    if ($cleaned > 0) {
        error_log("[Queue Worker] Cleaned $cleaned expired cache entries");
    }
} catch (Exception $e) {
    error_log("[Queue Worker] Error cleaning cache: " . $e->getMessage());
}

// Log completion
error_log("[Queue Worker] Finished processing $processedCount jobs at " . date('Y-m-d H:i:s'));

// Exit
exit(0);
