<?php
/**
 * Seller — Create Social Campaign
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/social-functions.php';

include 'includes/header.php';

$seller_id = (int)$_SESSION['seller_id'];

// Fetch platforms
try {
    $stmt = $pdo->query("SELECT * FROM social_platforms WHERE is_active = 1 ORDER BY sort_order");
    $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $platforms = [];
}

// Get seller wallet balance
try {
    $stmt = $pdo->prepare("SELECT balance FROM seller_wallet WHERE seller_id = ?");
    $stmt->execute([$seller_id]);
    $wallet_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $wallet_balance = $wallet_row ? (float)$wallet_row['balance'] : 0;
} catch (PDOException $e) {
    $wallet_balance = 0;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        // Validate inputs
        $platform_id           = (int)($_POST['platform_id'] ?? 0);
        $title                 = sanitizeInput($_POST['title'] ?? '');
        $description           = sanitizeInput($_POST['description'] ?? '');
        $content_url           = sanitizeInput($_POST['content_url'] ?? '');
        $task_type             = $_POST['task_type'] ?? 'watch';
        $reward_per_task       = (float)($_POST['reward_per_task'] ?? 0);
        $total_tasks_needed    = (int)($_POST['total_tasks_needed'] ?? 0);
        $watch_percent_required = (int)($_POST['watch_percent_required'] ?? 50);
        $category              = sanitizeInput($_POST['category'] ?? 'general');

        // Find platform slug
        $platform_slug = '';
        foreach ($platforms as $p) {
            if ((int)$p['id'] === $platform_id) {
                $platform_slug = $p['slug'];
                break;
            }
        }

        if (!$platform_id || !$title || !$content_url || $reward_per_task <= 0 || $total_tasks_needed < 1) {
            $error = 'Please fill in all required fields correctly.';
        } elseif (!filter_var($content_url, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid content URL.';
        } else {
            $allowed_task_types = ['watch','like','comment','follow','share','subscribe'];
            if (!in_array($task_type, $allowed_task_types)) $task_type = 'watch';

            $watch_percent_required = max(25, min(100, $watch_percent_required));

            $result = createCampaign($pdo, $seller_id, [
                'platform_id'            => $platform_id,
                'platform_slug'          => $platform_slug,
                'title'                  => $title,
                'description'            => $description,
                'content_url'            => $content_url,
                'task_type'              => $task_type,
                'reward_per_task'        => $reward_per_task,
                'total_tasks_needed'     => $total_tasks_needed,
                'watch_percent_required' => $watch_percent_required,
                'category'              => $category,
            ]);

            if ($result['success']) {
                $success = '🎉 Campaign created! Budget of ₹' . number_format($result['budget'], 2) . ' deducted. Awaiting admin approval.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$categories = ['general','education','tech','business','entertainment','lifestyle','news','sports','music','gaming'];
?>

<style>
.form-card { background: white; border-radius: 14px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
.form-label { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.4rem; display: block; }
.form-control, .form-select {
    width: 100%; padding: 0.65rem 0.9rem; border: 1px solid #d1d5db;
    border-radius: 8px; font-size: 0.95rem; transition: border-color 0.2s;
    background: white; color: #1a1a1a;
}
.form-control:focus, .form-select:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
.budget-preview { background: #f0f0ff; border-radius: 10px; padding: 1rem 1.25rem; margin-top: 1rem; }
.thumbnail-preview { max-width: 320px; border-radius: 8px; margin-top: 0.5rem; display: none; }
.url-hint { font-size: 0.78rem; color: #888; margin-top: 0.3rem; }
</style>

<h2 style="margin-bottom:1.5rem;font-size:1.5rem;font-weight:700;">📢 Create New Campaign</h2>

<?php if ($error): ?>
<div style="background:#f8d7da;color:#721c24;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div style="background:#d4edda;color:#155724;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div style="background:#fff3cd;color:#856404;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;">
    💳 Wallet Balance: <strong>₹<?php echo number_format($wallet_balance, 2); ?></strong>
</div>

<form method="POST" id="campaign-form">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <div class="form-card">
        <h4 style="margin-bottom:1.25rem;">📱 Platform &amp; Content</h4>

        <div style="margin-bottom:1rem;">
            <label class="form-label">Platform *</label>
            <select name="platform_id" id="platform_id" class="form-select" required>
                <option value="">Select platform...</option>
                <?php foreach ($platforms as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"
                        data-slug="<?php echo htmlspecialchars($p['slug']); ?>"
                        data-embed="<?php echo $p['embed_supported'] ? '1' : '0'; ?>"
                        <?php echo (isset($_POST['platform_id']) && (int)$_POST['platform_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom:1rem;">
            <label class="form-label">Content URL *</label>
            <input type="url" name="content_url" id="content_url" class="form-control"
                   placeholder="https://www.youtube.com/watch?v=..."
                   value="<?php echo htmlspecialchars($_POST['content_url'] ?? ''); ?>" required>
            <div class="url-hint" id="url-hint">Paste your YouTube, Instagram, Facebook, or other platform URL.</div>
            <img id="thumbnail-preview" class="thumbnail-preview" src="" alt="Thumbnail preview">
        </div>

        <div style="margin-bottom:1rem;">
            <label class="form-label">Campaign Title *</label>
            <input type="text" name="title" class="form-control" maxlength="255"
                   placeholder="E.g. Watch my new tech review video!"
                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
        </div>

        <div style="margin-bottom:1rem;">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Brief description of your content..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
    </div>

    <div class="form-card">
        <h4 style="margin-bottom:1.25rem;">🎯 Task Settings</h4>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label class="form-label">Task Type</label>
                <select name="task_type" class="form-select">
                    <?php foreach (['watch'=>'👁️ Watch','like'=>'👍 Like','comment'=>'💬 Comment','follow'=>'➕ Follow','share'=>'🔁 Share','subscribe'=>'🔔 Subscribe'] as $v => $l): ?>
                    <option value="<?php echo $v; ?>" <?php echo (($_POST['task_type'] ?? 'watch') === $v) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo (($_POST['category'] ?? 'general') === $cat) ? 'selected' : ''; ?>><?php echo ucfirst($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
                <label class="form-label">Reward per Task (₹) *</label>
                <input type="number" name="reward_per_task" id="reward_per_task" class="form-control"
                       min="0.5" step="0.5" placeholder="e.g. 2.00"
                       value="<?php echo htmlspecialchars($_POST['reward_per_task'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="form-label">Number of Tasks Needed *</label>
                <input type="number" name="total_tasks_needed" id="total_tasks_needed" class="form-control"
                       min="1" placeholder="e.g. 100"
                       value="<?php echo htmlspecialchars($_POST['total_tasks_needed'] ?? ''); ?>" required>
            </div>
        </div>

        <div style="margin-bottom:1rem;">
            <label class="form-label">Minimum Watch % Required: <span id="watch-pct-val"><?php echo htmlspecialchars($_POST['watch_percent_required'] ?? '50'); ?></span>%</label>
            <input type="range" name="watch_percent_required" id="watch_pct_slider"
                   min="25" max="100" step="5" value="<?php echo htmlspecialchars($_POST['watch_percent_required'] ?? '50'); ?>"
                   style="width:100%;">
            <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:#888;">
                <span>25%</span><span>50%</span><span>75%</span><span>100%</span>
            </div>
        </div>

        <div class="budget-preview" id="budget-preview">
            <div style="font-weight:700;margin-bottom:0.5rem;">💰 Budget Estimate</div>
            <div style="font-size:0.9rem;color:#555;">
                <div>Tasks × Reward: ₹<span id="base-cost">0.00</span></div>
                <div>Platform Fee (20%): ₹<span id="fee-cost">0.00</span></div>
                <div style="font-weight:700;margin-top:0.5rem;font-size:1rem;">Total Budget: ₹<span id="total-cost">0.00</span></div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;padding:0.9rem;font-size:1rem;">
        🚀 Submit Campaign
    </button>
</form>

<script>
(function() {
    var rewardEl   = document.getElementById('reward_per_task');
    var tasksEl    = document.getElementById('total_tasks_needed');
    var sliderEl   = document.getElementById('watch_pct_slider');
    var pctValEl   = document.getElementById('watch-pct-val');
    var platformEl = document.getElementById('platform_id');
    var urlEl      = document.getElementById('content_url');
    var thumbEl    = document.getElementById('thumbnail-preview');
    var urlHint    = document.getElementById('url-hint');

    sliderEl.addEventListener('input', function() {
        pctValEl.textContent = this.value;
    });

    function updateBudget() {
        var r = parseFloat(rewardEl.value) || 0;
        var t = parseInt(tasksEl.value) || 0;
        var base = r * t;
        var fee  = base * 0.20;
        var total = base + fee;
        document.getElementById('base-cost').textContent  = base.toFixed(2);
        document.getElementById('fee-cost').textContent   = fee.toFixed(2);
        document.getElementById('total-cost').textContent = total.toFixed(2);
    }

    rewardEl.addEventListener('input', updateBudget);
    tasksEl.addEventListener('input', updateBudget);
    updateBudget();

    function extractYtId(url) {
        var patterns = [
            /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/
        ];
        for (var i = 0; i < patterns.length; i++) {
            var m = url.match(patterns[i]);
            if (m) return m[1];
        }
        return null;
    }

    function updateThumbnail() {
        var sel = platformEl.options[platformEl.selectedIndex];
        var slug = sel ? sel.getAttribute('data-slug') : '';
        var url = urlEl.value;

        if (slug === 'youtube') {
            var vid = extractYtId(url);
            if (vid) {
                thumbEl.src = 'https://img.youtube.com/vi/' + vid + '/mqdefault.jpg';
                thumbEl.style.display = 'block';
                urlHint.textContent = 'YouTube video detected! ID: ' + vid;
            } else {
                thumbEl.style.display = 'none';
                urlHint.textContent = 'Paste your YouTube video URL.';
            }
        } else {
            thumbEl.style.display = 'none';
            urlHint.textContent = 'Paste your ' + (sel ? sel.textContent.trim() : 'platform') + ' content URL.';
        }
    }

    platformEl.addEventListener('change', updateThumbnail);
    urlEl.addEventListener('input', updateThumbnail);
})();
</script>

</div><!-- .main-content -->
</body>
</html>
