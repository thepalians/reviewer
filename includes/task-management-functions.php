<?php
declare(strict_types=1);

/**
 * Task Management Functions
 * Advanced task dependencies, milestones, and templates
 */

/**
 * Create task dependency
 */
function createTaskDependency(int $taskId, int $dependsOnTaskId, string $dependencyType = 'finish_to_start'): bool {
    global $pdo;
    
    try {
        // Check for circular dependencies
        if (hasCircularDependency($taskId, $dependsOnTaskId)) {
            error_log("Circular dependency detected");
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE dependency_type = ?
        ");
        return $stmt->execute([$taskId, $dependsOnTaskId, $dependencyType, $dependencyType]);
    } catch (PDOException $e) {
        error_log("Error creating task dependency: " . $e->getMessage());
        return false;
    }
}

/**
 * Check for circular dependencies
 */
function hasCircularDependency(int $taskId, int $dependsOnTaskId): bool {
    global $pdo;
    
    try {
        // Check if dependsOnTask depends on taskId (directly or indirectly)
        $visited = [];
        $queue = [$dependsOnTaskId];
        
        while (!empty($queue)) {
            $current = array_shift($queue);
            
            if ($current === $taskId) {
                return true; // Circular dependency found
            }
            
            if (in_array($current, $visited)) {
                continue;
            }
            
            $visited[] = $current;
            
            // Get dependencies of current task
            $stmt = $pdo->prepare("SELECT depends_on_task_id FROM task_dependencies WHERE task_id = ?");
            $stmt->execute([$current]);
            $dependencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $queue = array_merge($queue, $dependencies);
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error checking circular dependency: " . $e->getMessage());
        return false;
    }
}

/**
 * Get task dependencies
 */
function getTaskDependencies(int $taskId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT td.*, t.title as depends_on_title, t.task_status
            FROM task_dependencies td
            JOIN tasks t ON td.depends_on_task_id = t.id
            WHERE td.task_id = ?
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting task dependencies: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if task can start based on dependencies
 */
function canTaskStart(int $taskId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM task_dependencies td
            JOIN tasks t ON td.depends_on_task_id = t.id
            WHERE td.task_id = ? 
            AND td.dependency_type = 'finish_to_start'
            AND t.task_status != 'completed'
        ");
        $stmt->execute([$taskId]);
        
        return $stmt->fetchColumn() === 0;
    } catch (PDOException $e) {
        error_log("Error checking if task can start: " . $e->getMessage());
        return true; // Default to allowing task to start
    }
}

/**
 * Create task milestone
 */
function createMilestone(array $data): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO task_milestones 
            (name, description, seller_id, total_steps, status, deadline)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['seller_id'],
            $data['total_steps'] ?? 1,
            $data['status'] ?? 'draft',
            $data['deadline'] ?? null
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating milestone: " . $e->getMessage());
        return null;
    }
}

/**
 * Add milestone step
 */
function addMilestoneStep(int $milestoneId, int $stepNumber, string $title, ?string $description = null, ?int $taskId = null): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO milestone_steps 
            (milestone_id, step_number, title, description, task_id, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$milestoneId, $stepNumber, $title, $description, $taskId])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error adding milestone step: " . $e->getMessage());
        return null;
    }
}

/**
 * Update milestone step status
 */
function updateMilestoneStepStatus(int $stepId, string $status): bool {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update step status
        $stmt = $pdo->prepare("
            UPDATE milestone_steps 
            SET status = ?, completed_at = ?
            WHERE id = ?
        ");
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, $completedAt, $stepId]);
        
        // Get milestone ID
        $stmt = $pdo->prepare("SELECT milestone_id FROM milestone_steps WHERE id = ?");
        $stmt->execute([$stepId]);
        $milestoneId = $stmt->fetchColumn();
        
        if ($milestoneId) {
            // Update milestone completed steps count
            $stmt = $pdo->prepare("
                UPDATE task_milestones 
                SET completed_steps = (
                    SELECT COUNT(*) FROM milestone_steps 
                    WHERE milestone_id = ? AND status = 'completed'
                )
                WHERE id = ?
            ");
            $stmt->execute([$milestoneId, $milestoneId]);
            
            // Check if all steps completed
            $stmt = $pdo->prepare("
                SELECT total_steps, completed_steps 
                FROM task_milestones 
                WHERE id = ?
            ");
            $stmt->execute([$milestoneId]);
            $milestone = $stmt->fetch();
            
            if ($milestone && $milestone['completed_steps'] >= $milestone['total_steps']) {
                $stmt = $pdo->prepare("UPDATE task_milestones SET status = 'completed' WHERE id = ?");
                $stmt->execute([$milestoneId]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating milestone step status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get milestone with steps
 */
function getMilestone(int $milestoneId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM task_milestones WHERE id = ?");
        $stmt->execute([$milestoneId]);
        $milestone = $stmt->fetch();
        
        if (!$milestone) return null;
        
        // Get steps
        $stmt = $pdo->prepare("
            SELECT ms.*, t.title as task_title
            FROM milestone_steps ms
            LEFT JOIN tasks t ON ms.task_id = t.id
            WHERE ms.milestone_id = ?
            ORDER BY ms.step_number
        ");
        $stmt->execute([$milestoneId]);
        $milestone['steps'] = $stmt->fetchAll();
        
        return $milestone;
    } catch (PDOException $e) {
        error_log("Error getting milestone: " . $e->getMessage());
        return null;
    }
}

/**
 * Create advanced task template
 */
function createAdvancedTaskTemplate(array $data): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO advanced_task_templates 
            (name, description, category_id, template_data, steps, 
             default_commission, default_deadline_days, is_public, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            json_encode($data['template_data']),
            json_encode($data['steps'] ?? []),
            $data['default_commission'] ?? null,
            $data['default_deadline_days'] ?? 7,
            $data['is_public'] ?? 0,
            $data['created_by']
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating advanced task template: " . $e->getMessage());
        return null;
    }
}

/**
 * Clone task from template
 */
function cloneTaskFromTemplate(int $templateId, array $overrides = []): ?int {
    global $pdo;
    
    try {
        // Get template
        $stmt = $pdo->prepare("SELECT * FROM advanced_task_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) return null;
        
        $templateData = json_decode($template['template_data'], true);
        $steps = json_decode($template['steps'], true);
        
        // Merge with overrides
        $taskData = array_merge($templateData, $overrides);
        
        // Create task (assuming tasks table structure)
        $stmt = $pdo->prepare("
            INSERT INTO tasks 
            (title, description, commission, deadline, created_by, task_status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        $deadline = isset($taskData['deadline']) 
            ? $taskData['deadline'] 
            : date('Y-m-d', strtotime('+' . $template['default_deadline_days'] . ' days'));
        
        $stmt->execute([
            $taskData['title'] ?? $template['name'],
            $taskData['description'] ?? $template['description'],
            $taskData['commission'] ?? $template['default_commission'],
            $deadline,
            $taskData['created_by']
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Increment template use count
        $stmt = $pdo->prepare("UPDATE advanced_task_templates SET use_count = use_count + 1 WHERE id = ?");
        $stmt->execute([$templateId]);
        
        return (int)$taskId;
    } catch (PDOException $e) {
        error_log("Error cloning task from template: " . $e->getMessage());
        return null;
    }
}

/**
 * Create bulk task operation
 */
function createBulkTaskOperation(string $operationType, array $taskIds, ?array $changes = null, int $createdBy): ?int {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bulk_task_operations 
            (operation_type, task_ids, changes, total_count, created_by, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([
            $operationType,
            json_encode($taskIds),
            json_encode($changes ?? []),
            count($taskIds),
            $createdBy
        ])) {
            return (int)$pdo->lastInsertId();
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error creating bulk task operation: " . $e->getMessage());
        return null;
    }
}

/**
 * Process bulk task operation
 */
function processBulkTaskOperation(int $operationId): bool {
    global $pdo;
    
    try {
        // Get operation details
        $stmt = $pdo->prepare("SELECT * FROM bulk_task_operations WHERE id = ?");
        $stmt->execute([$operationId]);
        $operation = $stmt->fetch();
        
        if (!$operation || $operation['status'] !== 'pending') {
            return false;
        }
        
        // Update status to processing
        $stmt = $pdo->prepare("UPDATE bulk_task_operations SET status = 'processing' WHERE id = ?");
        $stmt->execute([$operationId]);
        
        $taskIds = json_decode($operation['task_ids'], true);
        $changes = json_decode($operation['changes'], true);
        $processedCount = 0;
        $errors = [];
        
        foreach ($taskIds as $taskId) {
            try {
                $result = executeBulkOperation($operation['operation_type'], $taskId, $changes);
                if ($result) {
                    $processedCount++;
                } else {
                    $errors[] = "Failed to process task ID: $taskId";
                }
            } catch (Exception $e) {
                $errors[] = "Error processing task ID $taskId: " . $e->getMessage();
            }
        }
        
        // Update operation status
        $finalStatus = ($processedCount === count($taskIds)) ? 'completed' : (($processedCount > 0) ? 'partial' : 'failed');
        $stmt = $pdo->prepare("
            UPDATE bulk_task_operations 
            SET status = ?, processed_count = ?, error_log = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$finalStatus, $processedCount, json_encode($errors), $operationId]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error processing bulk operation: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute single bulk operation
 */
function executeBulkOperation(string $operationType, int $taskId, array $changes): bool {
    global $pdo;
    
    try {
        switch ($operationType) {
            case 'update':
                $setClauses = [];
                $params = [];
                foreach ($changes as $field => $value) {
                    $setClauses[] = "$field = ?";
                    $params[] = $value;
                }
                $params[] = $taskId;
                
                $stmt = $pdo->prepare("UPDATE tasks SET " . implode(', ', $setClauses) . " WHERE id = ?");
                return $stmt->execute($params);
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                return $stmt->execute([$taskId]);
                
            case 'status_change':
                $stmt = $pdo->prepare("UPDATE tasks SET task_status = ? WHERE id = ?");
                return $stmt->execute([$changes['status'], $taskId]);
                
            case 'assign':
                $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
                return $stmt->execute([$changes['assigned_to'], $taskId]);
                
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("Error executing bulk operation: " . $e->getMessage());
        return false;
    }
}
