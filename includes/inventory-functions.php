<?php
declare(strict_types=1);

/**
 * Inventory Management Functions
 */

/**
 * Create or update product
 */
function saveProduct(array $data): ?int {
    global $pdo;
    
    try {
        if (isset($data['id']) && $data['id'] > 0) {
            // Update existing product
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, sku = ?, barcode = ?, 
                    category_id = ?, brand_id = ?, platform = ?, 
                    product_url = ?, image_url = ?, price = ?, 
                    stock_quantity = ?, low_stock_threshold = ?, status = ?
                WHERE id = ? AND seller_id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['sku'] ?? null,
                $data['barcode'] ?? null,
                $data['category_id'] ?? null,
                $data['brand_id'] ?? null,
                $data['platform'] ?? null,
                $data['product_url'] ?? null,
                $data['image_url'] ?? null,
                $data['price'] ?? null,
                $data['stock_quantity'] ?? 0,
                $data['low_stock_threshold'] ?? 10,
                $data['status'] ?? 'active',
                $data['id'],
                $data['seller_id']
            ]);
            
            return $data['id'];
        } else {
            // Create new product
            $stmt = $pdo->prepare("
                INSERT INTO products 
                (seller_id, name, description, sku, barcode, category_id, brand_id, 
                 platform, product_url, image_url, price, stock_quantity, low_stock_threshold, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['seller_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['sku'] ?? null,
                $data['barcode'] ?? null,
                $data['category_id'] ?? null,
                $data['brand_id'] ?? null,
                $data['platform'] ?? null,
                $data['product_url'] ?? null,
                $data['image_url'] ?? null,
                $data['price'] ?? null,
                $data['stock_quantity'] ?? 0,
                $data['low_stock_threshold'] ?? 10,
                $data['status'] ?? 'active'
            ]);
            
            return (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Error saving product: " . $e->getMessage());
        return null;
    }
}

/**
 * Get product by ID
 */
function getProduct(int $productId, ?int $sellerId = null): ?array {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM products WHERE id = ?";
        $params = [$productId];
        
        if ($sellerId) {
            $sql .= " AND seller_id = ?";
            $params[] = $sellerId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting product: " . $e->getMessage());
        return null;
    }
}

/**
 * Get products for a seller
 */
function getSellerProducts(int $sellerId, array $filters = []): array {
    global $pdo;
    
    try {
        $where = ['seller_id = ?'];
        $params = [$sellerId];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = 'category_id = ?';
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting seller products: " . $e->getMessage());
        return [];
    }
}

/**
 * Update product stock
 */
function updateStock(int $productId, int $quantity, string $action, array $details = []): bool {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock_quantity, low_stock_threshold FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $pdo->rollBack();
            return false;
        }
        
        $previousStock = $product['stock_quantity'];
        $newStock = match($action) {
            'add', 'return' => $previousStock + $quantity,
            'remove', 'sale' => $previousStock - $quantity,
            'adjust' => $quantity,
            default => $previousStock
        };
        
        // Ensure stock doesn't go negative
        if ($newStock < 0) {
            $newStock = 0;
        }
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$newStock, $productId]);
        
        // Log inventory change
        $stmt = $pdo->prepare("
            INSERT INTO inventory_logs 
            (product_id, action, quantity, previous_stock, new_stock, reference_type, reference_id, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $productId,
            $action,
            $quantity,
            $previousStock,
            $newStock,
            $details['reference_type'] ?? null,
            $details['reference_id'] ?? null,
            $details['notes'] ?? null,
            $details['created_by'] ?? null
        ]);
        
        // Check for low stock alert
        if ($newStock <= $product['low_stock_threshold']) {
            createStockAlert($productId, $newStock === 0 ? 'out_of_stock' : 'low_stock', $product['low_stock_threshold'], $newStock);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating stock: " . $e->getMessage());
        return false;
    }
}

/**
 * Create stock alert
 */
function createStockAlert(int $productId, string $alertType, int $threshold, int $currentValue): bool {
    global $pdo;
    
    try {
        // Check if alert already exists and is unread
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM stock_alerts 
            WHERE product_id = ? AND alert_type = ? AND is_read = 0
        ");
        $stmt->execute([$productId, $alertType]);
        
        if ($stmt->fetchColumn() > 0) {
            // Alert already exists
            return true;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO stock_alerts 
            (product_id, alert_type, threshold_value, current_value)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$productId, $alertType, $threshold, $currentValue]);
    } catch (PDOException $e) {
        error_log("Error creating stock alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Get stock alerts
 */
function getStockAlerts(int $sellerId, bool $unreadOnly = false): array {
    global $pdo;
    
    try {
        $sql = "
            SELECT sa.*, p.name as product_name, p.sku
            FROM stock_alerts sa
            JOIN products p ON sa.product_id = p.id
            WHERE p.seller_id = ?
        ";
        
        if ($unreadOnly) {
            $sql .= " AND sa.is_read = 0";
        }
        
        $sql .= " ORDER BY sa.created_at DESC LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sellerId]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting stock alerts: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark stock alert as read
 */
function markStockAlertRead(int $alertId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE stock_alerts SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$alertId]);
    } catch (PDOException $e) {
        error_log("Error marking alert as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Link product review to task
 */
function linkProductReview(int $productId, int $taskId, int $userId, array $reviewData = []): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO product_reviews 
            (product_id, task_id, user_id, review_url, rating, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([
            $productId,
            $taskId,
            $userId,
            $reviewData['review_url'] ?? null,
            $reviewData['rating'] ?? null
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error linking product review: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify product review
 */
function verifyProductReview(int $reviewId, string $status): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE product_reviews 
            SET status = ?, verified_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $reviewId]);
    } catch (PDOException $e) {
        error_log("Error verifying product review: " . $e->getMessage());
        return false;
    }
}

/**
 * Get inventory history
 */
function getInventoryHistory(int $productId, int $limit = 50): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT il.*, u.name as created_by_name
            FROM inventory_logs il
            LEFT JOIN users u ON il.created_by = u.id
            WHERE il.product_id = ?
            ORDER BY il.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$productId, $limit]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting inventory history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get low stock products
 */
function getLowStockProducts(int $sellerId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE seller_id = ? 
            AND stock_quantity <= low_stock_threshold
            AND status = 'active'
            ORDER BY stock_quantity ASC
        ");
        $stmt->execute([$sellerId]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting low stock products: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory summary
 */
function getInventorySummary(int $sellerId): array {
    global $pdo;
    
    try {
        $summary = [];
        
        // Total products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $summary['total_products'] = $stmt->fetchColumn();
        
        // Active products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'active'");
        $stmt->execute([$sellerId]);
        $summary['active_products'] = $stmt->fetchColumn();
        
        // Low stock count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM products 
            WHERE seller_id = ? AND stock_quantity <= low_stock_threshold AND stock_quantity > 0
        ");
        $stmt->execute([$sellerId]);
        $summary['low_stock_count'] = $stmt->fetchColumn();
        
        // Out of stock count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND stock_quantity = 0");
        $stmt->execute([$sellerId]);
        $summary['out_of_stock_count'] = $stmt->fetchColumn();
        
        // Total inventory value
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_quantity * price), 0) 
            FROM products 
            WHERE seller_id = ? AND status = 'active'
        ");
        $stmt->execute([$sellerId]);
        $summary['total_inventory_value'] = $stmt->fetchColumn();
        
        return $summary;
    } catch (PDOException $e) {
        error_log("Error getting inventory summary: " . $e->getMessage());
        return [];
    }
}
