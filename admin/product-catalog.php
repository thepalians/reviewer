<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Get products
$products = [];
$totalProducts = 0;

try {
    $query = "SELECT p.*, s.business_name, 
                     (SELECT COUNT(*) FROM review_requests WHERE product_id = p.id) as review_count
              FROM products p 
              JOIN sellers s ON p.seller_id = s.id 
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (p.product_name LIKE :search OR p.product_url LIKE :search OR s.business_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if ($category !== 'all') {
        $query .= " AND p.category = :category";
        $params[':category'] = $category;
    }
    
    $countQuery = str_replace('p.*, s.business_name, (SELECT COUNT(*) FROM review_requests WHERE product_id = p.id) as review_count', 'COUNT(*)', $query);
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalProducts = (int)$stmt->fetchColumn();
    
    $query .= " ORDER BY p.created_at DESC LIMIT :offset, :limit";
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':limit'] = $perPage;
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Product catalog error: " . $e->getMessage());
}

$current_page = 'product-catalog';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}
        .product-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);overflow:hidden;transition:transform 0.3s}
        .product-card:hover{transform:translateY(-5px);box-shadow:0 4px 20px rgba(0,0,0,0.1)}
        .product-img{width:100%;height:200px;object-fit:cover;background:#f0f0f0}
        .product-info{padding:15px}
        .product-name{font-size:15px;font-weight:600;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .product-seller{font-size:12px;color:#888;margin-bottom:10px}
        .product-stats{display:flex;justify-content:space-between;font-size:12px;color:#666}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.product-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <h3 class="mb-4"><i class="bi bi-box-seam"></i> Product Catalog (<?= number_format($totalProducts) ?>)</h3>

            <div class="card">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" 
                               value="<?= escape($search) ?>" placeholder="Search products...">
                    </div>
                    <div class="col-md-4">
                        <select name="category" class="form-select">
                            <option value="all">All Categories</option>
                            <option value="electronics">Electronics</option>
                            <option value="fashion">Fashion</option>
                            <option value="home">Home & Kitchen</option>
                            <option value="beauty">Beauty</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <img src="<?= escape($product['image_url'] ?? 'assets/images/placeholder.png') ?>" 
                             alt="Product" class="product-img">
                        <div class="product-info">
                            <div class="product-name"><?= escape($product['product_name']) ?></div>
                            <div class="product-seller">by <?= escape($product['business_name']) ?></div>
                            <div class="product-stats">
                                <span><i class="bi bi-star"></i> <?= $product['review_count'] ?> reviews</span>
                                <span>₹<?= number_format($product['price'] ?? 0, 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
