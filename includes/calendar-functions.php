<?php
/**
 * Task Scheduling & Calendar Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Schedule a task for a user
 */
function scheduleTask($db, $task_id, $user_id, $scheduled_date, $scheduled_time = null, $notes = '') {
    try {
        $stmt = $db->prepare("
            INSERT INTO task_schedules (task_id, user_id, scheduled_date, scheduled_time, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                scheduled_date = VALUES(scheduled_date),
                scheduled_time = VALUES(scheduled_time),
                notes = VALUES(notes)
        ");
        
        return $stmt->execute([$task_id, $user_id, $scheduled_date, $scheduled_time, $notes]);
    } catch (Exception $e) {
        error_log("Error scheduling task: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's task calendar
 */
function getUserTaskCalendar($db, $user_id, $start_date, $end_date) {
    try {
        $stmt = $db->prepare("
            SELECT 
                ts.*,
                t.title,
                t.status,
                t.commission_amount,
                t.category_id
            FROM task_schedules ts
            JOIN tasks t ON ts.task_id = t.id
            WHERE ts.user_id = ?
            AND ts.scheduled_date BETWEEN ? AND ?
            ORDER BY ts.scheduled_date, ts.scheduled_time
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting calendar: " . $e->getMessage());
        return [];
    }
}

/**
 * Get tasks scheduled for today with pending reminders
 */
function getTasksNeedingReminders($db) {
    try {
        $stmt = $db->query("
            SELECT 
                ts.*,
                t.title,
                u.name,
                u.email,
                u.phone
            FROM task_schedules ts
            JOIN tasks t ON ts.task_id = t.id
            JOIN users u ON ts.user_id = u.id
            WHERE ts.scheduled_date = CURDATE()
            AND ts.reminder_sent = 0
            AND ts.reminder_time <= NOW()
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting reminder tasks: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark reminder as sent
 */
function markReminderSent($db, $schedule_id) {
    try {
        $stmt = $db->prepare("
            UPDATE task_schedules 
            SET reminder_sent = 1 
            WHERE id = ?
        ");
        return $stmt->execute([$schedule_id]);
    } catch (Exception $e) {
        error_log("Error marking reminder: " . $e->getMessage());
        return false;
    }
}

/**
 * Create recurring task
 */
function createRecurringTask($db, $seller_id, $template_data, $frequency, $start_date, $end_date = null, $day_of_week = null, $day_of_month = null) {
    try {
        // Calculate next run date
        $next_run = calculateNextRunDate($start_date, $frequency, $day_of_week, $day_of_month);
        
        $stmt = $db->prepare("
            INSERT INTO recurring_tasks 
            (seller_id, template_data, frequency, day_of_week, day_of_month, start_date, end_date, next_run)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $template_json = json_encode($template_data);
        
        return $stmt->execute([
            $seller_id, 
            $template_json, 
            $frequency, 
            $day_of_week, 
            $day_of_month, 
            $start_date, 
            $end_date, 
            $next_run
        ]);
    } catch (Exception $e) {
        error_log("Error creating recurring task: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate next run date for recurring task
 */
function calculateNextRunDate($current_date, $frequency, $day_of_week = null, $day_of_month = null) {
    $date = new DateTime($current_date);
    
    switch ($frequency) {
        case 'daily':
            $date->modify('+1 day');
            break;
            
        case 'weekly':
            if ($day_of_week !== null) {
                $date->modify('next ' . getDayName($day_of_week));
            } else {
                $date->modify('+7 days');
            }
            break;
            
        case 'monthly':
            if ($day_of_month !== null) {
                $date->modify('first day of next month');
                $date->setDate($date->format('Y'), $date->format('m'), $day_of_month);
            } else {
                $date->modify('+1 month');
            }
            break;
    }
    
    return $date->format('Y-m-d');
}

/**
 * Get day name from number
 */
function getDayName($day_number) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$day_number] ?? 'Monday';
}

/**
 * Process recurring tasks that are due
 */
function processRecurringTasks($db) {
    try {
        // Get due recurring tasks
        $stmt = $db->query("
            SELECT * FROM recurring_tasks 
            WHERE is_active = 1 
            AND next_run <= CURDATE()
            AND (end_date IS NULL OR end_date >= CURDATE())
        ");
        $recurring_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = 0;
        foreach ($recurring_tasks as $recurring) {
            $template_data = json_decode($recurring['template_data'], true);
            
            // Create actual task from template
            $task_created = createTaskFromTemplate($db, $recurring['seller_id'], $template_data);
            
            if ($task_created) {
                // Update next run date
                $next_run = calculateNextRunDate(
                    $recurring['next_run'],
                    $recurring['frequency'],
                    $recurring['day_of_week'],
                    $recurring['day_of_month']
                );
                
                $update = $db->prepare("
                    UPDATE recurring_tasks 
                    SET next_run = ? 
                    WHERE id = ?
                ");
                $update->execute([$next_run, $recurring['id']]);
                
                $processed++;
            }
        }
        
        return $processed;
    } catch (Exception $e) {
        error_log("Error processing recurring tasks: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create task from template data
 */
function createTaskFromTemplate($db, $seller_id, $template_data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO tasks 
            (seller_id, title, description, platform, product_link, commission_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        return $stmt->execute([
            $seller_id,
            $template_data['title'] ?? 'Task',
            $template_data['description'] ?? '',
            $template_data['platform'] ?? '',
            $template_data['product_link'] ?? '',
            $template_data['commission_amount'] ?? 0
        ]);
    } catch (Exception $e) {
        error_log("Error creating task from template: " . $e->getMessage());
        return false;
    }
}

/**
 * Set user availability
 */
function setUserAvailability($db, $user_id, $day_of_week, $start_time, $end_time, $is_available = 1) {
    try {
        $stmt = $db->prepare("
            INSERT INTO user_availability (user_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                is_available = VALUES(is_available)
        ");
        
        return $stmt->execute([$user_id, $day_of_week, $start_time, $end_time, $is_available]);
    } catch (Exception $e) {
        error_log("Error setting availability: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user availability schedule
 */
function getUserAvailability($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM user_availability 
            WHERE user_id = ? 
            ORDER BY day_of_week, start_time
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting availability: " . $e->getMessage());
        return [];
    }
}

/**
 * Export calendar to iCal format
 */
function exportCalendarToICal($db, $user_id, $start_date, $end_date) {
    $tasks = getUserTaskCalendar($db, $user_id, $start_date, $end_date);
    
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//ReviewFlow//Task Calendar//EN\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    
    foreach ($tasks as $task) {
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . md5($task['id'] . $task['task_id']) . "@reviewflow.com\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        $date_str = str_replace('-', '', $task['scheduled_date']);
        if ($task['scheduled_time']) {
            $time_str = str_replace(':', '', $task['scheduled_time']);
            $ical .= "DTSTART:" . $date_str . "T" . $time_str . "\r\n";
        } else {
            $ical .= "DTSTART;VALUE=DATE:" . $date_str . "\r\n";
        }
        
        $ical .= "SUMMARY:" . $task['title'] . "\r\n";
        $ical .= "STATUS:" . strtoupper($task['status']) . "\r\n";
        
        if ($task['notes']) {
            $ical .= "DESCRIPTION:" . str_replace(["\r", "\n"], [" ", " "], $task['notes']) . "\r\n";
        }
        
        $ical .= "END:VEVENT\r\n";
    }
    
    $ical .= "END:VCALENDAR\r\n";
    
    return $ical;
}

/**
 * Get calendar statistics
 */
function getCalendarStatistics($db, $user_id, $month, $year) {
    try {
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_scheduled,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM task_schedules ts
            JOIN tasks t ON ts.task_id = t.id
            WHERE ts.user_id = ?
            AND ts.scheduled_date BETWEEN ? AND ?
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting calendar stats: " . $e->getMessage());
        return [];
    }
}
