<?php
if (!isset($_SESSION['seller_id'])) {
    header('Location: ' . SELLER_URL . '/index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];

// Get seller info
$stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch();

if (!$seller || $seller['status'] !== 'active') {
    session_destroy();
    header('Location: ' . SELLER_URL . '/index.php?error=account_inactive');
    exit;
}

// Ensure seller_name is set in session for chatbot
if (!isset($_SESSION['seller_name'])) {
    $_SESSION['seller_name'] = $seller['name'];
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Seller Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s;
            gap: 0.75rem;
            font-size: 0.95rem;
        }
        
        .sidebar-menu a:hover {
            background: var(--sidebar-hover);
            color: white;
        }
        
        .sidebar-menu a.active {
            background: var(--primary-color);
            color: white;
        }
        
        .sidebar-menu a i {
            font-size: 1.25rem;
            width: 24px;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .navbar-custom {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
            border-radius: 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background: #4338ca;
        }
        
        .card {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .table {
            font-size: 0.95rem;
        }
        
        .badge {
            padding: 0.35rem 0.75rem;
            font-weight: 500;
            border-radius: 6px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="bi bi-star-fill"></i>
                <?= APP_NAME ?>
            </a>
            <div class="text-muted small mt-2">Seller Panel</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="new-request.php" class="<?= $current_page === 'new-request' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                <span>New Request</span>
            </a>
            <a href="orders.php" class="<?= $current_page === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Orders</span>
            </a>
            <a href="analytics.php" class="<?= $current_page === 'analytics' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>Analytics</span>
            </a>
            <a href="wallet.php" class="<?= $current_page === 'wallet' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i>
                <span>Wallet</span>
            </a>
            <a href="invoices.php" class="<?= $current_page === 'invoices' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Invoices</span>
            </a>
            <a href="profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>
            <a href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <nav class="navbar-custom">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                    <h5 class="mb-0">Welcome, <?= htmlspecialchars($seller['name']) ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($seller['email']) ?></small>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <span class="badge bg-success">Active</span>
                    <a href="profile.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-gear"></i>
                    </a>
                </div>
            </div>
        </nav>
