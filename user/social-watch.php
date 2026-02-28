<?php
/**
 * User — Social Watch & Earn
 * Watch embedded content and claim reward
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/social-functions.php';

if (!isUser()) {
    redirect(APP_URL . '/index.php');
}

$user_id      = (int)$_SESSION['user_id'];
$campaign_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_page = 'social-hub';

if (!$campaign_id) {
    redirect(APP_URL . '/user/social-hub.php');
}

// Fetch campaign
try {
    $stmt = $pdo->prepare("
        SELECT sc.*, sp.name AS platform_name, sp.slug AS platform_slug,
               sp.icon AS platform_icon, sp.color AS platform_color,
               s.name AS seller_name
        FROM social_campaigns sc
        JOIN social_platforms sp ON sp.id = sc.platform_id
        JOIN sellers s ON s.id = sc.seller_id
        WHERE sc.id = ? AND sc.status = 'active' AND sc.admin_approved = 1
    ");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("social-watch fetch error: " . $e->getMessage());
    $campaign = null;
}

if (!$campaign) {
    redirect(APP_URL . '/user/social-hub.php');
}

// Check if already completed
try {
    $stmt = $pdo->prepare("SELECT id FROM social_task_completions WHERE campaign_id = ? AND user_id = ? AND status IN ('completed','verified')");
    $stmt->execute([$campaign_id, $user_id]);
    $already_done = (bool)$stmt->fetch();
} catch (PDOException $e) {
    $already_done = false;
}

if ($already_done) {
    redirect(APP_URL . '/user/social-hub.php');
}

// CSRF token
$csrf_token = generateCSRFToken();

include '../includes/security.php';

// Build embed based on platform
$embed_html = $campaign['embed_code'] ?: generateEmbed($campaign['platform_slug'], $campaign['content_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>▶️ Watch &amp; Earn — <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .watch-container { max-width: 860px; margin: 0 auto; }
        .player-wrap {
            background: #000; border-radius: 14px; overflow: hidden;
            margin-bottom: 1.5rem; position: relative;
        }
        .player-wrap iframe, .player-wrap blockquote { width: 100% !important; }
        .info-card {
            background: white; border-radius: 14px; padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 1.2rem;
        }
        .timer-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .timer-display {
            font-size: 1.3rem; font-weight: 700; background: #f0f0f0;
            border-radius: 8px; padding: 0.5rem 1rem; min-width: 120px; text-align: center;
        }
        .progress-bar-wrap { flex: 1; background: #f0f0f0; border-radius: 6px; height: 12px; }
        .progress-bar-fill { height: 12px; border-radius: 6px; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.5s; }
        .req-label { font-size: 0.85rem; color: #888; margin-bottom: 0.75rem; }
        .reward-display {
            font-size: 1.6rem; font-weight: 800; color: #28a745; margin-bottom: 1rem;
        }
        .btn-claim {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white; border: none; border-radius: 10px;
            padding: 0.8rem 2rem; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s; width: 100%;
        }
        .btn-claim:disabled { background: #ccc; cursor: not-allowed; }
        .btn-claim:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(40,167,69,0.4); }
        .tab-warning { background: #fff3cd; border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.85rem; margin-bottom: 0.75rem; display: none; }
        .campaign-meta { font-size: 0.85rem; color: #888; margin-top: 0.25rem; }
        .platform-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.7rem; border-radius: 20px; font-size: 0.8rem;
            font-weight: 600; color: white; margin-bottom: 0.5rem;
        }
        /* Confetti canvas */
        #confetti-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; display: none; }
        .success-banner {
            display: none; background: linear-gradient(135deg, #28a745, #20c997);
            color: white; border-radius: 12px; padding: 1.5rem; text-align: center; margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<canvas id="confetti-canvas"></canvas>
<div class="main-content">
    <div class="watch-container">

        <div style="margin-bottom:1rem;">
            <a href="<?php echo APP_URL; ?>/user/social-hub.php" style="color:#667eea;text-decoration:none;">← Back to Social Hub</a>
        </div>

        <div class="success-banner" id="success-banner">
            <div style="font-size:2rem;">🎉</div>
            <h3>Reward Claimed Successfully!</h3>
            <p>₹<?php echo number_format((float)$campaign['reward_per_task'], 2); ?> has been added to your wallet.</p>
            <a href="<?php echo APP_URL; ?>/user/social-hub.php" style="color:white;text-decoration:underline;">← Back to Social Hub</a>
        </div>

        <!-- Player -->
        <div class="player-wrap">
            <?php echo $embed_html; ?>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <span class="platform-badge" style="background:<?php echo htmlspecialchars($campaign['platform_color']); ?>">
                <i class="<?php echo htmlspecialchars($campaign['platform_icon']); ?>"></i>
                <?php echo htmlspecialchars($campaign['platform_name']); ?>
            </span>
            <h2 style="font-size:1.2rem;font-weight:700;margin:0.25rem 0;"><?php echo htmlspecialchars($campaign['title']); ?></h2>
            <div class="campaign-meta">By <?php echo htmlspecialchars($campaign['seller_name']); ?></div>
        </div>

        <!-- Watch Progress -->
        <div class="info-card">
            <h4 style="margin-bottom:1rem;">⏱️ Watch Progress</h4>
            <div class="tab-warning" id="tab-warning">⚠️ Timer paused — please keep this tab active while watching.</div>
            <div class="timer-row">
                <div class="timer-display" id="timer-display">0:00</div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill" id="progress-fill" style="width:0%"></div>
                </div>
                <div id="pct-label" style="font-weight:700;min-width:40px;">0%</div>
            </div>
            <div class="req-label">
                Watch at least <strong><?php echo (int)$campaign['watch_percent_required']; ?>%</strong> to claim your reward.
            </div>
            <div class="reward-display">🎁 ₹<?php echo number_format((float)$campaign['reward_per_task'], 2); ?></div>
            <button class="btn-claim" id="claim-btn" disabled>🎁 Claim Reward (watch more to unlock)</button>
        </div>

    </div>
</div>

<script>
(function() {
    var campaignId   = <?php echo $campaign_id; ?>;
    var requiredPct  = <?php echo (int)$campaign['watch_percent_required']; ?>;
    var csrfToken    = <?php echo json_encode($csrf_token); ?>;
    var apiBase      = <?php echo json_encode(APP_URL); ?>;

    var seconds      = 0;
    var tabActive    = true;
    var claimed      = false;
    var lastHb       = 0;
    var timerInterval = null;
    var isYTPlatform = <?php echo json_encode($campaign['platform_slug'] === 'youtube'); ?>;

    var timerEl   = document.getElementById('timer-display');
    var fillEl    = document.getElementById('progress-fill');
    var pctLabel  = document.getElementById('pct-label');
    var claimBtn  = document.getElementById('claim-btn');
    var tabWarn   = document.getElementById('tab-warning');

    function formatTime(s) {
        var m = Math.floor(s / 60);
        var sec = s % 60;
        return m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    function updateUI() {
        // Estimate total required seconds (assume 5 min default; refined server-side)
        var pct = Math.min(100, Math.round(seconds / 3 * 100) / 100); // placeholder pct
        timerEl.textContent = formatTime(seconds);
    }

    function sendHeartbeat() {
        if (claimed) return;
        var now = Date.now();
        if (now - lastHb < 5000) return; // rate-limit
        lastHb = now;

        var fd = new FormData();
        fd.append('campaign_id', campaignId);
        fd.append('watch_seconds', seconds);
        fd.append('tab_active', tabActive ? '1' : '0');
        fd.append('csrf_token', csrfToken);

        fetch(apiBase + '/api/social-track.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.watch_percent !== undefined) {
                    var pct = Math.min(100, Math.round(parseFloat(data.watch_percent)));
                    fillEl.style.width = pct + '%';
                    pctLabel.textContent = pct + '%';

                    if (pct >= requiredPct && !claimed) {
                        claimBtn.disabled = false;
                        claimBtn.textContent = '🎁 Claim ₹' + data.reward_amount + ' Reward';
                    }
                }
            })
            .catch(function(){});
    }

    // Start timer
    timerInterval = setInterval(function() {
        // For YouTube: only count when video is actually playing (not paused/buffering)
        var videoPlaying = !isYTPlatform || window._ytPlaying !== false;
        if (tabActive && !claimed && videoPlaying) {
            seconds++;
            timerEl.textContent = formatTime(seconds);
        }
        if (seconds % 10 === 0) {
            sendHeartbeat();
        }
    }, 1000);

    // Tab visibility
    document.addEventListener('visibilitychange', function() {
        tabActive = !document.hidden;
        tabWarn.style.display = tabActive ? 'none' : 'block';
        sendHeartbeat();
    });

    // Claim button
    claimBtn.addEventListener('click', function() {
        if (claimed || claimBtn.disabled) return;
        claimBtn.disabled = true;
        claimBtn.textContent = '⏳ Processing...';

        var fd = new FormData();
        fd.append('campaign_id', campaignId);
        fd.append('watch_seconds', seconds);
        fd.append('tab_active', tabActive ? '1' : '0');
        fd.append('csrf_token', csrfToken);

        fetch(apiBase + '/api/social-claim.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    claimed = true;
                    clearInterval(timerInterval);
                    document.getElementById('success-banner').style.display = 'block';
                    launchConfetti();
                    setTimeout(function(){
                        window.location.href = apiBase + '/user/social-hub.php';
                    }, 4000);
                } else {
                    claimBtn.disabled = false;
                    claimBtn.textContent = '🎁 Claim Reward';
                    alert(data.message || 'Could not claim reward. Please try again.');
                }
            })
            .catch(function() {
                claimBtn.disabled = false;
                claimBtn.textContent = '🎁 Claim Reward';
                alert('Network error. Please try again.');
            });
    });

    // Simple confetti animation
    function launchConfetti() {
        var canvas = document.getElementById('confetti-canvas');
        canvas.style.display = 'block';
        var ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        var pieces = [];
        var colors = ['#667eea','#764ba2','#28a745','#ffd700','#ff6b6b','#4ecdc4'];
        for (var i = 0; i < 120; i++) {
            pieces.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                w: 8 + Math.random() * 8,
                h: 4 + Math.random() * 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                speed: 2 + Math.random() * 4,
                angle: Math.random() * Math.PI * 2
            });
        }
        var anim;
        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var alive = false;
            pieces.forEach(function(p) {
                p.y += p.speed;
                p.angle += 0.05;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.angle);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
                ctx.restore();
                if (p.y < canvas.height + 20) alive = true;
            });
            if (alive) {
                anim = requestAnimationFrame(draw);
            } else {
                canvas.style.display = 'none';
            }
        }
        draw();
        setTimeout(function(){ cancelAnimationFrame(anim); canvas.style.display = 'none'; }, 5000);
    }

    // Initial heartbeat to start/resume session
    sendHeartbeat();
})();
</script>
<?php if ($campaign['platform_slug'] === 'youtube'): ?>
<script>
// YouTube IFrame API — track actual play time and detect seeking
window._ytPlaying = false;
window._ytLastTime = 0;
window._ytCreditedSeconds = 0; // cumulative genuinely-watched seconds
window._ytLastRealTime = 0;    // wall-clock ms when last position was recorded

window.onYouTubeIframeAPIReady = function() {
    var iframe = document.getElementById('yt-player');
    if (!iframe) return;
    var player = new YT.Player('yt-player', {
        events: {
            onReady: function(e) {
                window._ytLastTime = 0;
                window._ytLastRealTime = Date.now();
            },
            onStateChange: function(e) {
                var ct = (e.target && e.target.getCurrentTime) ? e.target.getCurrentTime() : 0;
                if (e.data === YT.PlayerState.PLAYING) {
                    var seekDelta = ct - window._ytLastTime;
                    // Detect forward seek: video jumped ahead more than 3s beyond expected play position
                    if (seekDelta > 3) {
                        // Penalise: reset credited seconds back by the skipped amount so
                        // the user must genuinely watch from the new position
                        window._ytCreditedSeconds = Math.max(0, window._ytCreditedSeconds - seekDelta);
                        // Override the main seconds counter to match credited seconds
                        if (typeof seconds !== 'undefined') {
                            seconds = Math.max(0, Math.min(seconds, window._ytCreditedSeconds));
                        }
                        window._ytPlaying = false;
                        setTimeout(function() { window._ytPlaying = true; }, 800);
                    } else {
                        window._ytPlaying = true;
                    }
                    window._ytLastTime = ct;
                    window._ytLastRealTime = Date.now();
                } else {
                    // Paused / buffering / ended — accumulate credited seconds up to this point
                    if (window._ytPlaying && window._ytLastRealTime > 0) {
                        var elapsed = (Date.now() - window._ytLastRealTime) / 1000;
                        // Only credit if elapsed time is reasonable (≤ 2× real time, max 30s per interval)
                        var creditGain = Math.min(elapsed, 30);
                        window._ytCreditedSeconds += creditGain;
                    }
                    if (e.target && e.target.getCurrentTime) {
                        window._ytLastTime = e.target.getCurrentTime();
                    }
                    window._ytPlaying = false;
                    window._ytLastRealTime = 0;
                }
            }
        }
    });

    // Poll every second to accumulate credits while playing
    setInterval(function() {
        if (!window._ytPlaying || !player || typeof player.getCurrentTime !== 'function') return;
        var ct = player.getCurrentTime();
        var now = Date.now();
        if (window._ytLastRealTime > 0) {
            var elapsed = (now - window._ytLastRealTime) / 1000;
            var posDelta = ct - window._ytLastTime;
            // Accept position delta only if it is close to elapsed wall-clock time (≤ 2×)
            if (posDelta >= 0 && posDelta <= elapsed * 2 + 1) {
                // Credit actual playback progress, capped to elapsed real time
                window._ytCreditedSeconds += Math.min(posDelta, elapsed);
                // Keep main seconds counter in sync with credited time
                if (typeof seconds !== 'undefined') {
                    seconds = Math.max(seconds, Math.floor(window._ytCreditedSeconds));
                }
            } else if (posDelta > elapsed * 2 + 1) {
                // Position advanced too fast — likely a seek, penalise by removing excess
                var excess = Math.max(0, posDelta - elapsed);
                window._ytCreditedSeconds = Math.max(0, window._ytCreditedSeconds - excess);
                if (typeof seconds !== 'undefined') {
                    seconds = Math.max(0, Math.min(seconds, Math.floor(window._ytCreditedSeconds)));
                }
            }
        }
        window._ytLastTime = ct;
        window._ytLastRealTime = now;
    }, 1000);
};
</script>
<script src="https://www.youtube.com/iframe_api"></script>
<?php endif; ?>
</body>
</html>
