<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$success_message = '';
$error_message = '';

// Handle link operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create_link') {
                $link_name = $_POST['link_name'];
                $destination_url = $_POST['destination_url'];
                $campaign = $_POST['campaign'] ?? '';
                
                // Generate unique short code
                $short_code = substr(md5(uniqid($affiliate_id, true)), 0, 8);
                
                $stmt = $pdo->prepare("
                    INSERT INTO affiliate_links (affiliate_id, name, destination_url, short_code, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $affiliate_id,
                    $link_name,
                    $destination_url,
                    $short_code
                ]);
                
                $success_message = 'Tracking link created successfully';
                
            } elseif ($action === 'delete_link') {
                $stmt = $pdo->prepare("
                    UPDATE affiliate_links 
                    SET is_active = 0
                    WHERE id = ? AND affiliate_id = ?
                ");
                $stmt->execute([$_POST['link_id'], $affiliate_id]);
                
                $success_message = 'Link deleted successfully';
            }
        } catch (PDOException $e) {
            error_log('Link operation error: ' . $e->getMessage());
            $error_message = 'Operation failed. Please try again.';
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Get all active links with performance data
    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            al.name as link_name,
            al.destination_url,
            al.short_code,
            al.created_at,
            al.click_count as total_clicks,
            al.conversion_count as conversions,
            0 as today_clicks,
            ROUND(al.conversion_count * 100.0 / NULLIF(al.click_count, 0), 2) as conversion_rate
        FROM affiliate_links al
        WHERE al.affiliate_id = ? AND al.is_active = 1
        ORDER BY al.created_at DESC
    ");
    $stmt->execute([$affiliate_id]);
    $links = $stmt->fetchAll();
    
    // Get overall statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT al.id) as total_links,
            SUM(al.click_count) as total_clicks,
            SUM(al.conversion_count) as total_conversions
        FROM affiliate_links al
        WHERE al.affiliate_id = ? AND al.is_active = 1
    ");
    $stmt->execute([$affiliate_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Link fetch error: ' . $e->getMessage());
    $links = [];
    $stats = [
        'total_links' => 0,
        'total_clicks' => 0,
        'total_conversions' => 0
    ];
}

$base_url = BASE_URL . '/l/';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Tracking Links</h3>
            <p class="text-muted">Create and manage your affiliate tracking links</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLinkModal">
                <i class="bi bi-plus-circle"></i> Create Link
            </button>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Links</div>
                        <h3 class="mb-0"><?= number_format($stats['total_links']) ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-link-45deg fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Clicks</div>
                        <h3 class="mb-0"><?= number_format($stats['total_clicks']) ?></h3>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-cursor fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Conversions</div>
                        <h3 class="mb-0"><?= number_format($stats['total_conversions']) ?></h3>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-check-circle fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Links Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Link Name</th>
                            <th>Short URL</th>
                            <th>Campaign</th>
                            <th>Clicks</th>
                            <th>Today</th>
                            <th>Conversions</th>
                            <th>Rate</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($links)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No tracking links yet. Create your first link to start earning!
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($links as $link): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($link['link_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($link['destination_url'], 0, 40)) ?>...</small>
                            </td>
                            <td>
                                <div class="input-group input-group-sm" style="max-width: 250px;">
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars($base_url . $link['short_code']) ?>" 
                                           id="link-<?= $link['id'] ?>" readonly>
                                    <button class="btn btn-outline-secondary copy-link" 
                                            data-link-id="<?= $link['id'] ?>"
                                            type="button">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted">-</span>
                            </td>
                            <td><?= number_format($link['total_clicks']) ?></td>
                            <td>
                                <?php if ($link['today_clicks'] > 0): ?>
                                <span class="badge bg-success"><?= $link['today_clicks'] ?></span>
                                <?php else: ?>
                                <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($link['conversions']) ?></td>
                            <td>
                                <?php if ($link['conversion_rate'] > 0): ?>
                                <span class="badge bg-success"><?= $link['conversion_rate'] ?>%</span>
                                <?php else: ?>
                                <span class="text-muted">0%</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($link['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary view-analytics" 
                                        data-link='<?= json_encode($link) ?>'>
                                    <i class="bi bi-graph-up"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-link" 
                                        data-id="<?= $link['id'] ?>"
                                        data-name="<?= htmlspecialchars($link['link_name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Link Modal -->
<div class="modal fade" id="createLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Tracking Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_link">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Link Name *</label>
                        <input type="text" class="form-control" name="link_name" 
                               placeholder="e.g., Homepage Banner" required>
                        <small class="text-muted">Give your link a memorable name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Destination URL *</label>
                        <input type="url" class="form-control" name="destination_url" 
                               placeholder="https://example.com" required>
                        <small class="text-muted">Where should this link redirect to?</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        A unique short link will be automatically generated for you.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link Analytics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col">
                        <h6 id="analytics-link-name"></h6>
                        <p class="text-muted mb-0" id="analytics-short-url"></p>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">Total Clicks</div>
                            <h4 class="mb-0" id="analytics-clicks">0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">Conversions</div>
                            <h4 class="mb-0" id="analytics-conversions">0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">Today</div>
                            <h4 class="mb-0" id="analytics-today">0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">Rate</div>
                            <h4 class="mb-0" id="analytics-rate">0%</h4>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Detailed analytics and charts coming soon!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_link">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="link_id" id="delete_link_id">
                    
                    <p>Are you sure you want to delete <strong id="delete_link_name"></strong>?</p>
                    <p class="text-muted">This link will stop working and cannot be recovered.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Copy link to clipboard
document.querySelectorAll('.copy-link').forEach(btn => {
    btn.addEventListener('click', function() {
        const linkId = this.dataset.linkId;
        const input = document.getElementById('link-' + linkId);
        input.select();
        document.execCommand('copy');
        
        const icon = this.querySelector('i');
        icon.classList.remove('bi-clipboard');
        icon.classList.add('bi-check');
        
        setTimeout(() => {
            icon.classList.remove('bi-check');
            icon.classList.add('bi-clipboard');
        }, 2000);
    });
});

// View analytics
document.querySelectorAll('.view-analytics').forEach(btn => {
    btn.addEventListener('click', function() {
        const link = JSON.parse(this.dataset.link);
        document.getElementById('analytics-link-name').textContent = link.link_name;
        document.getElementById('analytics-short-url').textContent = '<?= $base_url ?>' + link.short_code;
        document.getElementById('analytics-clicks').textContent = link.total_clicks;
        document.getElementById('analytics-conversions').textContent = link.conversions;
        document.getElementById('analytics-today').textContent = link.today_clicks;
        document.getElementById('analytics-rate').textContent = (link.conversion_rate || 0) + '%';
        new bootstrap.Modal(document.getElementById('analyticsModal')).show();
    });
});

// Delete link
document.querySelectorAll('.delete-link').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_link_id').value = this.dataset.id;
        document.getElementById('delete_link_name').textContent = this.dataset.name;
        new bootstrap.Modal(document.getElementById('deleteLinkModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
