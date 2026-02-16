<?php
/**
 * AI-Powered Review Quality Dashboard
 * Admin page to monitor and manage review quality scores
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/ai-quality-functions.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$page_title = 'Review Quality Dashboard';

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $qualityScoreId = (int)($_POST['quality_score_id'] ?? 0);
    $adminId = $_SESSION['admin_id'] ?? 1;
    
    if ($action === 'approve' && $qualityScoreId > 0) {
        markReviewAsReviewed($qualityScoreId, $adminId, true);
        $success_message = 'Review approved successfully';
    } elseif ($action === 'reject' && $qualityScoreId > 0) {
        markReviewAsReviewed($qualityScoreId, $adminId, false);
        $success_message = 'Review rejected successfully';
    }
}

// Get quality statistics
$stats = getQualityStatistics();

// Get flagged reviews
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$flaggedReviews = getFlaggedReviews($limit, $offset);

// Get total flagged count for pagination
$totalFlagged = $stats['flagged_reviews'];
$totalPages = ceil($totalFlagged / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ReviewFlow Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .review-card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .quality-score {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .quality-high { background: #10b981; color: white; }
        .quality-medium { background: #f59e0b; color: white; }
        .quality-low { background: #ef4444; color: white; }
        .flags-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        .flag-badge {
            padding: 5px 10px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 4px;
            font-size: 12px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a.active {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_reviews']); ?></div>
                <div class="stat-label">Total Reviews Analyzed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['flagged_reviews']); ?></div>
                <div class="stat-label">Flagged for Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['avg_quality_score'], 1); ?></div>
                <div class="stat-label">Average Quality Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['high_quality']); ?></div>
                <div class="stat-label">High Quality (≥70)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['medium_quality']); ?></div>
                <div class="stat-label">Medium Quality (40-69)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['low_quality']); ?></div>
                <div class="stat-label">Low Quality (<40)</div>
            </div>
        </div>
        
        <!-- Flagged Reviews -->
        <h2>Flagged Reviews for Manual Review</h2>
        
        <?php if (empty($flaggedReviews)): ?>
            <p>No flagged reviews at this time.</p>
        <?php else: ?>
            <?php foreach ($flaggedReviews as $review): ?>
                <div class="review-card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3><?php echo htmlspecialchars($review['task_title']); ?></h3>
                            <p><strong>User:</strong> <?php echo htmlspecialchars($review['user_name']); ?></p>
                        </div>
                        <div>
                            <span class="quality-score <?php 
                                echo $review['quality_score'] >= 70 ? 'quality-high' : 
                                     ($review['quality_score'] >= 40 ? 'quality-medium' : 'quality-low'); 
                            ?>">
                                Score: <?php echo $review['quality_score']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <strong>Review Text:</strong>
                        <p><?php echo nl2br(htmlspecialchars($review['review_text'] ?? 'No text')); ?></p>
                    </div>
                    
                    <div>
                        <strong>AI Flags:</strong>
                        <div class="flags-list">
                            <?php if (!empty($review['ai_flags'])): ?>
                                <?php foreach ($review['ai_flags'] as $flag => $value): ?>
                                    <?php if ($value): ?>
                                        <span class="flag-badge"><?php echo ucwords(str_replace('_', ' ', $flag)); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span>No specific flags</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; padding: 15px; background: #f9fafb; border-radius: 4px;">
                        <div>
                            <strong>Plagiarism Score:</strong>
                            <div><?php echo number_format($review['plagiarism_score'], 2); ?>%</div>
                        </div>
                        <div>
                            <strong>Spam Probability:</strong>
                            <div><?php echo number_format($review['spam_probability'], 2); ?>%</div>
                        </div>
                        <div>
                            <strong>Submitted:</strong>
                            <div><?php echo date('M d, Y H:i', strtotime($review['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($review['screenshot']): ?>
                        <div style="margin-top: 15px;">
                            <strong>Screenshot:</strong><br>
                            <img src="../<?php echo htmlspecialchars($review['screenshot']); ?>" alt="Proof" style="max-width: 300px; margin-top: 10px;">
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="action-buttons">
                        <input type="hidden" name="quality_score_id" value="<?php echo $review['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-approve">Approve Review</button>
                        <button type="submit" name="action" value="reject" class="btn btn-reject">Reject Review</button>
                    </form>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
