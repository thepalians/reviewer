<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Handle logout
if (isset($_GET['logout'])) {
    adminLogout();
}

// Already logged in → redirect to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . STORE_URL . '/admin/dashboard.php');
    exit;
}

$error     = '';
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (adminLogin($username, $password)) {
            header('Location: ' . STORE_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            // Throttle brute-force attempts
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Palians</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= STORE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="admin-login-page">
  <div class="admin-login-card">
    <div class="admin-login-logo">
      <span>🏪</span>
      <h1>Palians Admin</h1>
      <p>Sign in to manage your store</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= STORE_URL ?>/admin/" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control"
               placeholder="Enter username" required autofocus autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Enter password" required autocomplete="current-password">
      </div>

      <div style="margin-top:24px">
        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="bi bi-lock"></i> Sign In
        </button>
      </div>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:0.78rem;color:var(--text-muted)">
      <a href="<?= STORE_URL ?>" style="color:var(--text-muted)">← Back to Store</a>
    </p>
  </div>
</div>
</body>
</html>
