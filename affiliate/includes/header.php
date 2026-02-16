<?php
if (!isset($_SESSION['affiliate_id'])) {
    header('Location: ' . BASE_URL . '/affiliate/index.php');
    exit;
}

$affiliate_id = $_SESSION['affiliate_id'];

// Get affiliate info
$stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ?");
$stmt->execute([$affiliate_id]);
$affiliate = $stmt->fetch();

if (!$affiliate || $affiliate['status'] !== 'active') {
    session_destroy();
    header('Location: ' . BASE_URL . '/affiliate/index.php?error=account_inactive');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Affiliate Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #10b981;
            --sidebar-bg: #064e3b;
            --sidebar-hover: #065f46;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f0fdf4;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: var(--sidebar-hover);
            color: white;
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-left: 3px solid white;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            padding: 1.5rem;
            border-radius: 12px;
            background: white;
            border: 1px solid #e5e7eb;
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
        <div class="sidebar-brand">
            <i class="bi bi-graph-up-arrow"></i> Affiliate Portal
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="links.php" class="nav-link <?= $current_page === 'links' ? 'active' : '' ?>">
                <i class="bi bi-link-45deg"></i>
                <span>Tracking Links</span>
            </a>
            <a href="payouts.php" class="nav-link <?= $current_page === 'payouts' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin"></i>
                <span>Payouts</span>
            </a>
            
            <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 1rem 0;">
            
            <a href="<?= BASE_URL ?>/affiliate/logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
