<?php
/**
 * Email Marketing Functions
 * Helper functions for email campaigns and marketing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get all email campaigns
 */
function getAllCampaigns($pdo, $limit = 50, $offset = 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username as created_by_name, 
                   et.name as template_name
            FROM email_campaigns c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN email_templates et ON c.template_id = et.id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting campaigns: " . $e->getMessage());
        return [];
    }
}

/**
 * Get campaign by ID
 */
function getCampaignById($pdo, $campaign_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting campaign: " . $e->getMessage());
        return null;
    }
}

/**
 * Create new email campaign
 */
function createCampaign($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_campaigns 
            (name, subject, template_id, content, segment_type, segment_filters, 
             status, scheduled_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $segment_filters = isset($data['segment_filters']) ? json_encode($data['segment_filters']) : null;
        
        $stmt->execute([
            $data['name'],
            $data['subject'],
            $data['template_id'] ?? null,
            $data['content'],
            $data['segment_type'] ?? 'all',
            $segment_filters,
            $data['status'] ?? 'draft',
            $data['scheduled_at'] ?? null,
            $data['created_by']
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating campaign: " . $e->getMessage());
        return false;
    }
}

/**
 * Update campaign
 */
function updateCampaign($pdo, $campaign_id, $data) {
    try {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'segment_filters' && is_array($value)) {
                $value = json_encode($value);
            }
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $campaign_id;
        $sql = "UPDATE email_campaigns SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Error updating campaign: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recipients based on segment
 */
function getCampaignRecipients($pdo, $segment_type, $segment_filters = null) {
    try {
        $query = "SELECT id, email, username FROM users WHERE user_type = 'user' AND email IS NOT NULL";
        
        switch ($segment_type) {
            case 'active':
                $query .= " AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'inactive':
                $query .= " AND last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'new':
                $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'custom':
                if ($segment_filters) {
                    $filters = json_decode($segment_filters, true);
                    if (isset($filters['min_tasks'])) {
                        $query .= " AND (SELECT COUNT(*) FROM tasks WHERE user_id = users.id) >= " . (int)$filters['min_tasks'];
                    }
                    if (isset($filters['min_balance'])) {
                        $query .= " AND (SELECT balance FROM user_wallet WHERE user_id = users.id) >= " . (float)$filters['min_balance'];
                    }
                }
                break;
        }
        
        // Exclude unsubscribed emails
        $query .= " AND email NOT IN (SELECT email FROM email_unsubscribes)";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recipients: " . $e->getMessage());
        return [];
    }
}

/**
 * Send campaign email
 */
function sendCampaignEmail($pdo, $campaign_id, $recipient) {
    try {
        $campaign = getCampaignById($pdo, $campaign_id);
        if (!$campaign) return false;
        
        // Log email send
        $stmt = $pdo->prepare("
            INSERT INTO email_campaign_logs (campaign_id, user_id, email, status, sent_at)
            VALUES (?, ?, ?, 'sent', NOW())
        ");
        $stmt->execute([$campaign_id, $recipient['id'], $recipient['email']]);
        
        // Here you would integrate with your email service (SMTP, SendGrid, etc.)
        // For now, we just log it
        
        return true;
    } catch (PDOException $e) {
        error_log("Error sending campaign email: " . $e->getMessage());
        return false;
    }
}

/**
 * Track email open
 */
function trackEmailOpen($pdo, $campaign_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE email_campaign_logs 
            SET status = 'opened', opened_at = NOW()
            WHERE campaign_id = ? AND user_id = ? AND status = 'sent'
        ");
        $stmt->execute([$campaign_id, $user_id]);
        
        // Update campaign stats
        $stmt = $pdo->prepare("UPDATE email_campaigns SET open_count = open_count + 1 WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error tracking email open: " . $e->getMessage());
        return false;
    }
}

/**
 * Track email click
 */
function trackEmailClick($pdo, $campaign_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE email_campaign_logs 
            SET status = 'clicked', clicked_at = NOW()
            WHERE campaign_id = ? AND user_id = ?
        ");
        $stmt->execute([$campaign_id, $user_id]);
        
        // Update campaign stats
        $stmt = $pdo->prepare("UPDATE email_campaigns SET click_count = click_count + 1 WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error tracking email click: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all email templates
 */
function getAllTemplates($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT t.*, u.username as created_by_name
            FROM email_templates t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.is_active = 1
            ORDER BY t.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting templates: " . $e->getMessage());
        return [];
    }
}

/**
 * Create email template
 */
function createTemplate($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_templates (name, subject, content, type, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['subject'],
            $data['content'],
            $data['type'] ?? 'promotional',
            $data['created_by']
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating template: " . $e->getMessage());
        return false;
    }
}

/**
 * Unsubscribe user from emails
 */
function unsubscribeEmail($pdo, $email, $reason = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_unsubscribes (email, reason)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE reason = VALUES(reason)
        ");
        return $stmt->execute([$email, $reason]);
    } catch (PDOException $e) {
        error_log("Error unsubscribing email: " . $e->getMessage());
        return false;
    }
}

/**
 * Get campaign statistics
 */
function getCampaignStats($pdo, $campaign_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as total_opened,
                SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as total_clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as total_bounced,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as total_unsubscribed
            FROM email_campaign_logs
            WHERE campaign_id = ?
        ");
        $stmt->execute([$campaign_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting campaign stats: " . $e->getMessage());
        return null;
    }
}
