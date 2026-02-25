<?php
declare(strict_types=1);

/**
 * ReviewFlow - Admin Blog Manager
 * CRUD for blog posts + Telegram channel/DM notifications.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TelegramBot.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors     = [];
$success    = '';

// ── Helpers ───────────────────────────────────────────────────────────────────

function blogSlugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function uniqueBlogSlug(PDO $pdo, string $base, int $excludeId = 0): string {
    $slug = $base;
    $i    = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function sendBlogTelegram(PDO $pdo, int $postId, string $title, string $excerpt, string $slug): void {
    if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) return;

    $postUrl = APP_URL . '/blog/index.php?slug=' . rawurlencode($slug);

    // Channel broadcast
    try {
        $tgBot   = new TelegramBot();
        $blogMsg  = "📝 <b>New Blog Post!</b>\n\n";
        $blogMsg .= "📖 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $blogMsg .= htmlspecialchars(substr(strip_tags($excerpt), 0, 200)) . "...\n\n";
        $blogMsg .= "👉 <a href=\"" . $postUrl . "\">Read Full Article</a>\n\n";
        $blogMsg .= "#ReviewFlow #EarnMoney #Tips";
        $tgBot->sendMessage($blogMsg);
    } catch (Exception $e) {
        error_log("Blog Telegram channel error: " . $e->getMessage());
    }

    // Personal DM to all connected users
    try {
        $connectedUsers = $pdo->query(
            "SELECT telegram_chat_id FROM users WHERE telegram_chat_id IS NOT NULL AND telegram_chat_id != ''"
        );
        $tgBot = new TelegramBot();
        while ($u = $connectedUsers->fetch()) {
            try {
                $dmMsg  = "📖 <b>New Article for You!</b>\n\n";
                $dmMsg .= "💎 <b>" . htmlspecialchars($title) . "</b>\n\n";
                $dmMsg .= htmlspecialchars(substr(strip_tags($excerpt), 0, 150)) . "\n\n";
                $dmMsg .= "👉 <a href=\"" . $postUrl . "\">Read Now</a>";
                $tgBot->sendPersonalMessage($u['telegram_chat_id'], $dmMsg);
            } catch (Exception $e) {
                continue;
            }
        }
    } catch (Exception $e) {
        error_log("Blog DM broadcast error: " . $e->getMessage());
    }

    // Mark notified
    try {
        $pdo->prepare("UPDATE blog_posts SET telegram_notified = 1 WHERE id = ?")
            ->execute([$postId]);
    } catch (PDOException $e) {
        error_log("Blog mark telegram_notified error: " . $e->getMessage());
    }
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ── Handle Add ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blog'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title    = sanitizeInput($_POST['title']    ?? '');
        $content  = $_POST['content'] ?? '';   // Allow HTML
        $excerpt  = sanitizeInput($_POST['excerpt']  ?? '');
        $category = sanitizeInput($_POST['category'] ?? 'general');
        $tags     = sanitizeInput($_POST['tags']     ?? '');
        $status   = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';
        $read_min = max(1, (int)($_POST['read_time_minutes'] ?? 3));
        $auto_gen = isset($_POST['is_auto_generated']) ? 1 : 0;
        $notify   = isset($_POST['notify_telegram']);

        if (empty($title))   $errors[] = 'Title is required.';
        if (empty($content)) $errors[] = 'Content is required.';

        if (empty($errors)) {
            $slug = uniqueBlogSlug($pdo, blogSlugify($title));
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO blog_posts
                        (title,slug,excerpt,content,category,tags,author,status,is_auto_generated,read_time_minutes,published_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
                );
                $stmt->execute([$title,$slug,$excerpt,$content,$category,$tags,$admin_name,$status,$auto_gen,$read_min]);
                $newId = (int)$pdo->lastInsertId();

                logActivity("Added blog post: $title");
                $success = "Blog post added successfully! Slug: <code>$slug</code>";

                if ($notify && $status === 'published') {
                    sendBlogTelegram($pdo, $newId, $title, $excerpt, $slug);
                    $success .= ' Telegram notifications sent.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to add blog post.';
                error_log('Blog Add Error: ' . $e->getMessage());
            }
        }
    }
}

// ── Handle Edit ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_blog'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $edit_id  = (int)($_POST['blog_id'] ?? 0);
        $title    = sanitizeInput($_POST['title']    ?? '');
        $content  = $_POST['content'] ?? '';
        $excerpt  = sanitizeInput($_POST['excerpt']  ?? '');
        $category = sanitizeInput($_POST['category'] ?? 'general');
        $tags     = sanitizeInput($_POST['tags']     ?? '');
        $status   = in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft';
        $read_min = max(1, (int)($_POST['read_time_minutes'] ?? 3));
        $notify   = isset($_POST['notify_telegram']);

        if (empty($title))   $errors[] = 'Title is required.';
        if (empty($content)) $errors[] = 'Content is required.';
        if ($edit_id <= 0)   $errors[] = 'Invalid post ID.';

        if (empty($errors)) {
            try {
                // Keep existing slug if title unchanged
                $existingSlug = '';
                $s = $pdo->prepare("SELECT slug FROM blog_posts WHERE id = ?");
                $s->execute([$edit_id]);
                $row = $s->fetch();
                if ($row) {
                    $newBase = blogSlugify($title);
                    $existingSlug = (blogSlugify($row['slug']) === $newBase)
                        ? $row['slug']
                        : uniqueBlogSlug($pdo, $newBase, $edit_id);
                }

                $stmt = $pdo->prepare(
                    "UPDATE blog_posts
                        SET title=?,slug=?,excerpt=?,content=?,category=?,tags=?,status=?,read_time_minutes=?
                      WHERE id=?"
                );
                $stmt->execute([$title,$existingSlug,$excerpt,$content,$category,$tags,$status,$read_min,$edit_id]);

                logActivity("Updated blog post #$edit_id: $title");
                $success = 'Blog post updated successfully!';

                if ($notify && $status === 'published') {
                    sendBlogTelegram($pdo, $edit_id, $title, $excerpt, $existingSlug);
                    $success .= ' Telegram notifications sent.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to update blog post.';
                error_log('Blog Edit Error: ' . $e->getMessage());
            }
        }
    }
}

// ── Handle Delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blog'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $del_id = (int)($_POST['blog_id'] ?? 0);
        if ($del_id > 0) {
            try {
                $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$del_id]);
                logActivity("Deleted blog post #$del_id");
                $success = 'Blog post deleted.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to delete blog post.';
            }
        }
    }
}

// ── Handle Toggle Status ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $toggle_id = (int)($_POST['blog_id'] ?? 0);
        if ($toggle_id > 0) {
            try {
                $pdo->prepare(
                    "UPDATE blog_posts SET status = CASE WHEN status='published' THEN 'draft' ELSE 'published' END WHERE id=?"
                )->execute([$toggle_id]);
                $success = 'Blog post status toggled.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to toggle status.';
            }
        }
    }
}

// ── Handle Send Telegram ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_telegram'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $tg_id = (int)($_POST['blog_id'] ?? 0);
        if ($tg_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
                $stmt->execute([$tg_id]);
                $tgPost = $stmt->fetch();
                if ($tgPost) {
                    sendBlogTelegram($pdo, $tg_id, $tgPost['title'], $tgPost['excerpt'] ?? '', $tgPost['slug']);
                    $success = 'Telegram notifications sent for: ' . htmlspecialchars($tgPost['title']);
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to fetch blog post for Telegram.';
            }
        }
    }
}

// ── Handle Auto-Generate ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_generate'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $auto_title   = '🚀 ReviewFlow Platform Update — ' . date('F Y');
        $auto_excerpt = 'Discover the latest features and improvements on ReviewFlow. Stay updated to earn more!';
        $auto_content = '<h2>What\'s New on ReviewFlow 🚀</h2>'
            . '<p>We keep improving ReviewFlow to help you earn more. Here are the latest platform highlights:</p>'
            . '<ul>'
            . '<li>✅ Faster task approval system</li>'
            . '<li>💰 New withdrawal options available</li>'
            . '<li>📊 Improved earnings dashboard</li>'
            . '<li>🔔 Enhanced Telegram notifications</li>'
            . '<li>🎯 More tasks available daily</li>'
            . '</ul>'
            . '<div class="tip-box"><strong>💡 Pro Tip:</strong> Check your dashboard every morning for the newest tasks!</div>';

        $auto_slug = uniqueBlogSlug($pdo, blogSlugify($auto_title));
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO blog_posts
                    (title,slug,excerpt,content,category,tags,author,status,is_auto_generated,read_time_minutes,published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            $stmt->execute([
                $auto_title, $auto_slug, $auto_excerpt, $auto_content,
                'updates', 'platform,updates,features,new',
                $admin_name, 'published', 1, 2
            ]);
            logActivity("Auto-generated blog post: $auto_title");
            $success = '🤖 Blog post auto-generated: <strong>' . htmlspecialchars($auto_title) . '</strong>';
        } catch (PDOException $e) {
            $errors[] = 'Auto-generate failed.';
            error_log('Blog Auto-gen Error: ' . $e->getMessage());
        }
    }
}

// ── Fetch list ────────────────────────────────────────────────────────────────
$filter_status   = $_GET['status']   ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search          = sanitizeInput($_GET['search'] ?? '');
$page            = max(1, (int)($_GET['page'] ?? 1));
$per_page        = 20;
$offset          = ($page - 1) * $per_page;

$where  = '1=1';
$params = [];

if ($filter_status !== 'all') {
    $where   .= " AND status = ?";
    $params[] = $filter_status;
}
if ($filter_category !== 'all') {
    $where   .= " AND category = ?";
    $params[] = $filter_category;
}
if ($search !== '') {
    $where   .= " AND (title LIKE ? OR excerpt LIKE ? OR tags LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$blogs       = [];
$total       = 0;
$total_pages = 0;

try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $where");
    $cnt->execute($params);
    $total       = (int)$cnt->fetchColumn();
    $total_pages = (int)ceil($total / $per_page);

    $stmt = $pdo->prepare(
        "SELECT id, title, slug, category, status, view_count, read_time_minutes, is_auto_generated, telegram_notified, published_at
           FROM blog_posts
          WHERE $where
          ORDER BY published_at DESC
          LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $blogs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Blog list error: ' . $e->getMessage());
}

// Fetch for edit modal
$edit_post = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $s = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $s->execute([$edit_id]);
        $edit_post = $s->fetch();
    } catch (PDOException $e) {}
}

$current_page = 'blog-manage';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Manager — Admin — <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,0.75);text-decoration:none;border-radius:0 25px 25px 0;margin-right:15px;transition:all 0.2s;font-size:14px}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.15);color:#fff}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.08);margin:10px 20px}
        .menu-section-label{padding:8px 20px;font-size:11px;text-transform:uppercase;color:rgba(255,255,255,0.4);letter-spacing:1px;cursor:default}
        .badge{background:#e74c3c;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto}
        .main{padding:25px;overflow-x:auto}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px}
        .page-header h1{font-size:22px;color:#333;font-weight:700}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.2s}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{opacity:0.9}
        .btn-success{background:#27ae60;color:#fff}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-warning{background:#f39c12;color:#fff}
        .btn-info{background:#0088cc;color:#fff}
        .btn-secondary{background:#6c757d;color:#fff}
        .btn-sm{padding:6px 11px;font-size:12px}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:15px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .card{background:#fff;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.07);padding:20px;margin-bottom:20px}
        .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:15px}
        .filters input,.filters select{padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px 12px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #eee}
        table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
        table tr:hover td{background:#fafafa}
        .status-badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .status-published{background:#d4edda;color:#155724}
        .status-draft{background:#fff3cd;color:#856404}
        .status-archived{background:#f8f9fa;color:#6c757d}
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px;display:none}
        .modal-overlay.show{display:flex}
        .modal{background:#fff;border-radius:15px;padding:25px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto}
        .modal h2{font-size:20px;font-weight:700;color:#333;margin-bottom:20px}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px}
        .form-control{width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;font-family:inherit;transition:border-color 0.2s}
        .form-control:focus{outline:none;border-color:#667eea}
        textarea.form-control{resize:vertical;min-height:200px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        .form-check{display:flex;align-items:center;gap:8px;font-size:13px;color:#555;margin-top:5px}
        .form-check input{width:16px;height:16px}
        .pagination{display:flex;gap:6px;flex-wrap:wrap;margin-top:15px}
        .pagination a{padding:7px 12px;background:#fff;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#333;text-decoration:none}
        .pagination a.active{background:#667eea;color:#fff;border-color:#667eea}
        @media(max-width:900px){.admin-layout{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <div class="page-header">
            <h1><i class="bi bi-journal-richtext"></i> Blog Manager</h1>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button type="submit" name="auto_generate" class="btn btn-info">🤖 Auto Generate Blog</button>
                </form>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')">
                    <i class="bi bi-plus-lg"></i> New Blog Post
                </button>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px">
            <?php
            $stats = ['total'=>0,'published'=>0,'draft'=>0,'views'=>0];
            try {
                $s = $pdo->query("SELECT status, COUNT(*) as c, SUM(view_count) as v FROM blog_posts GROUP BY status");
                foreach ($s->fetchAll() as $row) {
                    $stats['total'] += (int)$row['c'];
                    $stats[$row['status']] = (int)$row['c'];
                    $stats['views'] += (int)$row['v'];
                }
            } catch (PDOException $e) {}
            ?>
            <?php foreach ([['📝','Total Posts',$stats['total'],'#667eea'],['✅','Published',$stats['published'],'#27ae60'],['📄','Drafts',$stats['draft'],'#f39c12'],['👁️','Total Views',$stats['views'],'#0088cc']] as [$icon,$label,$val,$color]): ?>
            <div style="background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 15px rgba(0,0,0,0.07);text-align:center">
                <div style="font-size:28px;margin-bottom:5px"><?php echo $icon; ?></div>
                <div style="font-size:22px;font-weight:700;color:<?php echo $color; ?>"><?php echo number_format($val); ?></div>
                <div style="font-size:12px;color:#888"><?php echo $label; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="card">
            <form class="filters" method="get">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="max-width:200px">
                <select name="status" class="form-control">
                    <option value="all" <?php echo $filter_status==='all'?'selected':''; ?>>All Status</option>
                    <option value="published" <?php echo $filter_status==='published'?'selected':''; ?>>Published</option>
                    <option value="draft" <?php echo $filter_status==='draft'?'selected':''; ?>>Draft</option>
                    <option value="archived" <?php echo $filter_status==='archived'?'selected':''; ?>>Archived</option>
                </select>
                <select name="category" class="form-control">
                    <option value="all" <?php echo $filter_category==='all'?'selected':''; ?>>All Categories</option>
                    <?php
                    $cats = [];
                    try {
                        $cats = $pdo->query("SELECT DISTINCT category FROM blog_posts ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
                    } catch (PDOException $e) {}
                    foreach ($cats as $cat):
                    ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category===$cat?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($cat)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <a href="<?php echo ADMIN_URL; ?>/blog-manage.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>

            <!-- Table -->
            <?php if (empty($blogs)): ?>
                <p style="text-align:center;color:#999;padding:30px">No blog posts found.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blogs as $b): ?>
                    <tr>
                        <td>#<?php echo $b['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($b['title']); ?></strong>
                            <?php if ($b['is_auto_generated']): ?><span style="font-size:10px;background:#e8f4fd;color:#0088cc;padding:2px 6px;border-radius:8px;margin-left:4px">🤖 Auto</span><?php endif; ?>
                            <?php if ($b['telegram_notified']): ?><span style="font-size:10px;background:#e8f4fd;color:#0088cc;padding:2px 6px;border-radius:8px;margin-left:4px">📲 Sent</span><?php endif; ?>
                            <br><small style="color:#999"><?php echo htmlspecialchars($b['slug']); ?></small>
                        </td>
                        <td><span style="background:#f0f0f0;padding:3px 8px;border-radius:8px;font-size:11px"><?php echo htmlspecialchars(ucfirst($b['category'])); ?></span></td>
                        <td><span class="status-badge status-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                        <td>👁️ <?php echo number_format((int)$b['view_count']); ?></td>
                        <td><?php echo date('d M Y', strtotime($b['published_at'])); ?></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap">
                                <a href="<?php echo APP_URL; ?>/blog/index.php?slug=<?php echo rawurlencode($b['slug']); ?>" target="_blank" class="btn btn-sm" style="background:#e8f4fd;color:#0088cc" title="View">👁️</a>
                                <button class="btn btn-warning btn-sm" onclick="openEdit(<?php echo $b['id']; ?>)" title="Edit">✏️</button>
                                <form method="post" style="display:inline" onsubmit="return confirm('Toggle status?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="blog_id"   value="<?php echo $b['id']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-secondary btn-sm" title="Toggle">🔄</button>
                                </form>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="blog_id"   value="<?php echo $b['id']; ?>">
                                    <button type="submit" name="send_telegram" class="btn btn-info btn-sm" title="Send Telegram">📲</button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this post permanently?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="blog_id"   value="<?php echo $b['id']; ?>">
                                    <button type="submit" name="delete_blog" class="btn btn-danger btn-sm" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search); ?>"
                   class="<?php echo $page === $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Add Modal ──────────────────────────────────────────────────────────────-->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2>➕ New Blog Post</h2>
            <button onclick="document.getElementById('addModal').classList.remove('show')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#999">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" placeholder="Blog post title" required>
            </div>
            <div class="form-group">
                <label>Excerpt</label>
                <textarea name="excerpt" class="form-control" rows="2" placeholder="Short description for listing page"></textarea>
            </div>
            <div class="form-group">
                <label>Content * (HTML allowed)</label>
                <textarea name="content" class="form-control" rows="10" placeholder="Full blog content..." required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <?php foreach (['general','getting-started','tutorial','referral','payments','features','security','gamification','motivation','support','updates'] as $c): ?>
                        <option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="tag1,tag2,tag3">
                </div>
                <div class="form-group">
                    <label>Read Time (minutes)</label>
                    <input type="number" name="read_time_minutes" class="form-control" value="3" min="1" max="60">
                </div>
            </div>
            <div class="form-check" style="margin-bottom:12px">
                <input type="checkbox" name="notify_telegram" id="nt_add">
                <label for="nt_add">📲 Send Telegram notification (channel + all users) when published</label>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('addModal').classList.remove('show')" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="add_blog" class="btn btn-primary">💾 Save Post</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Edit Modal ─────────────────────────────────────────────────────────────-->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h2>✏️ Edit Blog Post</h2>
            <button onclick="document.getElementById('editModal').classList.remove('show')" style="background:none;border:none;font-size:22px;cursor:pointer;color:#999">&times;</button>
        </div>
        <form method="post" id="editForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="blog_id"    id="edit_blog_id">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Excerpt</label>
                <textarea name="excerpt" id="edit_excerpt" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Content * (HTML allowed)</label>
                <textarea name="content" id="edit_content" class="form-control" rows="10" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="edit_category" class="form-control">
                        <?php foreach (['general','getting-started','tutorial','referral','payments','features','security','gamification','motivation','support','updates'] as $c): ?>
                        <option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tags</label>
                    <input type="text" name="tags" id="edit_tags" class="form-control">
                </div>
                <div class="form-group">
                    <label>Read Time (minutes)</label>
                    <input type="number" name="read_time_minutes" id="edit_read_time" class="form-control" min="1" max="60">
                </div>
            </div>
            <div class="form-check" style="margin-bottom:12px">
                <input type="checkbox" name="notify_telegram" id="nt_edit">
                <label for="nt_edit">📲 Send Telegram notification (channel + all users)</label>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('editModal').classList.remove('show')" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="edit_blog" class="btn btn-primary">💾 Update Post</button>
            </div>
        </form>
    </div>
</div>

<script>
// Blog data for edit modal
var blogData = <?php
    $allBlogs = [];
    try {
        $rows = $pdo->query("SELECT id, title, slug, excerpt, content, category, tags, status, read_time_minutes FROM blog_posts ORDER BY id")->fetchAll();
        foreach ($rows as $r) {
            $allBlogs[$r['id']] = $r;
        }
    } catch (PDOException $e) {}
    echo json_encode($allBlogs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>;

function openEdit(id) {
    var b = blogData[id];
    if (!b) return alert('Post data not found.');
    document.getElementById('edit_blog_id').value  = b.id;
    document.getElementById('edit_title').value     = b.title;
    document.getElementById('edit_excerpt').value   = b.excerpt || '';
    document.getElementById('edit_content').value   = b.content;
    document.getElementById('edit_tags').value      = b.tags || '';
    document.getElementById('edit_read_time').value = b.read_time_minutes;
    var catSel = document.getElementById('edit_category');
    for (var i = 0; i < catSel.options.length; i++) {
        catSel.options[i].selected = (catSel.options[i].value === b.category);
    }
    var stSel = document.getElementById('edit_status');
    for (var i = 0; i < stSel.options.length; i++) {
        stSel.options[i].selected = (stSel.options[i].value === b.status);
    }
    document.getElementById('editModal').classList.add('show');
}

// Close modals on outside click
['addModal','editModal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});
</script>
</body>
</html>
