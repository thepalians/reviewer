<?php
/**
 * API — Social Watch Heartbeat Tracker
 * Receives watch-time heartbeats and returns current progress
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/social-functions.php';

header('Content-Type: application/json');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

// CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$campaign_id   = (int)($_POST['campaign_id'] ?? 0);
$watch_seconds = max(0, (int)($_POST['watch_seconds'] ?? 0));
$tab_active    = (int)(bool)($_POST['tab_active'] ?? 1);

if (!$campaign_id) {
    echo json_encode(['error' => 'Invalid campaign']);
    exit;
}

// Rate limiting: max 1 heartbeat per 5 seconds per user per campaign
$rate_key = "hb_{$user_id}_{$campaign_id}";
// Simple rate-limit via DB last_heartbeat check
try {
    // Fetch campaign to get watch_percent_required and video_duration
    $stmt = $pdo->prepare("SELECT id, watch_percent_required, reward_per_task, COALESCE(video_duration, 300) AS video_duration FROM social_campaigns WHERE id = ? AND status = 'active' AND admin_approved = 1");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['error' => 'Campaign not active']);
        exit;
    }

    // Find or create completion record
    $stmt = $pdo->prepare("SELECT id, watch_duration FROM social_task_completions WHERE campaign_id = ? AND user_id = ?");
    $stmt->execute([$campaign_id, $user_id]);
    $completion = $stmt->fetch(PDO::FETCH_ASSOC);
    $prev_watch_seconds = 0;
    $session = null;

    if (!$completion) {
        // Start a new completion
        $ip = substr($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $stmt = $pdo->prepare("
            INSERT INTO social_task_completions (campaign_id, user_id, watch_duration, watch_percent, status, ip_address, user_agent)
            VALUES (?, ?, ?, 0, 'watching', ?, ?)
        ");
        $stmt->execute([$campaign_id, $user_id, $watch_seconds, $ip, $ua]);
        $completion_id = (int)$pdo->lastInsertId();
        $server_heartbeats = 0;
    } else {
        $completion_id = (int)$completion['id'];
        $prev_watch_seconds = (int)($completion['watch_duration'] ?? 0);

        // Check status — don't update if already claimed
        $stmt = $pdo->prepare("SELECT status, watch_duration FROM social_task_completions WHERE id = ?");
        $stmt->execute([$completion_id]);
        $comp_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($comp_row && in_array($comp_row['status'], ['completed', 'verified'])) {
            echo json_encode(['watch_percent' => 100, 'reward_amount' => number_format((float)$campaign['reward_per_task'], 2), 'already_done' => true]);
            exit;
        }

        // Rate limit: check last heartbeat on session
        $stmt = $pdo->prepare("SELECT id, last_heartbeat, heartbeat_count, last_watch_seconds FROM social_watch_sessions WHERE completion_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$completion_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        $server_heartbeats = $session ? (int)$session['heartbeat_count'] : 0;
        if ($session && $session['last_heartbeat']) {
            $diff = time() - strtotime($session['last_heartbeat']);
            if ($diff < 5) {
                // Too fast — return current progress without updating
                $stmt2 = $pdo->prepare("SELECT watch_percent FROM social_task_completions WHERE id = ?");
                $stmt2->execute([$completion_id]);
                $row = $stmt2->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['watch_percent' => $row['watch_percent'] ?? 0, 'reward_amount' => number_format((float)$campaign['reward_per_task'], 2)]);
                exit;
            }
        }
    }

    // Anti-cheat: cap client-reported watch_seconds to 2× elapsed real time per interval
    if ($session && $session['last_heartbeat']) {
        $diff       = time() - strtotime($session['last_heartbeat']);
        $last_watch = (int)($session['last_watch_seconds'] ?? $prev_watch_seconds);
        $client_jump = $watch_seconds - $last_watch;
        $max_allowed = $diff * 2 + 5; // 2× real time + 5s grace
        if ($diff > 0 && $client_jump > $max_allowed) {
            $watch_seconds = $last_watch + $max_allowed;
        }
    } elseif ($prev_watch_seconds === 0 && $watch_seconds > 20) {
        $watch_seconds = min($watch_seconds, 20);
    }

    // Server-side anti-cheat: calculate watch percent from server-tracked heartbeats
    // Each heartbeat = ~10 seconds of genuine watch time (heartbeat interval is 10s)
    // Allow 15 seconds grace to cover the first heartbeat before session is created
    $server_watched_seconds = $server_heartbeats * 10;
    $actual_seconds = min($watch_seconds, $server_watched_seconds + 15);
    $assumed_duration = max(1, (int)($campaign['video_duration'] ?? 300));
    $watch_percent = min(100.0, round(($actual_seconds / $assumed_duration) * 100, 2));

    // Update completion
    $pdo->prepare("UPDATE social_task_completions SET watch_duration = ?, watch_percent = ?, status = 'watching' WHERE id = ?")
        ->execute([$watch_seconds, $watch_percent, $completion_id]);

    // Update or insert watch session
    $stmt = $pdo->prepare("SELECT id FROM social_watch_sessions WHERE completion_id = ?");
    $stmt->execute([$completion_id]);
    $ws = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ws) {
        $pdo->prepare("UPDATE social_watch_sessions SET heartbeat_count = heartbeat_count + 1, last_heartbeat = NOW(), tab_active = ?, last_watch_seconds = ? WHERE completion_id = ?")
            ->execute([$tab_active, $watch_seconds, $completion_id]);
    } else {
        $pdo->prepare("INSERT INTO social_watch_sessions (completion_id, heartbeat_count, last_heartbeat, tab_active, last_watch_seconds) VALUES (?, 1, NOW(), ?, ?)")
            ->execute([$completion_id, $tab_active, $watch_seconds]);
    }

    echo json_encode([
        'watch_percent'  => $watch_percent,
        'watch_seconds'  => $watch_seconds,
        'required_pct'   => (int)$campaign['watch_percent_required'],
        'reward_amount'  => number_format((float)$campaign['reward_per_task'], 2),
        'unlocked'       => $watch_percent >= (float)$campaign['watch_percent_required'],
    ]);

} catch (PDOException $e) {
    error_log("social-track error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
