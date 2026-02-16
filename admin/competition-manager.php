<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/competition-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';

// Handle create competition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_competition'])) {
    $data = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'competition_type' => sanitizeInput($_POST['competition_type'] ?? ''),
        'start_date' => $_POST['start_date'] ?? '',
        'end_date' => $_POST['end_date'] ?? '',
        'prizes' => json_decode($_POST['prizes'] ?? '{}', true),
        'prize_pool' => floatval($_POST['prize_pool'] ?? 0),
        'created_by' => $_SESSION['admin_id'] ?? 1
    ];
    
    if (createCompetition($pdo, $data)) {
        $message = "Competition created successfully!";
    }
}

// Update statuses
updateCompetitionStatuses($pdo);

// Get competitions
$competitions = $pdo->query("
    SELECT * FROM competitions 
    ORDER BY start_date DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'competition-manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competition Manager - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .main-content{padding:25px;overflow-x:hidden}
    </style>
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-trophy"></i> Competition Manager</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Create Competition -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Create New Competition</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Competition Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type *</label>
                            <select class="form-select" name="competition_type" required>
                                <option value="tasks">Most Tasks Completed</option>
                                <option value="earnings">Highest Earnings</option>
                                <option value="quality">Best Quality Score</option>
                                <option value="referrals">Most Referrals</option>
                                <option value="speed">Fastest Completion</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="datetime-local" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="datetime-local" class="form-control" name="end_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prize Pool (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="prize_pool" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prize Distribution (JSON)</label>
                            <input type="text" class="form-control" name="prizes" value='{"1":1000,"2":500,"3":250}'>
                            <small class="text-muted">Format: {"rank":amount}</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_competition" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Create Competition
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Competitions List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> All Competitions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Prize Pool</th>
                                <th>Participants</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competitions as $comp): ?>
                            <?php
                            // Get participant count
                            $part_stmt = $pdo->prepare("SELECT COUNT(*) FROM competition_participants WHERE competition_id = ?");
                            $part_stmt->execute([$comp['id']]);
                            $participant_count = $part_stmt->fetchColumn();
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comp['name']); ?></td>
                                <td><?php echo ucfirst($comp['competition_type']); ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($comp['start_date'])); ?> - 
                                    <?php echo date('M d', strtotime($comp['end_date'])); ?>
                                </td>
                                <td>₹<?php echo number_format($comp['prize_pool'], 2); ?></td>
                                <td><?php echo $participant_count; ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'upcoming' => 'info',
                                        'active' => 'success',
                                        'ended' => 'secondary',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$comp['status']]; ?>">
                                        <?php echo ucfirst($comp['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=view&id=<?php echo $comp['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
