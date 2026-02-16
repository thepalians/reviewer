<?php
/**
 * WhatsApp Integration Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Send WhatsApp message using template
 */
function sendWhatsAppTemplate($db, $user_id, $template_id, $variables = []) {
    try {
        // Get user phone number
        $user_stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['phone'])) {
            return ['success' => false, 'message' => 'User phone not found'];
        }
        
        // Get template
        $template_stmt = $db->prepare("SELECT * FROM whatsapp_templates WHERE id = ? AND status = 'approved'");
        $template_stmt->execute([$template_id]);
        $template = $template_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found or not approved'];
        }
        
        // Replace variables in content
        $content = $template['content'];
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }
        
        // Log message
        $log_stmt = $db->prepare("
            INSERT INTO whatsapp_messages 
            (user_id, phone_number, template_id, message_type, content, status)
            VALUES (?, ?, ?, 'template', ?, 'pending')
        ");
        $log_stmt->execute([$user_id, $user['phone'], $template_id, $content]);
        $message_id = $db->lastInsertId();
        
        // Send via WhatsApp API (mock implementation)
        $result = sendWhatsAppAPI($user['phone'], $content, $template['template_id']);
        
        if ($result['success']) {
            $update_stmt = $db->prepare("
                UPDATE whatsapp_messages 
                SET status = 'sent', external_id = ?, sent_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$result['message_id'], $message_id]);
            
            return ['success' => true, 'message_id' => $message_id];
        } else {
            $update_stmt = $db->prepare("
                UPDATE whatsapp_messages 
                SET status = 'failed', error_message = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$result['error'], $message_id]);
            
            return ['success' => false, 'message' => $result['error']];
        }
        
    } catch (Exception $e) {
        error_log("Error sending WhatsApp template: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send WhatsApp text message
 */
function sendWhatsAppText($db, $user_id, $message) {
    try {
        $user_stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['phone'])) {
            return ['success' => false, 'message' => 'User phone not found'];
        }
        
        $log_stmt = $db->prepare("
            INSERT INTO whatsapp_messages 
            (user_id, phone_number, message_type, content, status)
            VALUES (?, ?, 'text', ?, 'pending')
        ");
        $log_stmt->execute([$user_id, $user['phone'], $message]);
        $message_id = $db->lastInsertId();
        
        $result = sendWhatsAppAPI($user['phone'], $message);
        
        if ($result['success']) {
            $update_stmt = $db->prepare("
                UPDATE whatsapp_messages 
                SET status = 'sent', external_id = ?, sent_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$result['message_id'], $message_id]);
            
            return ['success' => true, 'message_id' => $message_id];
        } else {
            $update_stmt = $db->prepare("
                UPDATE whatsapp_messages 
                SET status = 'failed', error_message = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$result['error'], $message_id]);
            
            return ['success' => false, 'message' => $result['error']];
        }
        
    } catch (Exception $e) {
        error_log("Error sending WhatsApp text: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send WhatsApp notification (wrapper for common notifications)
 */
function sendWhatsAppNotification($db, $user_id, $notification_type, $data = []) {
    $templates = [
        'task_assigned' => [
            'template_name' => 'task_assignment',
            'variables' => ['task_title' => $data['title'] ?? '', 'commission' => $data['commission'] ?? '']
        ],
        'payment_confirmed' => [
            'template_name' => 'payment_confirmation',
            'variables' => ['amount' => $data['amount'] ?? '', 'transaction_id' => $data['transaction_id'] ?? '']
        ],
        'deadline_reminder' => [
            'template_name' => 'deadline_reminder',
            'variables' => ['task_title' => $data['title'] ?? '', 'deadline' => $data['deadline'] ?? '']
        ]
    ];
    
    if (!isset($templates[$notification_type])) {
        return ['success' => false, 'message' => 'Unknown notification type'];
    }
    
    $template_config = $templates[$notification_type];
    
    // Get template by name
    try {
        $stmt = $db->prepare("SELECT id FROM whatsapp_templates WHERE name = ? AND status = 'approved' LIMIT 1");
        $stmt->execute([$template_config['template_name']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            return sendWhatsAppTemplate($db, $user_id, $template['id'], $template_config['variables']);
        } else {
            // Fallback to text message
            $message = formatNotificationMessage($notification_type, $data);
            return sendWhatsAppText($db, $user_id, $message);
        }
    } catch (Exception $e) {
        error_log("Error sending WhatsApp notification: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Format notification message
 */
function formatNotificationMessage($type, $data) {
    switch ($type) {
        case 'task_assigned':
            return "New task assigned: {$data['title']}. Commission: ₹{$data['commission']}";
        case 'payment_confirmed':
            return "Payment of ₹{$data['amount']} confirmed. Transaction ID: {$data['transaction_id']}";
        case 'deadline_reminder':
            return "Reminder: Task '{$data['title']}' deadline is {$data['deadline']}";
        default:
            return "New notification";
    }
}

/**
 * Mock WhatsApp API call (replace with actual implementation)
 */
function sendWhatsAppAPI($phone, $message, $template_id = null) {
    // In production, integrate with WhatsApp Business API
    // Example: Twilio, 360Dialog, or official WhatsApp Business API
    
    // Get WhatsApp settings
    global $pdo;
    $settings = getWhatsAppSettings($pdo);
    
    if (!$settings['enabled']) {
        return ['success' => false, 'error' => 'WhatsApp integration not enabled'];
    }
    
    // Mock successful response
    return [
        'success' => true,
        'message_id' => 'wamid.' . uniqid()
    ];
    
    /* Example real implementation with cURL:
    $api_url = $settings['api_url'];
    $api_key = $settings['api_key'];
    
    $data = [
        'phone' => $phone,
        'message' => $message,
        'template_id' => $template_id
    ];
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        return ['success' => true, 'message_id' => $result['id']];
    } else {
        return ['success' => false, 'error' => $response];
    }
    */
}

/**
 * Get WhatsApp settings
 */
function getWhatsAppSettings($db) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM whatsapp_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Provide defaults
        $defaults = [
            'enabled' => false,
            'api_url' => '',
            'api_key' => '',
            'phone_number_id' => ''
        ];
        
        return array_merge($defaults, $settings);
    } catch (Exception $e) {
        error_log("Error getting WhatsApp settings: " . $e->getMessage());
        return ['enabled' => false];
    }
}

/**
 * Update WhatsApp settings
 */
function updateWhatsAppSettings($db, $settings) {
    try {
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO whatsapp_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Error updating WhatsApp settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Get message delivery status
 */
function getMessageStatus($db, $message_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM whatsapp_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting message status: " . $e->getMessage());
        return null;
    }
}

/**
 * Update message delivery status (called by webhook)
 */
function updateMessageStatus($db, $external_id, $status, $timestamp = null) {
    try {
        $updates = ['status' => $status];
        
        if ($status == 'delivered') {
            $updates['delivered_at'] = $timestamp ?? date('Y-m-d H:i:s');
        } elseif ($status == 'read') {
            $updates['read_at'] = $timestamp ?? date('Y-m-d H:i:s');
        }
        
        $set_clause = implode(', ', array_map(function($k) {
            return "$k = ?";
        }, array_keys($updates)));
        
        $stmt = $db->prepare("
            UPDATE whatsapp_messages 
            SET $set_clause
            WHERE external_id = ?
        ");
        
        $params = array_merge(array_values($updates), [$external_id]);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error updating message status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get WhatsApp message statistics
 */
function getWhatsAppStatistics($db, $start_date = null, $end_date = null) {
    try {
        $where = "1=1";
        $params = [];
        
        if ($start_date && $end_date) {
            $where .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        
        $stmt = $db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                message_type
            FROM whatsapp_messages
            WHERE $where
            GROUP BY status, message_type
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting WhatsApp statistics: " . $e->getMessage());
        return [];
    }
}

/**
 * Create WhatsApp template
 */
function createWhatsAppTemplate($db, $template_data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_templates 
            (name, template_id, category, language, content, variables, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        return $stmt->execute([
            $template_data['name'],
            $template_data['template_id'] ?? null,
            $template_data['category'] ?? 'marketing',
            $template_data['language'] ?? 'en',
            $template_data['content'],
            json_encode($template_data['variables'] ?? [])
        ]);
    } catch (Exception $e) {
        error_log("Error creating WhatsApp template: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all WhatsApp templates
 */
function getWhatsAppTemplates($db, $status = null) {
    try {
        if ($status) {
            $stmt = $db->prepare("SELECT * FROM whatsapp_templates WHERE status = ? ORDER BY created_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query("SELECT * FROM whatsapp_templates ORDER BY created_at DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting WhatsApp templates: " . $e->getMessage());
        return [];
    }
}
