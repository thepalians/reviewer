<?php
/**
 * Chat & Support System Helper Functions
 * Phase 2: In-App Chat System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// Chat attachments directory
define('CHAT_UPLOAD_DIR', __DIR__ . '/../uploads/chat/');
if (!is_dir(CHAT_UPLOAD_DIR)) {
    mkdir(CHAT_UPLOAD_DIR, 0755, true);
}

/**
 * Create or get conversation for user
 */
function getOrCreateConversation($db, $user_id, $subject = 'General Support') {
    // Check for existing open conversation
    $stmt = $db->prepare("
        SELECT id FROM chat_conversations 
        WHERE user_id = ? AND status = 'open'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $conversation_id = $stmt->fetchColumn();
    
    if ($conversation_id) {
        return $conversation_id;
    }
    
    // Create new conversation
    $insert = $db->prepare("
        INSERT INTO chat_conversations (user_id, subject, status)
        VALUES (?, ?, 'open')
    ");
    $insert->execute([$user_id, $subject]);
    return $db->lastInsertId();
}

/**
 * Send chat message
 */
function sendChatMessage($db, $conversation_id, $sender_id, $sender_type, $message, $attachment = null) {
    try {
        $attachment_path = null;
        
        // Handle file attachment
        if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadChatAttachment($attachment, $sender_id);
            if ($upload_result['success']) {
                $attachment_path = $upload_result['file_path'];
            }
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message, attachment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$conversation_id, $sender_id, $sender_type, $message, $attachment_path]);
        $message_id = $db->lastInsertId();
        
        // Update conversation last message time
        $update = $db->prepare("
            UPDATE chat_conversations 
            SET last_message_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$conversation_id]);
        
        // Get receiver
        $receiver_id = getConversationReceiver($db, $conversation_id, $sender_type);
        if ($receiver_id) {
            // Send notification
            createNotification($db, $receiver_id, 'new_chat_message', 
                'You have a new message in chat');
        }
        
        return [
            'success' => true, 
            'message_id' => $message_id,
            'attachment' => $attachment_path
        ];
    } catch (Exception $e) {
        error_log("Send chat message error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error sending message'];
    }
}

/**
 * Get conversation receiver
 */
function getConversationReceiver($db, $conversation_id, $sender_type) {
    $stmt = $db->prepare("SELECT user_id, admin_id FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv) {
        return null;
    }
    
    if ($sender_type === 'user') {
        // If admin assigned, notify admin; otherwise notify all admins
        return $conv['admin_id'] ?: null;
    } else {
        // Notify user
        return $conv['user_id'];
    }
}

/**
 * Upload chat attachment
 */
function uploadChatAttachment($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'chat_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $target_path = CHAT_UPLOAD_DIR . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'file_path' => 'uploads/chat/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

/**
 * Get chat messages for conversation
 */
function getChatMessages($db, $conversation_id, $limit = 50, $offset = 0) {
    $stmt = $db->prepare("
        SELECT 
            cm.*,
            u.username as sender_name
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.conversation_id = ?
        ORDER BY cm.created_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$conversation_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get unread message count
 */
function getUnreadMessageCount($db, $user_id, $user_type = 'user') {
    if ($user_type === 'admin') {
        // Count unread messages in all conversations for admin
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN chat_conversations cc ON cm.conversation_id = cc.id
            WHERE cm.sender_type = 'user' AND cm.is_read = 0
        ");
        $stmt->execute();
    } else {
        // Count unread messages for user
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN chat_conversations cc ON cm.conversation_id = cc.id
            WHERE cc.user_id = ? AND cm.sender_type = 'admin' AND cm.is_read = 0
        ");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchColumn();
}

/**
 * Mark messages as read
 */
function markMessagesAsRead($db, $conversation_id, $reader_type) {
    // Mark messages from opposite type as read
    $sender_type = $reader_type === 'user' ? 'admin' : 'user';
    
    $stmt = $db->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE conversation_id = ? AND sender_type = ? AND is_read = 0
    ");
    return $stmt->execute([$conversation_id, $sender_type]);
}

/**
 * Get user conversations (for user)
 */
function getUserConversations($db, $user_id) {
    $stmt = $db->prepare("
        SELECT 
            cc.*,
            u.username as admin_name,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE conversation_id = cc.id AND sender_type = 'admin' AND is_read = 0) as unread_count,
            (SELECT message FROM chat_messages 
             WHERE conversation_id = cc.id 
             ORDER BY created_at DESC LIMIT 1) as last_message
        FROM chat_conversations cc
        LEFT JOIN users u ON cc.admin_id = u.id
        WHERE cc.user_id = ?
        ORDER BY cc.last_message_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all conversations (for admin)
 */
function getAllConversations($db, $status = 'open', $limit = 100, $offset = 0) {
    $where = $status ? "WHERE cc.status = ?" : "";
    
    $stmt = $db->prepare("
        SELECT 
            cc.*,
            u.username as user_name,
            u.email as user_email,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE conversation_id = cc.id AND sender_type = 'user' AND is_read = 0) as unread_count,
            (SELECT message FROM chat_messages 
             WHERE conversation_id = cc.id 
             ORDER BY created_at DESC LIMIT 1) as last_message
        FROM chat_conversations cc
        JOIN users u ON cc.user_id = u.id
        $where
        ORDER BY cc.last_message_at DESC
        LIMIT ? OFFSET ?
    ");
    
    if ($status) {
        $stmt->execute([$status, $limit, $offset]);
    } else {
        $stmt->execute([$limit, $offset]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Assign conversation to admin
 */
function assignConversationToAdmin($db, $conversation_id, $admin_id) {
    $stmt = $db->prepare("
        UPDATE chat_conversations 
        SET admin_id = ?
        WHERE id = ?
    ");
    return $stmt->execute([$admin_id, $conversation_id]);
}

/**
 * Close conversation
 */
function closeConversation($db, $conversation_id) {
    $stmt = $db->prepare("
        UPDATE chat_conversations 
        SET status = 'closed'
        WHERE id = ?
    ");
    return $stmt->execute([$conversation_id]);
}

/**
 * Reopen conversation
 */
function reopenConversation($db, $conversation_id) {
    $stmt = $db->prepare("
        UPDATE chat_conversations 
        SET status = 'open'
        WHERE id = ?
    ");
    return $stmt->execute([$conversation_id]);
}

/**
 * Get canned responses
 */
function getCannedResponses($db, $category = null) {
    if ($category) {
        $stmt = $db->prepare("
            SELECT * FROM canned_responses 
            WHERE category = ? AND is_active = 1
            ORDER BY title ASC
        ");
        $stmt->execute([$category]);
    } else {
        $stmt = $db->query("
            SELECT * FROM canned_responses 
            WHERE is_active = 1
            ORDER BY category, title ASC
        ");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get canned response by shortcode
 */
function getCannedResponseByShortcode($db, $shortcode) {
    $stmt = $db->prepare("
        SELECT message FROM canned_responses 
        WHERE shortcode = ? AND is_active = 1
    ");
    $stmt->execute([$shortcode]);
    return $stmt->fetchColumn();
}

/**
 * Update typing status
 */
function updateTypingStatus($db, $conversation_id, $user_id, $is_typing) {
    $stmt = $db->prepare("
        INSERT INTO chat_typing_status (conversation_id, user_id, is_typing)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_typed_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([$conversation_id, $user_id, $is_typing]);
}

/**
 * Get typing status
 */
function getTypingStatus($db, $conversation_id) {
    $stmt = $db->prepare("
        SELECT 
            cts.*,
            u.username
        FROM chat_typing_status cts
        JOIN users u ON cts.user_id = u.id
        WHERE cts.conversation_id = ? 
          AND cts.is_typing = 1
          AND cts.last_typed_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $stmt->execute([$conversation_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get chat statistics
 */
function getChatStatistics($db, $user_id = null, $is_admin = false) {
    if ($is_admin) {
        // Admin stats
        $stats = $db->query("
            SELECT 
                COUNT(*) as total_conversations,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_conversations,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_conversations,
                (SELECT COUNT(*) FROM chat_messages WHERE sender_type = 'user' AND is_read = 0) as unread_messages
            FROM chat_conversations
        ")->fetch(PDO::FETCH_ASSOC);
    } else {
        // User stats
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_conversations,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_conversations,
                (SELECT COUNT(*) FROM chat_messages cm
                 JOIN chat_conversations cc ON cm.conversation_id = cc.id
                 WHERE cc.user_id = ? AND cm.sender_type = 'admin' AND cm.is_read = 0) as unread_messages
            FROM chat_conversations
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $stats;
}

/**
 * Search conversations
 */
function searchConversations($db, $search_term, $is_admin = false) {
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT DISTINCT cc.*,
                   u.username as user_name,
                   u.email as user_email
            FROM chat_conversations cc
            JOIN users u ON cc.user_id = u.id
            LEFT JOIN chat_messages cm ON cc.id = cm.conversation_id
            WHERE u.username LIKE ? 
               OR u.email LIKE ?
               OR cc.subject LIKE ?
               OR cm.message LIKE ?
            ORDER BY cc.last_message_at DESC
            LIMIT 50
        ");
        $search = "%{$search_term}%";
        $stmt->execute([$search, $search, $search, $search]);
    } else {
        $stmt = $db->prepare("
            SELECT DISTINCT cc.*
            FROM chat_conversations cc
            LEFT JOIN chat_messages cm ON cc.id = cm.conversation_id
            WHERE cc.user_id = ? AND (cc.subject LIKE ? OR cm.message LIKE ?)
            ORDER BY cc.last_message_at DESC
            LIMIT 20
        ");
        $search = "%{$search_term}%";
        $stmt->execute([$_SESSION['user_id'], $search, $search]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get conversation details
 */
function getConversationDetails($db, $conversation_id) {
    $stmt = $db->prepare("
        SELECT 
            cc.*,
            u.username as user_name,
            u.email as user_email,
            a.username as admin_name
        FROM chat_conversations cc
        JOIN users u ON cc.user_id = u.id
        LEFT JOIN users a ON cc.admin_id = a.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$conversation_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
