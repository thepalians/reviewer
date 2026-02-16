<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$errors = [];
$success = false;

// Handle FAQ CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }
    
    $action = sanitizeInput($_POST['action'] ?? '');
    
    // ADD FAQ
    if ($action === 'add') {
        $question = sanitizeInput($_POST['question'] ?? '');
        $answer = sanitizeInput($_POST['answer'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        
        if (empty($question)) $errors[] = 'Question is required';
        if (empty($answer)) $errors[] = 'Answer is required';
        if (empty($category)) $errors[] = 'Category is required';
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO chatbot_faq (question, answer, category, is_active)
                    VALUES (:question, :answer, :category, true)
                ");
                
                $stmt->execute([
                    ':question' => $question,
                    ':answer' => $answer,
                    ':category' => $category
                ]);
                
                logActivity('Admin added FAQ', null, null);
                $success = true;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Failed to add FAQ';
            }
        }
    }
    
    // DELETE FAQ
    if ($action === 'delete') {
        $faq_id = intval($_POST['faq_id'] ?? 0);
        
        if ($faq_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM chatbot_faq WHERE id = :id");
                $stmt->execute([':id' => $faq_id]);
                
                logActivity('Admin deleted FAQ', null, null);
                $success = true;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = 'Failed to delete FAQ';
            }
        }
    }
}

// Fetch all FAQs
try {
    $stmt = $pdo->query("SELECT * FROM chatbot_faq ORDER BY category, id");
    $faqs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $faqs = [];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot FAQ Manager - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
        }
        .admin-wrapper {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-menu a {
            color: #bbb;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-content {
            padding: 30px;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .faq-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .faq-question {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .faq-answer {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .faq-category {
            display: inline-block;
            background: #ecf0f1;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #2c3e50;
            font-weight: 600;
        }
        .btn-delete {
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        .btn-add {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <div class="sidebar-brand" style="text-align: center; margin-bottom: 30px;">
                <h3>⚙️ Admin</h3>
            </div>
            <ul class="sidebar-menu" style="list-style: none;">
                <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php">📊 Dashboard</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php">👥 Reviewers</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending.php">📋 Pending Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed.php">✓ Completed Tasks</a></li>
                <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php" class="active">🤖 Chatbot FAQ</a></li>
                <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <a href="<?php echo APP_URL; ?>/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                </li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h1 style="color: #2c3e50; margin-bottom: 30px;">🤖 Chatbot FAQ Manager</h1>
            
            <div class="grid-2">
                <!-- Add FAQ Form -->
                <div class="form-card">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;">➕ Add New FAQ</h3>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">✓ FAQ added successfully!</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="question">Question *</label>
                            <input type="text" id="question" name="question" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="answer">Answer *</label>
                            <textarea id="answer" name="answer" class="form-control" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="Getting Started">Getting Started</option>
                                <option value="Tasks">Tasks</option>
                                <option value="Payment">Payment & Refund</option>
                                <option value="Account">Account</option>
                                <option value="Technical">Technical Support</option>
                            </select>
                        </div>
                        
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-add">Add FAQ</button>
                    </form>
                </div>
                
                <!-- FAQ List -->
                <div>
                    <h3 style="margin-bottom: 20px; color: #2c3e50;">📚 All FAQs (<?php echo count($faqs); ?>)</h3>
                    
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($faqs)): ?>
                            <p style="color: #999;">No FAQs yet. Add some!</p>
                        <?php else: ?>
                            <?php foreach ($faqs as $faq): ?>
                                <div class="faq-card">
                                    <div class="faq-question"><?php echo escape($faq['question']); ?></div>
                                    <div class="faq-answer"><?php echo nl2br(escape($faq['answer'])); ?></div>
                                    <div>
                                        <span class="faq-category"><?php echo escape($faq['category']); ?></span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Delete this FAQ?');">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
