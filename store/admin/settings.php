<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();

$db        = getStoreDB();
$csrfToken = generateCsrfToken();
$success   = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token mismatch.';
    } else {
        $keys = [
            'site_name','site_tagline','site_description','site_email','site_phone','site_address',
            'razorpay_key_id','razorpay_key_secret','razorpay_test_mode',
            'currency','gst_number','business_name','footer_text',
        ];
        $stmt = $db->prepare('INSERT INTO store_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt->execute([$key, $val]);
        }
        $success = 'Settings saved successfully.';
    }
}

// Reload settings
$settingsRows = $db->query('SELECT setting_key, setting_value FROM store_settings')->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['setting_key']] = $r['setting_value'];

$sv = function(string $key, string $default = '') use ($settings): string {
    return $settings[$key] ?? $default;
};

$pageTitle = 'Settings';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-header">
    <span class="admin-header-title">Settings</span>
  </div>
  <div class="admin-content">
    <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

    <form method="POST" action="<?= STORE_URL ?>/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

      <!-- Site Info -->
      <div class="admin-card" style="margin-bottom:24px">
        <div class="admin-card-header"><h3>Site Information</h3></div>
        <div style="padding:24px">
          <div class="admin-form-grid">
            <div class="form-group">
              <label class="form-label">Site Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= h($sv('site_name')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Site Tagline</label>
              <input type="text" name="site_tagline" class="form-control" value="<?= h($sv('site_tagline')) ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Site Description</label>
              <textarea name="site_description" class="form-control" rows="3"><?= h($sv('site_description')) ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Support Email</label>
              <input type="email" name="site_email" class="form-control" value="<?= h($sv('site_email')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="site_phone" class="form-control" value="<?= h($sv('site_phone')) ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Address</label>
              <input type="text" name="site_address" class="form-control" value="<?= h($sv('site_address')) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Payment -->
      <div class="admin-card" style="margin-bottom:24px">
        <div class="admin-card-header"><h3>Payment — Razorpay</h3></div>
        <div style="padding:24px">
          <div class="alert alert-info" style="margin-bottom:20px">
            <i class="bi bi-info-circle"></i>
            Get your API keys from <a href="https://dashboard.razorpay.com" target="_blank" style="color:var(--accent-blue)">dashboard.razorpay.com</a>.
            Use Test keys for development, Live keys for production.
          </div>
          <div class="admin-form-grid">
            <div class="form-group">
              <label class="form-label">Razorpay Key ID</label>
              <input type="text" name="razorpay_key_id" class="form-control"
                     placeholder="rzp_test_..." value="<?= h($sv('razorpay_key_id')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Razorpay Key Secret</label>
              <input type="password" name="razorpay_key_secret" class="form-control"
                     placeholder="••••••••" value="<?= h($sv('razorpay_key_secret')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Test Mode</label>
              <select name="razorpay_test_mode" class="form-control">
                <option value="1" <?= $sv('razorpay_test_mode') === '1' ? 'selected' : '' ?>>Enabled (Test)</option>
                <option value="0" <?= $sv('razorpay_test_mode') === '0' ? 'selected' : '' ?>>Disabled (Live)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Currency</label>
              <input type="text" name="currency" class="form-control" value="<?= h($sv('currency', 'INR')) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Business -->
      <div class="admin-card" style="margin-bottom:24px">
        <div class="admin-card-header"><h3>Business Details</h3></div>
        <div style="padding:24px">
          <div class="admin-form-grid">
            <div class="form-group">
              <label class="form-label">Business Name</label>
              <input type="text" name="business_name" class="form-control" value="<?= h($sv('business_name')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">GST Number</label>
              <input type="text" name="gst_number" class="form-control" placeholder="GSTIN" value="<?= h($sv('gst_number')) ?>">
            </div>
            <div class="form-group full-width">
              <label class="form-label">Footer Text</label>
              <input type="text" name="footer_text" class="form-control" value="<?= h($sv('footer_text')) ?>">
            </div>
          </div>
        </div>
      </div>

      <div style="text-align:right">
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="bi bi-check"></i> Save Settings
        </button>
      </div>
    </form>
  </div>
</div>
</div>
<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
