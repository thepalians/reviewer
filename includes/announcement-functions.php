<?php
declare(strict_types=1);

/**
 * Announcement Functions for Phase 4
 * Handles system-wide announcements and user notifications
 */

/**
 * Create a new announcement
 */
function createAnnouncement($pdo, $title, $message, $target_audience, $start_date, $end_date, $admin_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, message, target_audience, start_date, end_date, created_by, is_active)
            VALUES (:title, :message, :target_audience, :start_date, :end_date, :admin_id, 1)
        ");
        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':target_audience' => $target_audience,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':admin_id' => $admin_id
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to create announcement'];
    }
}

/**
 * Get all announcements with filters
 */
function getAnnouncements($pdo, $filters = []) {
    try {
        $sql = "SELECT a.*, u.name as created_by_name 
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['target_audience'])) {
            $sql .= " AND a.target_audience = :target_audience";
            $params[':target_audience'] = $filters['target_audience'];
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND a.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get active announcements for a user
 */
function getActiveAnnouncementsForUser($pdo, $user_type) {
    try {
        $sql = "SELECT * FROM announcements 
                WHERE is_active = 1 
                AND (target_audience = 'all' OR target_audience = :user_type)
                AND (start_date IS NULL OR start_date <= CURDATE())
                AND (end_date IS NULL OR end_date >= CURDATE())
                ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_type' => $user_type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Mark announcement as viewed by user
 */
function markAnnouncementViewed($pdo, $announcement_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_views (announcement_id, user_id)
            VALUES (:announcement_id, :user_id)
        ");
        $stmt->execute([
            ':announcement_id' => $announcement_id,
            ':user_id' => $user_id
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Update announcement
 */
function updateAnnouncement($pdo, $id, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET title = :title, 
                message = :message, 
                target_audience = :target_audience,
                start_date = :start_date,
                end_date = :end_date,
                is_active = :is_active
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':target_audience' => $data['target_audience'],
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':is_active' => $data['is_active']
        ]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to update announcement'];
    }
}

/**
 * Delete announcement
 */
function deleteAnnouncement($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to delete announcement'];
    }
}

/**
 * Get announcement statistics
 */
function getAnnouncementStats($pdo, $announcement_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT av.user_id) as views,
                a.target_audience
            FROM announcements a
            LEFT JOIN announcement_views av ON a.id = av.announcement_id
            WHERE a.id = :id
            GROUP BY a.id
        ");
        $stmt->execute([':id' => $announcement_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['views' => 0];
    } catch (PDOException $e) {
        return ['views' => 0];
    }
}
