<?php
require_once __DIR__ . '/config.php';

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatPrice(float $amount): string {
    return '₹' . number_format($amount, 0, '.', ',');
}

function getProducts(string $status = 'active', int $limit = 100, int $offset = 0): array {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT * FROM store_products WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->execute([$status, $limit, $offset]);
    return $stmt->fetchAll();
}

function getProductBySlug(string $slug): ?array {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT * FROM store_products WHERE slug = ? AND status = "active" LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getProductById(int $id): ?array {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT * FROM store_products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function searchProducts(string $query): array {
    $db = getStoreDB();
    $q = '%' . $query . '%';
    $stmt = $db->prepare('SELECT * FROM store_products WHERE status = "active" AND (name LIKE ? OR tagline LIKE ? OR short_description LIKE ? OR tags LIKE ?) ORDER BY total_sales DESC');
    $stmt->execute([$q, $q, $q, $q]);
    return $stmt->fetchAll();
}

function generateOrderId(): string {
    return 'PAL-' . strtoupper(bin2hex(random_bytes(5))) . '-' . time();
}

function generateDownloadToken(): string {
    return bin2hex(random_bytes(32));
}

function createOrder(array $data): string {
    $db = getStoreDB();
    $orderId = generateOrderId();
    $token = generateDownloadToken();
    $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
    $stmt = $db->prepare('INSERT INTO store_orders (order_id, product_id, license_type, buyer_name, buyer_email, buyer_phone, amount, currency, razorpay_order_id, payment_status, download_token, token_expires_at, ip_address) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $orderId,
        $data['product_id'],
        $data['license_type'],
        $data['buyer_name'],
        $data['buyer_email'],
        $data['buyer_phone'] ?? '',
        $data['amount'],
        'INR',
        $data['razorpay_order_id'] ?? '',
        'pending',
        $token,
        $expires,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    return $orderId;
}

function getOrderByToken(string $token): ?array {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT o.*, p.name as product_name, p.download_file, p.version FROM store_orders o JOIN store_products p ON o.product_id = p.id WHERE o.download_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getOrderById(string $orderId): ?array {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id = p.id WHERE o.order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function slugify(string $text): string {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function starsHtml(float $rating): string {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }
    return $stars;
}

function sendOrderConfirmationEmail(array $order, array $product): void {
    $to = $order['buyer_email'];
    $subject = 'Your Order Confirmation — ' . $product['name'];
    $downloadUrl = STORE_URL . '/download.php?token=' . urlencode($order['download_token']);
    $message = "Hi " . $order['buyer_name'] . ",\n\n";
    $message .= "Thank you for your purchase!\n\n";
    $message .= "Order ID: " . $order['order_id'] . "\n";
    $message .= "Product: " . $product['name'] . "\n";
    $message .= "License: " . ucfirst($order['license_type']) . "\n";
    $message .= "Amount: " . formatPrice((float)$order['amount']) . "\n\n";
    $message .= "Download Link (valid 48 hours, max 5 downloads):\n" . $downloadUrl . "\n\n";
    $message .= "If you need help, email support@palians.com\n\n";
    $message .= "— Team Palians\n";
    $headers = 'From: Palians <support@palians.com>' . "\r\n";
    @mail($to, $subject, $message, $headers);
}

function handleFileUpload(array $file, string $dest, array $allowedTypes, int $maxSize = 10485760): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxSize) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes, true)) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('', true) . '.' . strtolower($ext);
    $destPath = STORE_ROOT . '/uploads/' . $dest . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) return null;
    return $filename;
}
