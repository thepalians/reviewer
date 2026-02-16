<?php
declare(strict_types=1);

/**
 * Deep Link Functions
 * App deep linking support
 */

/**
 * Create deep link
 */
function createDeepLink(string $linkType, string $targetUrl, array $parameters = [], ?string $shortCode = null, ?int $createdBy = null, ?string $expiresAt = null): ?array {
    global $pdo;
    
    try {
        if (!$shortCode) {
            $shortCode = generateShortCode();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO deep_links 
            (link_type, short_code, target_url, parameters, created_by, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $linkType,
            $shortCode,
            $targetUrl,
            json_encode($parameters),
            $createdBy,
            $expiresAt
        ])) {
            return [
                'id' => $pdo->lastInsertId(),
                'short_code' => $shortCode,
                'url' => APP_URL . '/d/' . $shortCode,
                'app_url' => 'reviewflow://open/' . $shortCode
            ];
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating deep link: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate unique short code
 */
function generateShortCode(int $length = 8): string {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

/**
 * Resolve deep link
 */
function resolveDeepLink(string $shortCode): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM deep_links 
            WHERE short_code = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$shortCode]);
        $link = $stmt->fetch();
        
        if (!$link) return null;
        
        // Increment click count
        $stmt = $pdo->prepare("UPDATE deep_links SET click_count = click_count + 1 WHERE id = ?");
        $stmt->execute([$link['id']]);
        
        $link['parameters'] = json_decode($link['parameters'], true);
        return $link;
    } catch (PDOException $e) {
        error_log("Error resolving deep link: " . $e->getMessage());
        return null;
    }
}

/**
 * Create task deep link
 */
function createTaskDeepLink(int $taskId, ?int $createdBy = null): ?array {
    return createDeepLink(
        'task',
        APP_URL . '/user/tasks.php?id=' . $taskId,
        ['task_id' => $taskId],
        null,
        $createdBy
    );
}

/**
 * Create payment deep link
 */
function createPaymentDeepLink(int $orderId, ?int $createdBy = null): ?array {
    return createDeepLink(
        'payment',
        APP_URL . '/seller/payment-page.php?order_id=' . $orderId,
        ['order_id' => $orderId],
        null,
        $createdBy
    );
}

/**
 * Create referral deep link
 */
function createReferralDeepLink(string $referralCode, ?int $createdBy = null): ?array {
    return createDeepLink(
        'referral',
        APP_URL . '/index.php?ref=' . $referralCode,
        ['referral_code' => $referralCode],
        null,
        $createdBy
    );
}

/**
 * Get deep link analytics
 */
function getDeepLinkAnalytics(int $linkId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM deep_links WHERE id = ?");
        $stmt->execute([$linkId]);
        $link = $stmt->fetch();
        
        if (!$link) return null;
        
        return [
            'id' => $link['id'],
            'link_type' => $link['link_type'],
            'short_code' => $link['short_code'],
            'click_count' => $link['click_count'],
            'created_at' => $link['created_at'],
            'expires_at' => $link['expires_at']
        ];
    } catch (PDOException $e) {
        error_log("Error getting deep link analytics: " . $e->getMessage());
        return null;
    }
}
