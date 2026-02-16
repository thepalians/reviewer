<?php
require_once '../includes/config.php';
require_once '../includes/security.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Handle user actions
$action = $_GET['action'] ?? '';
$message = '';
$message_type = '';

// Add new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        $password = $_POST['password'];
        
        // Validate
        if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
            $message = 'All fields are required!';
            $message_type = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters!';
            $message_type = 'error';
        } else {
            try {
                // Check if email exists
                $checkQuery = "SELECT id FROM users WHERE email = :email OR mobile = :mobile";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([':email' => $email, ':mobile' => $mobile]);
                
                if ($checkStmt->rowCount() > 0) {
                    $message = 'User with this email or mobile already exists!';
                    $message_type = 'error';
                } else {
                    // Insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insertQuery = "INSERT INTO users (name, email, mobile, password, user_type, created_at) 
                                   VALUES (:name, :email, :mobile, :password, 'user', NOW())";
                    $insertStmt = $pdo->prepare($insertQuery);
                    $insertStmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':mobile' => $mobile,
                        ':password' => $hashed_password
                    ]);
                    
                    $message = 'User added successfully!';
                    $message_type = 'success';
                }
            } catch(PDOException $e) {
                error_log('User creation error: ' . $e->getMessage());
                $message = 'A database error occurred. Please try again or contact support.';
                $message_type = 'error';
            }
        }
    }
}

// Delete user
if ($action == 'delete' && isset($_GET['id'])) {
    // Verify CSRF token for delete action
    if (!verifyCSRFToken($_GET['csrf_token'] ?? null)) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        $user_id = intval($_GET['id']);
        
        try {
            // Check if user has tasks
            $checkTasks = "SELECT COUNT(*) FROM tasks WHERE user_id = :user_id";
            $checkStmt = $pdo->prepare($checkTasks);
            $checkStmt->execute([':user_id' => $user_id]);
            $task_count = $checkStmt->fetchColumn();
            
            if ($task_count > 0) {
                $message = 'Cannot delete user with assigned tasks!';
                $message_type = 'error';
            } else {
                $deleteQuery = "DELETE FROM users WHERE id = :id AND user_type = 'user'";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->execute([':id' => $user_id]);
                
                if ($deleteStmt->rowCount() > 0) {
                    $message = 'User deleted successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'User not found or cannot be deleted!';
                    $message_type = 'error';
                }
            }
        } catch(PDOException $e) {
            error_log('User deletion error: ' . $e->getMessage());
            $message = 'A database error occurred. Please try again or contact support.';
            $message_type = 'error';
        }
    }
}

// Get all users
try {
    $usersQuery = "SELECT * FROM users WHERE user_type = 'user' ORDER BY created_at DESC";
    $usersStmt = $pdo->prepare($usersQuery);
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $users = [];
}

// Get user statistics
$user_stats = [];
foreach ($users as $user) {
    try {
        $statsQuery = "SELECT 
            COUNT(DISTINCT t.id) as task_count,
            COUNT(DISTINCT o.id) as order_count,
            SUM(CASE WHEN o.step4_status = 'approved' THEN 1 ELSE 0 END) as completed_orders
            FROM tasks t
            LEFT JOIN orders o ON t.id = o.task_id
            WHERE t.user_id = :user_id";
        
        $statsStmt = $pdo->prepare($statsQuery);
        $statsStmt->execute([':user_id' => $user['id']]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        $user_stats[$user['id']] = $stats;
    } catch(PDOException $e) {
        $user_stats[$user['id']] = ['task_count' => 0, 'order_count' => 0, 'completed_orders' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviewers - ReviewFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 20px 0;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover {
            background: #f0f4ff;
            color: #4361ee;
        }
        
        .sidebar-menu a.active {
            background: #4361ee;
            color: white;
        }
        
        .main-content {
            flex: 1;
            padding: 25px;
            background: #f5f5f5;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn {
            background: #4361ee;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background: #3a0ca3;
            transform: translateY(-2px);
        }
        
        .action-btn.green { background: #2ecc71; }
        .action-btn.green:hover { background: #27ae60; }
        
        .action-btn.red { background: #e74c3c; }
        .action-btn.red:hover { background: #c0392b; }
        
        .action-btn.orange { background: #f39c12; }
        .action-btn.orange:hover { background: #d68910; }
        
        .table-container {
            overflow-x: auto;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            white-space: nowrap;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4361ee;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-info-small {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: #f0f4ff;
            color: #4361ee;
            margin-right: 5px;
        }
        
        .stats-badge.green {
            background: #d4edda;
            color: #155724;
        }
        
        .stats-badge.orange {
            background: #fff3cd;
            color: #856404;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .sidebar {
                position: fixed;
                left: -250px;
                top: 70px;
                height: calc(100vh - 70px);
                transition: left 0.3s;
                z-index: 1000;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="light-mode">
    <!-- Header -->
    <div class="header">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        <h1>
            <i class="fas fa-users"></i>
            Manage Reviewers
        </h1>
        <div class="user-info">
            <span>
                <i class="fas fa-user-circle"></i>
                Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Add User Form -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> Add New Reviewer</h2>
                </div>
                <form method="POST" action="">
                    <?php echo Security::csrfField(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required 
                                   placeholder="Enter full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   placeholder="Enter email address">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                            <input type="text" id="mobile" name="mobile" class="form-control" required 
                                   placeholder="Enter 10-digit mobile number"
                                   pattern="[0-9]{10}" maxlength="10">
                        </div>
                        
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" id="password" name="password" class="form-control" required 
                                   placeholder="Minimum 6 characters" minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" name="add_user" class="action-btn green">
                        <i class="fas fa-save"></i> Add Reviewer
                    </button>
                </form>
            </div>
            
            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> All Reviewers (<?php echo count($users); ?>)</h2>
                </div>
                
                <?php if (!empty($users)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Registration</th>
                                <th>Statistics</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info-small">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                                            <small style="color: #666;">ID: #<?php echo $user['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                                        <strong>Mobile:</strong> <?php echo htmlspecialchars($user['mobile']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?><br>
                                    <small style="color: #666;"><?php echo date('h:i A', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $stats = $user_stats[$user['id']] ?? ['task_count' => 0, 'order_count' => 0, 'completed_orders' => 0];
                                    ?>
                                    <span class="stats-badge">
                                        <i class="fas fa-tasks"></i> <?php echo $stats['task_count']; ?> Tasks
                                    </span>
                                    <span class="stats-badge orange">
                                        <i class="fas fa-shopping-cart"></i> <?php echo $stats['order_count']; ?> Orders
                                    </span>
                                    <span class="stats-badge green">
                                        <i class="fas fa-check-circle"></i> <?php echo $stats['completed_orders']; ?> Completed
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <a href="assign_task.php?user_id=<?php echo $user['id']; ?>" 
                                           class="action-btn" title="Assign Task">
                                            <i class="fas fa-tasks"></i> Assign
                                        </a>
                                        <a href="#" onclick="resetPassword(<?php echo $user['id']; ?>)" 
                                           class="action-btn orange" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" 
                                           class="action-btn red" title="Delete User"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; color: #ddd;"></i>
                    <p>No reviewers found. Add your first reviewer using the form above.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        // Reset password function
        function resetPassword(userId) {
            const newPassword = prompt('Enter new password for this user (minimum 6 characters):');
            if (newPassword && newPassword.length >= 6) {
                if (confirm('Are you sure you want to reset the password?')) {
                    // AJAX request to reset password
                    const formData = new FormData();
                    formData.append('user_id', userId);
                    formData.append('new_password', newPassword);
                    formData.append('action', 'reset_password');
                    
                    fetch('users.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        alert('Password reset successfully!');
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error resetting password: ' . error);
                    });
                }
            } else if (newPassword !== null) {
                alert('Password must be at least 6 characters!');
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('open') && 
                !sidebar.contains(e.target) && 
                e.target !== mobileMenuBtn) {
                sidebar.classList.remove('open');
            }
        });
    </script>
</body>
</html>
