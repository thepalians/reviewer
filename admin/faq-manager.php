<?php
/**
 * ReviewFlow - Admin FAQ Manager
 * Manage chatbot FAQ entries
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Handle Add FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faq'])) {
    $category = sanitizeInput($_POST['category'] ?? 'general');
    $question = sanitizeInput($_POST['question'] ?? '');
    $answer = sanitizeInput($_POST['answer'] ?? '');
    $keywords = sanitizeInput($_POST['keywords'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($question)) {
        $errors[] = "Question is required";
    }
    if (empty($answer)) {
        $errors[] = "Answer is required";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO chatbot_faq (category, question, answer, keywords, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$category, $question, $answer, $keywords, $is_active]);
            
            $success = "FAQ added successfully!";
            logActivity("Added FAQ: " . substr($question, 0, 50));
            
        } catch (PDOException $e) {
            $errors[] = "Failed to add FAQ";
            error_log("Add FAQ Error: " . $e->getMessage());
        }
    }
}

// Handle Edit FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_faq'])) {
    $faq_id = intval($_POST['faq_id'] ?? 0);
    $category = sanitizeInput($_POST['category'] ?? 'general');
    $question = sanitizeInput($_POST['question'] ?? '');
    $answer = sanitizeInput($_POST['answer'] ?? '');
    $keywords = sanitizeInput($_POST['keywords'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($faq_id <= 0) {
        $errors[] = "Invalid FAQ";
    }
    if (empty($question)) {
        $errors[] = "Question is required";
    }
    if (empty($answer)) {
        $errors[] = "Answer is required";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE chatbot_faq 
                SET category = ?, question = ?, answer = ?, keywords = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$category, $question, $answer, $keywords, $is_active, $faq_id]);
            
            $success = "FAQ updated successfully!";
            logActivity("Updated FAQ #$faq_id");
            
        } catch (PDOException $e) {
            $errors[] = "Failed to update FAQ";
            error_log("Edit FAQ Error: " . $e->getMessage());
        }
    }
}

// Handle Delete FAQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faq'])) {
    $faq_id = intval($_POST['faq_id'] ?? 0);
    
    if ($faq_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM chatbot_faq WHERE id = ?");
            $stmt->execute([$faq_id]);
            
            $success = "FAQ deleted successfully!";
            logActivity("Deleted FAQ #$faq_id");
            
        } catch (PDOException $e) {
            $errors[] = "Failed to delete FAQ";
        }
    }
}

// Handle Toggle Active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $faq_id = intval($_POST['faq_id'] ?? 0);
    
    if ($faq_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE chatbot_faq SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$faq_id]);
            
            $success = "FAQ status toggled!";
            
        } catch (PDOException $e) {
            $errors[] = "Failed to toggle status";
        }
    }
}

// Handle Bulk Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import'])) {
    $import_data = $_POST['import_data'] ?? '';
    
    if (!empty($import_data)) {
        try {
            $lines = explode("\n", $import_data);
            $imported = 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO chatbot_faq (category, question, answer, keywords, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            foreach ($lines as $line) {
                $parts = str_getcsv($line, '|');
                if (count($parts) >= 3) {
                    $category = trim($parts[0]) ?: 'general';
                    $question = trim($parts[1]);
                    $answer = trim($parts[2]);
                    $keywords = isset($parts[3]) ? trim($parts[3]) : '';
                    
                    if (!empty($question) && !empty($answer)) {
                        $stmt->execute([$category, $question, $answer, $keywords]);
                        $imported++;
                    }
                }
            }
            
            $success = "Imported $imported FAQ entries!";
            logActivity("Bulk imported $imported FAQs");
            
        } catch (PDOException $e) {
            $errors[] = "Import failed: " . $e->getMessage();
        }
    }
}

// Filters
$filter_category = $_GET['category'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = "1=1";
$params = [];

if ($filter_category !== 'all') {
    $where .= " AND category = ?";
    $params[] = $filter_category;
}

if ($filter_status === 'active') {
    $where .= " AND is_active = 1";
} elseif ($filter_status === 'inactive') {
    $where .= " AND is_active = 0";
}

if (!empty($search)) {
    $where .= " AND (question LIKE ? OR answer LIKE ? OR keywords LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get FAQs
try {
    // Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatbot_faq WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Get data
    $stmt = $pdo->prepare("
        SELECT * FROM chatbot_faq 
        WHERE $where 
        ORDER BY category ASC, usage_count DESC, created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $faqs = $stmt->fetchAll();
    
    // Get categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM chatbot_faq ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_faq");
    $total_faqs = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_faq WHERE is_active = 1");
    $active_faqs = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(usage_count) FROM chatbot_faq");
    $total_usage = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_count = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("FAQ Manager Error: " . $e->getMessage());
    $faqs = [];
    $categories = [];
    $total = $total_faqs = $active_faqs = $total_usage = $unanswered_count = 0;
    $total_pages = 0;
}

// Get for edit
$edit_faq = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM chatbot_faq WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_faq = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Get sidebar counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unread_messages = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Manager - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:25px;overflow-x:hidden}
        
        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .header-actions{display:flex;gap:10px}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        
        /* Stats */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04);text-align:center}
        .stat-card.highlight{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .stat-value{font-size:32px;font-weight:700;margin-bottom:5px}
        .stat-label{font-size:13px;opacity:0.8}
        
        /* Buttons */
        .btn{padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#f1f5f9;color:#475569}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-sm{padding:6px 12px;font-size:12px}
        .btn:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        
        /* Filters */
        .filters-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .filters-row{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
        .filter-group{display:flex;flex-direction:column;gap:5px}
        .filter-group label{font-size:12px;font-weight:600;color:#64748b}
        .filter-group select,.filter-group input{padding:10px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:150px}
        .filter-group select:focus,.filter-group input:focus{border-color:#667eea;outline:none}
        .filter-actions{margin-left:auto;display:flex;gap:10px}
        
        /* Table */
        .table-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .table-header{padding:20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .table-title{font-size:18px;font-weight:600;color:#1e293b}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase}
        td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        tr:last-child td{border-bottom:none}
        tr:hover{background:#f8fafc}
        
        .category-badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;display:inline-block}
        .category-badge.general{background:#e0f2fe;color:#0369a1}
        .category-badge.tasks{background:#fef3c7;color:#d97706}
        .category-badge.wallet{background:#dcfce7;color:#16a34a}
        .category-badge.withdrawal{background:#fce7f3;color:#be185d}
        .category-badge.referral{background:#f3e8ff;color:#7c3aed}
        .category-badge.account{background:#e0e7ff;color:#4338ca}
        .category-badge.support{background:#fee2e2;color:#dc2626}
        
        .status-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
        .status-dot.active{background:#10b981}
        .status-dot.inactive{background:#ef4444}
        
        .usage-badge{background:#f1f5f9;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;color:#64748b}
        
        .question-text{max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500}
        .answer-preview{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#64748b;font-size:12px}
        
        .actions-cell{display:flex;gap:5px}
        
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .modal-title{font-size:20px;font-weight:600;color:#1e293b}
        .modal-close{width:35px;height:35px;border-radius:50%;background:#f1f5f9;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
        
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#1e293b;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px}
        .form-control:focus{border-color:#667eea;outline:none}
        select.form-control{cursor:pointer}
        textarea.form-control{min-height:120px;resize:vertical;font-family:inherit;line-height:1.6}
        
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        
        .form-hint{font-size:12px;color:#94a3b8;margin-top:5px}
        
        .checkbox-group{display:flex;align-items:center;gap:10px}
        .checkbox-group input{width:18px;height:18px;accent-color:#667eea}
        .checkbox-group label{font-size:14px;color:#1e293b;cursor:pointer}
        
        .btn-group{display:flex;gap:10px;margin-top:25px}
        .btn-group .btn{flex:1}
        
        /* Variables help */
        .variables-help{background:#f8fafc;border-radius:10px;padding:15px;margin-top:10px}
        .variables-help h4{font-size:13px;color:#64748b;margin-bottom:10px}
        .variables-help code{background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:12px;margin-right:8px;cursor:pointer}
        .variables-help code:hover{background:#667eea;color:#fff}
        
        /* Pagination */
        .pagination{display:flex;justify-content:center;gap:8px;padding:20px}
        .page-btn{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;color:#64748b;text-decoration:none;font-weight:600;font-size:14px;border:1px solid #e2e8f0;cursor:pointer}
        .page-btn:hover{background:#667eea;color:#fff;border-color:#667eea}
        .page-btn.active{background:#667eea;color:#fff;border-color:#667eea}
        .page-btn.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none}
        
        /* Empty State */
        .empty-state{text-align:center;padding:60px 20px}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#64748b;margin-bottom:10px}
        .empty-state p{color:#94a3b8;font-size:14px}
        
        /* Responsive */
        @media(max-width:1200px){
            .stats-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
        }
        @media(max-width:768px){
            .filters-row{flex-direction:column}
            .filter-group{width:100%}
            .filter-group select,.filter-group input{width:100%}
            .filter-actions{width:100%;margin-left:0}
            .filter-actions .btn{flex:1}
            .form-row{grid-template-columns:1fr}
            .stats-grid{grid-template-columns:1fr}
            th,td{padding:10px;font-size:12px}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">🤖 Chatbot FAQ Manager</div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="showImportModal()">📥 Bulk Import</button>
                <button class="btn btn-primary" onclick="showAddModal()">➕ Add FAQ</button>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-value"><?php echo $total_faqs; ?></div>
                <div class="stat-label">Total FAQs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_faqs; ?></div>
                <div class="stat-label">Active FAQs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_usage); ?></div>
                <div class="stat-label">Total Usage</div>
            </div>
            <div class="stat-card" style="<?php echo $unanswered_count > 0 ? 'background:#fef2f2' : ''; ?>">
                <div class="stat-value" style="<?php echo $unanswered_count > 0 ? 'color:#dc2626' : ''; ?>"><?php echo $unanswered_count; ?></div>
                <div class="stat-label">Unanswered Questions</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo escape($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>><?php echo ucfirst(escape($cat)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search question, answer..." value="<?php echo escape($search); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="?" class="btn btn-secondary">↺ Reset</a>
                </div>
            </form>
        </div>
        
        <!-- FAQ Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">FAQ Entries</div>
                <div style="font-size:14px;color:#64748b"><?php echo $total; ?> entries</div>
            </div>
            
            <?php if (empty($faqs)): ?>
                <div class="empty-state">
                    <div class="icon">🤖</div>
                    <h3>No FAQs Found</h3>
                    <p>Add your first FAQ to train the chatbot.</p>
                    <button class="btn btn-primary" style="margin-top:15px" onclick="showAddModal()">➕ Add FAQ</button>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Category</th>
                                <th>Question</th>
                                <th>Answer Preview</th>
                                <th>Keywords</th>
                                <th>Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                            <button type="submit" name="toggle_active" class="status-dot <?php echo $faq['is_active'] ? 'active' : 'inactive'; ?>" title="Click to toggle" style="border:none;cursor:pointer"></button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="category-badge <?php echo $faq['category']; ?>">
                                            <?php echo ucfirst($faq['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="question-text" title="<?php echo escape($faq['question']); ?>">
                                            <?php echo escape($faq['question']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="answer-preview" title="<?php echo escape($faq['answer']); ?>">
                                            <?php echo escape(substr($faq['answer'], 0, 50)); ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <div style="max-width:150px;font-size:11px;color:#64748b">
                                            <?php echo escape(substr($faq['keywords'] ?? '-', 0, 30)); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="usage-badge"><?php echo $faq['usage_count']; ?></span>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <a href="?edit=<?php echo $faq['id']; ?>" class="btn btn-secondary btn-sm">✏️</a>
                                            <button class="btn btn-sm" style="background:#e0f2fe;color:#0369a1" onclick="previewFAQ(<?php echo htmlspecialchars(json_encode($faq), ENT_QUOTES); ?>)">👁️</button>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this FAQ?')">
                                                <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                                <button type="submit" name="delete_faq" class="btn btn-danger btn-sm">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $query_string = http_build_query($query_params);
                        $query_string = $query_string ? "&$query_string" : '';
                        ?>
                        <a href="?page=1<?php echo $query_string; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i . $query_string; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo $total_pages . $query_string; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal" id="faqModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" id="faqModalTitle">➕ Add New FAQ</div>
            <button class="modal-close" onclick="hideModal('faqModal')">×</button>
        </div>
        <form method="POST" id="faqForm">
            <input type="hidden" name="faq_id" id="faq_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="faq_category" class="form-control">
                        <option value="general">General</option>
                        <option value="tasks">Tasks</option>
                        <option value="wallet">Wallet</option>
                        <option value="withdrawal">Withdrawal</option>
                        <option value="referral">Referral</option>
                        <option value="account">Account</option>
                        <option value="support">Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="checkbox-group" style="margin-top:30px">
                        <input type="checkbox" name="is_active" id="faq_active" checked>
                        <label for="faq_active">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Question *</label>
                <input type="text" name="question" id="faq_question" class="form-control" placeholder="Enter the question..." required>
            </div>
            
            <div class="form-group">
                <label>Answer *</label>
                <textarea name="answer" id="faq_answer" class="form-control" placeholder="Enter the answer..." required rows="6"></textarea>
                <div class="variables-help">
                    <h4>Available Variables (click to insert):</h4>
                    <code onclick="insertVariable('{user_name}')">{user_name}</code>
                    <code onclick="insertVariable('{balance}')">{balance}</code>
                    <code onclick="insertVariable('{referral_code}')">{referral_code}</code>
                    <code onclick="insertVariable('{min_withdrawal}')">{min_withdrawal}</code>
                    <code onclick="insertVariable('{referral_bonus}')">{referral_bonus}</code>
                    <code onclick="insertVariable('{support_email}')">{support_email}</code>
                </div>
            </div>
            
            <div class="form-group">
                <label>Keywords (comma separated)</label>
                <input type="text" name="keywords" id="faq_keywords" class="form-control" placeholder="keyword1, keyword2, keyword3...">
                <div class="form-hint">Keywords help match user questions. Add variations and synonyms.</div>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="hideModal('faqModal')">Cancel</button>
                <button type="submit" name="add_faq" id="faqSubmitBtn" class="btn btn-primary">➕ Add FAQ</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div class="modal" id="importModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">📥 Bulk Import FAQs</div>
            <button class="modal-close" onclick="hideModal('importModal')">×</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Import Data</label>
                <textarea name="import_data" class="form-control" rows="10" placeholder="Format: category|question|answer|keywords (one per line)

Example:
general|What is ReviewFlow?|ReviewFlow is a platform to earn money...|what, reviewflow, about
tasks|How do tasks work?|Each task has 4 steps...|task, work, how"></textarea>
                <div class="form-hint">Each line: category|question|answer|keywords (keywords optional)</div>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="hideModal('importModal')">Cancel</button>
                <button type="submit" name="bulk_import" class="btn btn-primary">📥 Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">👁️ FAQ Preview</div>
            <button class="modal-close" onclick="hideModal('previewModal')">×</button>
        </div>
        <div id="previewContent"></div>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary" onclick="hideModal('previewModal')" style="width:100%">Close</button>
        </div>
    </div>
</div>

<?php if ($edit_faq): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showEditModal(<?php echo json_encode($edit_faq); ?>);
});
</script>
<?php endif; ?>

<script>
function showModal(id) {
    document.getElementById(id).classList.add('show');
}

function hideModal(id) {
    document.getElementById(id).classList.remove('show');
    if (id === 'faqModal') {
        // Reset form
        document.getElementById('faqForm').reset();
        document.getElementById('faq_id').value = '';
        document.getElementById('faqModalTitle').textContent = '➕ Add New FAQ';
        document.getElementById('faqSubmitBtn').name = 'add_faq';
        document.getElementById('faqSubmitBtn').textContent = '➕ Add FAQ';
    }
}

function showAddModal() {
    hideModal('faqModal'); // Reset first
    setTimeout(() => showModal('faqModal'), 100);
}

function showEditModal(faq) {
    document.getElementById('faq_id').value = faq.id;
    document.getElementById('faq_category').value = faq.category;
    document.getElementById('faq_question').value = faq.question;
    document.getElementById('faq_answer').value = faq.answer;
    document.getElementById('faq_keywords').value = faq.keywords || '';
    document.getElementById('faq_active').checked = faq.is_active == 1;
    
    document.getElementById('faqModalTitle').textContent = '✏️ Edit FAQ';
    document.getElementById('faqSubmitBtn').name = 'edit_faq';
    document.getElementById('faqSubmitBtn').textContent = '💾 Save Changes';
    
    showModal('faqModal');
}

function showImportModal() {
    showModal('importModal');
}

function previewFAQ(faq) {
    const content = `
        <div style="margin-bottom:15px">
            <strong style="color:#64748b;font-size:12px">CATEGORY</strong><br>
            <span class="category-badge ${faq.category}">${faq.category.charAt(0).toUpperCase() + faq.category.slice(1)}</span>
        </div>
        <div style="margin-bottom:15px">
            <strong style="color:#64748b;font-size:12px">QUESTION</strong><br>
            <p style="font-size:16px;color:#1e293b;margin-top:5px">${faq.question}</p>
        </div>
        <div style="margin-bottom:15px">
            <strong style="color:#64748b;font-size:12px">ANSWER</strong><br>
            <div style="background:#f8fafc;padding:15px;border-radius:10px;margin-top:5px;white-space:pre-wrap;line-height:1.6">${faq.answer}</div>
        </div>
        <div style="margin-bottom:15px">
            <strong style="color:#64748b;font-size:12px">KEYWORDS</strong><br>
            <p style="color:#64748b;margin-top:5px">${faq.keywords || 'None'}</p>
        </div>
        <div style="display:flex;gap:20px;font-size:13px;color:#64748b">
            <span>📊 Used ${faq.usage_count} times</span>
            <span>📅 Created ${new Date(faq.created_at).toLocaleDateString()}</span>
        </div>
    `;
    document.getElementById('previewContent').innerHTML = content;
    showModal('previewModal');
}

function insertVariable(variable) {
    const textarea = document.getElementById('faq_answer');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    textarea.focus();
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) hideModal(this.id);
    });
});
</script>
</body>
</html>
