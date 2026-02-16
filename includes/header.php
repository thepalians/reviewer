<?php
/**
 * Common Header File
 * DO NOT declare functions here
 */

// Include configuration and functions
require_once 'config.php';
require_once 'functions.php';

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Handle dark mode preference
if (!isset($_COOKIE['dark_mode'])) {
    setcookie('dark_mode', '0', time() + (86400 * 30), "/"); // 30 days
    $dark_mode = false;
} else {
    $dark_mode = $_COOKIE['dark_mode'] == '1';
}

// Toggle dark mode if requested
if (isset($_GET['toggle_dark'])) {
    $dark_mode = !$dark_mode;
    setcookie('dark_mode', $dark_mode ? '1' : '0', time() + (86400 * 30), "/");
    header("Location: " . str_replace('?toggle_dark=1', '', $_SERVER['REQUEST_URI']));
    exit();
}

// Check if user is logged in
$is_logged_in = isLoggedIn();
$is_admin = isAdmin();
$is_user = isUser();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviewer Task Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --dark-bg: #121212;
            --dark-card: #1e1e1e;
            --dark-text: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 70px;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            margin: 0 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-link.active {
            background-color: var(--primary-color);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .dark-mode-toggle {
            cursor: pointer;
            font-size: 1.2rem;
            transition: transform 0.3s;
        }
        
        .dark-mode-toggle:hover {
            transform: rotate(30deg);
        }
        
        [data-bs-theme="dark"] {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }
        
        [data-bs-theme="dark"] .card {
            background-color: var(--dark-card);
        }
        
        [data-bs-theme="dark"] .table {
            color: var(--dark-text);
        }
        
        .badge-online {
            background-color: #0f0;
            color: #000;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand" href="<?php echo $is_logged_in ? ($is_admin ? BASE_URL . '/admin/dashboard.php' : BASE_URL . '/user/dashboard.php') : BASE_URL . '/'; ?>">
                <i class="bi bi-check-circle-fill"></i> ReviewFlow
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($is_logged_in): ?>
                    <ul class="navbar-nav me-auto">
                        <?php if ($is_admin): ?>
                            <!-- Admin Links -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'assign_task.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/assign_task.php">
                                    <i class="bi bi-plus-circle"></i> Assign Task
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'pending_tasks.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/pending_tasks.php">
                                    <i class="bi bi-clock-history"></i> Pending Tasks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'completed_tasks.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/completed_tasks.php">
                                    <i class="bi bi-check2-circle"></i> Completed Tasks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/users.php">
                                    <i class="bi bi-people"></i> Users
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- User Links -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'tasks.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/tasks.php">
                                    <i class="bi bi-list-task"></i> My Tasks
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/user/history.php">
                                    <i class="bi bi-clock-history"></i> History
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Common Links -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/chatbot/">
                                <i class="bi bi-robot"></i> Chat Assistant
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/help.php">
                                <i class="bi bi-question-circle"></i> Help
                            </a>
                        </li>
                    </ul>
                <?php else: ?>
                    <!-- Public Navigation -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#how-it-works">
                                <i class="bi bi-info-circle"></i> How It Works
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/chatbot/">
                                <i class="bi bi-robot"></i> Chat Assistant
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/help.php">
                                <i class="bi bi-question-circle"></i> Help
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
                
                <!-- Right Side Items -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Dark Mode Toggle -->
                    <li class="nav-item me-3">
                        <a class="nav-link dark-mode-toggle" href="?toggle_dark=1" title="Toggle Dark Mode">
                            <?php if ($dark_mode): ?>
                                <i class="bi bi-sun-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-moon-fill"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <?php if ($is_logged_in): ?>
                        <!-- Notifications -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell-fill"></i>
                                <?php
                                // Count pending notifications safely
                                $notification_count = 0;
                                if (isset($pdo)) {
                                    try {
                                        if ($is_admin) {
                                            // Admin notifications: pending refund requests
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE status = 'refund_requested'");
                                            $stmt->execute();
                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $notification_count = $result['count'];
                                        } else {
                                            // User notifications: tasks near deadline
                                            $user_id = $_SESSION['user_id'];
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status != 'completed' AND deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
                                            $stmt->execute([$user_id]);
                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $notification_count = $result['count'];
                                        }
                                    } catch (Exception $e) {
                                        // Silently fail, notification count remains 0
                                        error_log("Notification count error: " . $e->getMessage());
                                    }
                                }
                                
                                if ($notification_count > 0): ?>
                                    <span class="badge bg-danger notification-badge"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($notification_count > 0): ?>
                                    <li><h6 class="dropdown-header">You have <?php echo $notification_count; ?> notifications</h6></li>
                                    <?php if ($is_admin): ?>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/pending_tasks.php?status=refund_requested">
                                            <i class="bi bi-cash-coin text-success"></i> <?php echo $notification_count; ?> refund requests pending
                                        </a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/tasks.php?filter=active">
                                            <i class="bi bi-exclamation-triangle text-warning"></i> <?php echo $notification_count; ?> tasks near deadline
                                        </a></li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li><h6 class="dropdown-header">No new notifications</h6></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Notification Settings</a></li>
                            </ul>
                        </li>
                        
                        <!-- User Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
                                    <small class="d-block">
                                        <?php if ($is_admin): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">User</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Signed in as</h6></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/profile.php">
                                    <i class="bi bi-person-circle"></i> My Profile
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/user/settings.php">
                                    <i class="bi bi-gear"></i> Settings
                                </a></li>
                                <?php if ($is_admin): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/reports.php">
                                    <i class="bi bi-bar-chart"></i> Reports
                                </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Login/Register Links for Public Users -->
                        <li class="nav-item">
                            <a href="#user-login" class="btn btn-outline-light me-2" onclick="showUserLogin()">
                                <i class="bi bi-box-arrow-in-right"></i> User Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#user-register" class="btn btn-light" onclick="showUserRegister()">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- User Login/Register Modal -->
    <div class="modal fade" id="userAuthModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">User Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loginForm" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> User login system is under development. For now, contact admin to create your account.
                        </div>
                        <form id="userLoginForm" action="<?php echo BASE_URL; ?>/admin/index.php" method="GET">
                            <p>Please visit the admin panel to manage user accounts.</p>
                            <button type="submit" class="btn btn-primary w-100">Go to Admin Panel</button>
                        </form>
                    </div>
                    <div id="registerForm" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> User registration is currently managed by admin. Please contact administrator to create an account.
                        </div>
                        <div class="text-center">
                            <p>Contact Admin: admin@reviewflow.com</p>
                            <a href="mailto:admin@reviewflow.com" class="btn btn-primary">Email Admin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(function(alert) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        var bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                });
            }, 5000);
        });
        
        function showUserLogin() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('modalTitle').innerText = 'User Login';
            var modal = new bootstrap.Modal(document.getElementById('userAuthModal'));
            modal.show();
        }
        
        function showUserRegister() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            document.getElementById('modalTitle').innerText = 'User Registration';
            var modal = new bootstrap.Modal(document.getElementById('userAuthModal'));
            modal.show();
        }
    </script>
</body>
</html>
