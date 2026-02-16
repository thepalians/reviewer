<?php
/**
 * Scheduled Reports Management
 * Configure and manage automatic report generation
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'] ?? 1;
$page_title = 'Scheduled Reports';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_schedule') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $frequency = $_POST['frequency'] ?? 'weekly';
        $recipients = $_POST['recipients'] ?? '';
        $format = $_POST['format'] ?? 'pdf';
        
        $recipientsArray = array_filter(array_map('trim', explode(',', $recipients)));
        $recipientsJson = json_encode($recipientsArray);
        
        // Calculate next run time
        $nextRun = date('Y-m-d 09:00:00', strtotime('+1 day'));
        
        $stmt = $conn->prepare("
            INSERT INTO scheduled_reports 
            (template_id, name, frequency, recipients, format, next_run_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssssi', $templateId, $name, $frequency, $recipientsJson, $format, $nextRun, $admin_id);
        $stmt->execute();
        
        $success_message = 'Scheduled report created successfully';
    } elseif ($action === 'toggle_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE scheduled_reports SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $isActive, $scheduleId);
        $stmt->execute();
        
        $success_message = 'Schedule status updated';
    }
}

// Get all scheduled reports
$scheduledReports = $conn->query("
    SELECT 
        sr.*,
        rt.name as template_name,
        rt.report_type
    FROM scheduled_reports sr
    JOIN report_templates rt ON sr.template_id = rt.id
    ORDER BY sr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get available templates
$templates = $conn->query("
    SELECT * FROM report_templates ORDER BY name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ReviewFlow Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .form-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .schedule-card {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .schedule-card.inactive {
            opacity: 0.6;
            border-left-color: #9ca3af;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        <p>Automate report generation and delivery on a schedule.</p>
        
        <?php if (isset($success_message)): ?>
            <div class="alert"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <!-- Create New Schedule -->
        <div class="form-section">
            <h2>Create New Scheduled Report</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_schedule">
                
                <div class="form-group">
                    <label>Report Template</label>
                    <select name="template_id" required>
                        <option value="">Select a template...</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>">
                                <?php echo htmlspecialchars($template['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Schedule Name</label>
                    <input type="text" name="name" required placeholder="e.g., Weekly Revenue Report">
                </div>
                
                <div class="form-group">
                    <label>Frequency</label>
                    <select name="frequency" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Recipients (comma-separated emails)</label>
                    <textarea name="recipients" rows="3" required placeholder="admin@example.com, manager@example.com"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Export Format</label>
                    <select name="format">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Schedule</button>
            </form>
        </div>
        
        <!-- Existing Schedules -->
        <h2>Active Schedules</h2>
        <?php if (empty($scheduledReports)): ?>
            <p>No scheduled reports configured yet.</p>
        <?php else: ?>
            <?php foreach ($scheduledReports as $schedule): ?>
                <div class="schedule-card <?php echo $schedule['is_active'] ? '' : 'inactive'; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3><?php echo htmlspecialchars($schedule['name']); ?></h3>
                            <p><strong>Template:</strong> <?php echo htmlspecialchars($schedule['template_name']); ?></p>
                            <p><strong>Frequency:</strong> <?php echo ucfirst($schedule['frequency']); ?></p>
                            <p><strong>Format:</strong> <?php echo strtoupper($schedule['format']); ?></p>
                            <p><strong>Recipients:</strong> <?php echo count(json_decode($schedule['recipients'], true)); ?> people</p>
                            <p><strong>Next Run:</strong> <?php echo date('M d, Y H:i', strtotime($schedule['next_run_at'])); ?></p>
                            <?php if ($schedule['last_sent_at']): ?>
                                <p><strong>Last Sent:</strong> <?php echo date('M d, Y H:i', strtotime($schedule['last_sent_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge badge-<?php echo $schedule['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_schedule">
                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $schedule['is_active'] ? 0 : 1; ?>">
                            <button type="submit" class="btn <?php echo $schedule['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo $schedule['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="report-builder.php" class="btn btn-primary">Report Builder</a>
        </div>
    </div>
</body>
</html>
