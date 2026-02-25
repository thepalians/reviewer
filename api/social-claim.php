<?php
/**
 * API — Social Task Claim Reward
 * Validates watch time and credits user wallet
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/social-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$campaign_id   = (int)($_POST['campaign_id'] ?? 0);
$watch_seconds = max(0, (int)($_POST['watch_seconds'] ?? 0));

if (!$campaign_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid campaign']);
    exit;
}

// Fetch completion record to get server-tracked watch_percent and heartbeat count
try {
    $stmt = $pdo->prepare("
        SELECT stc.*, sc.watch_percent_required, sc.reward_per_task, sc.title,
               COALESCE(sc.video_duration, 300) AS video_duration,
               COALESCE(sws.heartbeat_count, 0) AS heartbeat_count
        FROM social_task_completions stc
        JOIN social_campaigns sc ON sc.id = stc.campaign_id
        LEFT JOIN social_watch_sessions sws ON sws.completion_id = stc.id
        WHERE stc.campaign_id = ? AND stc.user_id = ?
    ");
    $stmt->execute([$campaign_id, $user_id]);
    $completion = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("social-claim fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

// If no completion record exists yet, create one from client data
if (!$completion) {
    // Validate watch percent from client using server-side formula (no heartbeats yet, use grace only)
    $actual_seconds = min($watch_seconds, 15); // no heartbeats yet — minimal grace
    $watch_percent = min(100.0, round(($actual_seconds / 300) * 100, 2));
    $watch_data = ['watch_percent' => $watch_percent, 'watch_seconds' => $watch_seconds];
} else {
    // Use server-tracked value
    if (in_array($completion['status'], ['completed', 'verified'])) {
        echo json_encode(['success' => false, 'message' => 'You have already claimed this reward.']);
        exit;
    }

    // Validate server-tracked heartbeat count against minimum required
    $heartbeat_count   = (int)$completion['heartbeat_count'];
    $assumed_duration  = max(1, (int)$completion['video_duration']);
    $watch_pct_req     = (float)$completion['watch_percent_required'];
    $min_heartbeats    = (int)ceil($assumed_duration * ($watch_pct_req / 100) / 10);
    if ($heartbeat_count < $min_heartbeats) {
        echo json_encode(['success' => false, 'message' => 'Minimum watch time not verified. Please continue watching the video.']);
        exit;
    }

    $watch_percent = (float)$completion['watch_percent'];
    $watch_data = ['watch_percent' => $watch_percent, 'watch_seconds' => $watch_seconds];
}

$result = completeSocialTask($pdo, $campaign_id, $user_id, $watch_data);
echo json_encode($result);
