<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$errors = [];
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add answer to FAQ and mark resolved
    if ($action === 'add_answer') {
        $unanswered_id = intval($_POST['unanswered_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($question)) $errors[] = 'Question required';
        if (empty($answer)) $errors[] = 'Answer required';
        if (empty($category)) $errors[] = 'Category required';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Add to FAQ
                $stmt = $pdo->prepare("INSERT INTO chatbot_faq (question, answer, category, is_active) VALUES (:q, :a, :c, 1)");
                $stmt->execute([':q' => $question, ':a' => $answer, ':c' => $category]);
                $faq_id = $pdo->lastInsertId();
                
                // Mark as resolved
                $stmt = $pdo->prepare("UPDATE chatbot_unanswered SET is_resolved = 1, resolved_faq_id = :faq_id WHERE id = :id");
                $stmt->execute([':faq_id' => $faq_id, ':id' => $unanswered_id]);
                
                $pdo->commit();
                $success = 'Answer added and question resolved!';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to save: ' . $e->getMessage();
            }
        }
    }
    
    // Delete/Ignore question
    if ($action === 'ignore') {
        $unanswered_id = intval($_POST['unanswered_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM chatbot_unanswered WHERE id = :id");
            $stmt->execute([':id' => $unanswered_id]);
            $success = 'Question removed';
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete';
        }
    }
}

// Fetch unanswered questions (sorted by asked_count - most asked first)
try {
    $stmt = $pdo->query("
        SELECT * FROM chatbot_unanswered 
        WHERE is_resolved = 0 
        ORDER BY asked_count DESC, last_asked_at DESC
    ");
    $unanswered = $stmt->fetchAll();
} catch (PDOException $e) {
    $unanswered = [];
}

// Stats
try {
    $total_unanswered = count($unanswered);
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 1");
    $total_resolved = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_unanswered = 0;
    $total_resolved = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unanswered Questions - Admin</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#f5f5f5;font-family:-apple-system,sans-serif}
        .wrapper{display:grid;grid-template-columns:220px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px}
        .sidebar h3{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:8px}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .content{padding:25px}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .page-title{font-size:24px;color:#2c3e50}
        .stats{display:flex;gap:15px}
        .stat-box{background:#fff;padding:15px 25px;border-radius:10px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .stat-num{font-size:28px;font-weight:700;color:#e74c3c}
        .stat-num.green{color:#27ae60}
        .stat-label{font-size:12px;color:#888}
        .alert{padding:12px 15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .question-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1);border-left:4px solid #e74c3c}
        .question-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .question-text{font-size:16px;font-weight:600;color:#2c3e50;flex:1}
        .question-meta{display:flex;gap:15px;margin-bottom:15px;font-size:13px;color:#666}
        .meta-item{display:flex;align-items:center;gap:5px}
        .badge{padding:3px 10px;border-radius:15px;font-size:11px;font-weight:600}
        .badge-count{background:#e74c3c;color:#fff}
        .badge-user{background:#3498db;color:#fff}
        .answer-form{background:#f8f9fa;padding:15px;border-radius:8px;margin-top:15px;display:none}
        .answer-form.show{display:block}
        .form-group{margin-bottom:12px}
        .form-group label{display:block;font-weight:600;margin-bottom:5px;color:#333;font-size:13px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:80px;resize:vertical}
        .btn-row{display:flex;gap:10px;margin-top:15px}
        .btn{padding:10px 20px;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px}
        .btn-success{background:#27ae60;color:#fff}
        .btn-primary{background:#3498db;color:#fff}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-secondary{background:#95a5a6;color:#fff}
        .btn:hover{opacity:0.9}
        .empty{text-align:center;padding:60px;background:#fff;border-radius:12px;color:#666}
        .empty h3{color:#27ae60;margin-bottom:10px}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.stats{flex-direction:column}}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="page-header">
            <h1 class="page-title">❓ Unanswered Questions</h1>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-num"><?php echo $total_unanswered; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num green"><?php echo $total_resolved; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo escape($success); ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger">✗ <?php echo escape($e); ?></div>
        <?php endforeach; ?>
        
        <p style="color:#666;margin-bottom:20px;font-size:14px">
            💡 These are questions users asked but chatbot couldn't answer. Add answers to train your AI!
        </p>
        
        <?php if (empty($unanswered)): ?>
            <div class="empty">
                <h3>🎉 All caught up!</h3>
                <p>No unanswered questions. Your chatbot is doing great!</p>
            </div>
        <?php else: ?>
            <?php foreach ($unanswered as $q): ?>
                <div class="question-card" id="card-<?php echo $q['id']; ?>">
                    <div class="question-header">
                        <div class="question-text">"<?php echo escape($q['question']); ?>"</div>
                        <span class="badge badge-count">Asked <?php echo $q['asked_count']; ?>x</span>
                    </div>
                    
                    <div class="question-meta">
                        <div class="meta-item">👤 <?php echo escape($q['user_name'] ?? 'Guest'); ?></div>
                        <div class="meta-item">📅 First: <?php echo date('d M Y', strtotime($q['first_asked_at'])); ?></div>
                        <div class="meta-item">🕐 Last: <?php echo date('d M Y, H:i', strtotime($q['last_asked_at'])); ?></div>
                    </div>
                    
                    <div class="btn-row">
                        <button class="btn btn-success" onclick="showAnswerForm(<?php echo $q['id']; ?>)">✓ Add Answer</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Ignore this question?')">
                            <input type="hidden" name="action" value="ignore">
                            <input type="hidden" name="unanswered_id" value="<?php echo $q['id']; ?>">
                            <button type="submit" class="btn btn-secondary">✕ Ignore</button>
                        </form>
                    </div>
                    
                    <!-- Answer Form (Hidden by default) -->
                    <div class="answer-form" id="form-<?php echo $q['id']; ?>">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_answer">
                            <input type="hidden" name="unanswered_id" value="<?php echo $q['id']; ?>">
                            
                            <div class="form-group">
                                <label>Question (you can edit)</label>
                                <input type="text" name="question" class="form-control" value="<?php echo escape($q['question']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Answer *</label>
                                <textarea name="answer" class="form-control" placeholder="Type the answer here..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="Getting Started">Getting Started</option>
                                    <option value="Tasks">Tasks</option>
                                    <option value="Payment">Payment & Refund</option>
                                    <option value="Account">Account</option>
                                    <option value="Technical">Technical Support</option>
                                </select>
                            </div>
                            
                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary">💾 Save & Add to FAQ</button>
                                <button type="button" class="btn btn-secondary" onclick="hideAnswerForm(<?php echo $q['id']; ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function showAnswerForm(id) {
    document.getElementById('form-' + id).classList.add('show');
}
function hideAnswerForm(id) {
    document.getElementById('form-' + id).classList.remove('show');
}
</script>
</body>
</html>
