<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/ticket-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$current_page = 'support-tickets';
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validation
    if (empty($category) || empty($priority) || empty($subject) || empty($description)) {
        $error_message = 'All fields are required.';
    } else {
        try {
            $ticket_data = [
                'user_id' => $user_id,
                'category' => $category,
                'priority' => $priority,
                'subject' => $subject,
                'description' => $description
            ];
            
            $ticket_id = createTicket($pdo, $ticket_data);
            
            if ($ticket_id) {
                $_SESSION['success_message'] = 'Support ticket created successfully!';
                header('Location: view-ticket.php?id=' . $ticket_id);
                exit;
            } else {
                $error_message = 'Failed to create ticket. Please try again.';
            }
        } catch (Exception $e) {
            error_log('Create ticket error: ' . $e->getMessage());
            $error_message = 'An error occurred. Please try again.';
        }
    }
}

// Get categories
$categories = ['technical', 'billing', 'account', 'general', 'feedback', 'complaint'];
$priorities = ['low', 'medium', 'high', 'urgent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Ticket - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { width: 260px; position: fixed; left: 0; top: 60px; height: calc(100vh - 60px); background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); overflow-y: auto; transition: all 0.3s ease; z-index: 999; }
        .main-content { margin-left: 260px; padding: 20px; min-height: calc(100vh - 60px); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .priority-info { margin-top: 10px; padding: 10px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; font-size: 13px; }
        .priority-info ul { margin: 5px 0 0 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        @media (max-width: 768px) { .sidebar { left: -260px; } .main-content { margin-left: 0; } }
    </style>
</head>
<body class="light-mode">
    <?php 
    include '../includes/header.php'; 
    require_once __DIR__ . '/includes/sidebar.php';
    ?>
    
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Create Support Ticket</h2>
                <a href="support-tickets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tickets
                </a>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="category">
                        <i class="fas fa-folder"></i> Category *
                    </label>
                    <select name="category" id="category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">
                        <i class="fas fa-flag"></i> Priority *
                    </label>
                    <select name="priority" id="priority" required>
                        <option value="">Select priority level</option>
                        <?php foreach ($priorities as $pri): ?>
                            <option value="<?php echo $pri; ?>" <?php echo (isset($_POST['priority']) && $_POST['priority'] === $pri) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($pri); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="priority-info">
                        <strong>Priority Levels:</strong>
                        <ul>
                            <li><strong>Low:</strong> General inquiries (Response within 72 hours)</li>
                            <li><strong>Medium:</strong> Account or billing questions (Response within 48 hours)</li>
                            <li><strong>High:</strong> Service disruptions (Response within 24 hours)</li>
                            <li><strong>Urgent:</strong> Critical issues affecting operations (Response within 4 hours)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="subject">
                        <i class="fas fa-heading"></i> Subject *
                    </label>
                    <input 
                        type="text" 
                        name="subject" 
                        id="subject" 
                        placeholder="Brief description of your issue"
                        value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>"
                        required
                        maxlength="200"
                    >
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Description *
                    </label>
                    <textarea 
                        name="description" 
                        id="description" 
                        placeholder="Please provide detailed information about your issue..."
                        required
                    ><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <small style="color: #666; font-size: 12px;">
                        Tip: Include relevant order numbers, dates, or error messages to help us assist you better.
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="support-tickets.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../includes/version-display.php'; ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
    <script src="<?= APP_URL ?>/assets/js/theme.js"></script>
    <?php require_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
