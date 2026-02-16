<?php
/**
 * Support Ticket System Functions
 * Helper functions for ticket management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Generate unique ticket number
 */
function generateTicketNumber($pdo) {
    do {
        $ticket_number = 'TKT-' . strtoupper(substr(uniqid(), -8));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE ticket_number = ?");
        $stmt->execute([$ticket_number]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    
    return $ticket_number;
}

/**
 * Create new support ticket
 */
function createTicket($pdo, $data) {
    try {
        $ticket_number = generateTicketNumber($pdo);
        
        // Calculate SLA deadline based on priority
        $sla_hours = [
            'low' => 72,
            'medium' => 48,
            'high' => 24,
            'urgent' => 4
        ];
        $hours = $sla_hours[$data['priority']] ?? 48;
        $sla_deadline = date('Y-m-d H:i:s', strtotime("+$hours hours"));
        
        $stmt = $pdo->prepare("
            INSERT INTO support_tickets 
            (ticket_number, user_id, category, priority, subject, description, sla_deadline)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticket_number,
            $data['user_id'],
            $data['category'],
            $data['priority'],
            $data['subject'],
            $data['description'],
            $sla_deadline
        ]);
        
        $ticket_id = $pdo->lastInsertId();
        
        // Auto-assign to available agent if enabled
        autoAssignTicket($pdo, $ticket_id);
        
        return $ticket_id;
    } catch (PDOException $e) {
        error_log("Error creating ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Get ticket by ID
 */
function getTicketById($pdo, $ticket_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.username, u.email,
                   a.username as assigned_to_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticket_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting ticket: " . $e->getMessage());
        return null;
    }
}

/**
 * Get ticket by ticket number
 */
function getTicketByNumber($pdo, $ticket_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.username, u.email,
                   a.username as assigned_to_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.ticket_number = ?
        ");
        $stmt->execute([$ticket_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting ticket: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user tickets
 */
function getUserTickets($pdo, $user_id, $status = null) {
    try {
        $query = "
            SELECT t.*, 
                   a.username as assigned_to_name
            FROM support_tickets t
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.user_id = ?
        ";
        
        $params = [$user_id];
        
        if ($status) {
            $query .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all tickets for admin
 */
function getAllTickets($pdo, $filters = []) {
    try {
        $query = "
            SELECT t.*, 
                   u.username, u.email,
                   a.username as assigned_to_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $query .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['priority'])) {
            $query .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (isset($filters['category'])) {
            $query .= " AND t.category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['assigned_to'])) {
            $query .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        
        $query .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Update ticket status
 */
function updateTicketStatus($pdo, $ticket_id, $status) {
    try {
        $update_fields = ['status' => $status];
        
        if ($status === 'resolved') {
            $update_fields['resolved_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'closed') {
            $update_fields['closed_at'] = date('Y-m-d H:i:s');
        }
        
        $fields = [];
        $values = [];
        foreach ($update_fields as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $ticket_id;
        
        $sql = "UPDATE support_tickets SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Error updating ticket status: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign ticket to agent
 */
function assignTicket($pdo, $ticket_id, $agent_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET assigned_to = ?, status = 'in_progress'
            WHERE id = ?
        ");
        return $stmt->execute([$agent_id, $ticket_id]);
    } catch (PDOException $e) {
        error_log("Error assigning ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-assign ticket to available agent
 */
function autoAssignTicket($pdo, $ticket_id) {
    try {
        // Get agent with least assigned open tickets
        $stmt = $pdo->query("
            SELECT u.id, COUNT(t.id) as ticket_count
            FROM users u
            LEFT JOIN support_tickets t ON u.id = t.assigned_to AND t.status IN ('open', 'in_progress')
            WHERE u.user_type = 'admin' AND u.is_active = 1
            GROUP BY u.id
            ORDER BY ticket_count ASC
            LIMIT 1
        ");
        
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent) {
            return assignTicket($pdo, $ticket_id, $agent['id']);
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error auto-assigning ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Add reply to ticket
 */
function addTicketReply($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ticket_replies 
            (ticket_id, user_id, message, is_internal, attachments)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $attachments = isset($data['attachments']) ? json_encode($data['attachments']) : null;
        
        $stmt->execute([
            $data['ticket_id'],
            $data['user_id'],
            $data['message'],
            $data['is_internal'] ?? 0,
            $attachments
        ]);
        
        // Update ticket's updated_at
        $stmt = $pdo->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$data['ticket_id']]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error adding ticket reply: " . $e->getMessage());
        return false;
    }
}

/**
 * Get ticket replies
 */
function getTicketReplies($pdo, $ticket_id, $include_internal = false) {
    try {
        $query = "
            SELECT r.*, u.username, u.user_type
            FROM ticket_replies r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.ticket_id = ?
        ";
        
        if (!$include_internal) {
            $query .= " AND r.is_internal = 0";
        }
        
        $query .= " ORDER BY r.created_at ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$ticket_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting ticket replies: " . $e->getMessage());
        return [];
    }
}

/**
 * Get canned responses
 */
function getCannedResponses($pdo, $category = null) {
    try {
        $query = "SELECT * FROM ticket_canned_responses WHERE is_active = 1";
        
        $params = [];
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        $query .= " ORDER BY title ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting canned responses: " . $e->getMessage());
        return [];
    }
}

/**
 * Upload ticket attachment
 */
function uploadTicketAttachment($pdo, $file, $ticket_id, $user_id, $reply_id = null) {
    try {
        $upload_dir = __DIR__ . '/../uploads/tickets/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
        
        if (!in_array($file_ext, $allowed_exts)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }
        
        if ($file_size > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File too large (max 5MB)'];
        }
        
        $new_file_name = uniqid() . '_' . $file_name;
        $file_path = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_attachments 
                (ticket_id, reply_id, file_name, file_path, file_size, file_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $ticket_id,
                $reply_id,
                $file_name,
                'uploads/tickets/' . $new_file_name,
                $file_size,
                $file_ext,
                $user_id
            ]);
            
            return [
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'file_name' => $file_name,
                'file_path' => 'uploads/tickets/' . $new_file_name
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to upload file'];
    } catch (Exception $e) {
        error_log("Error uploading attachment: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get ticket statistics
 */
function getTicketStats($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolved_at, NOW()))) as avg_resolution_time
            FROM support_tickets
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting ticket stats: " . $e->getMessage());
        return null;
    }
}
