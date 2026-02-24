<?php
declare(strict_types=1);

/**
 * ReviewFlow - Public Blog Page
 * Lists all published blog posts and shows individual posts.
 * No login required — public page for SEO.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$category_filter = trim($_GET['category'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$post = null;
$related_posts = [];

// ── Individual post view ──────────────────────────────────────────────────────
if ($slug !== '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Blog post fetch error: ' . $e->getMessage());
    }

    if ($post) {
        // Increment view count
        try {
            $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")
                ->execute([$post['id']]);
        } catch (PDOException $e) {
            // Non-fatal
        }

        // Related posts (same category, exclude current)
        try {
            $stmt = $pdo->prepare(
                "SELECT id, title, slug, excerpt, category, read_time_minutes, published_at, view_count
                   FROM blog_posts
                  WHERE status = 'published' AND category = ? AND id != ?
                  ORDER BY published_at DESC
                  LIMIT 3"
            );
            $stmt->execute([$post['category'], $post['id']]);
            $related_posts = $stmt->fetchAll();
        } catch (PDOException $e) {
            $related_posts = [];
        }

        $meta_title       = $post['meta_title']       ?: htmlspecialchars($post['title']) . ' — ' . APP_NAME . ' Blog';
        $meta_description = $post['meta_description'] ?: htmlspecialchars(substr(strip_tags($post['excerpt'] ?? ''), 0, 160));
        $page_title       = htmlspecialchars($post['title']);
    } else {
        // 404 for invalid slug
        http_response_code(404);
        $meta_title = 'Post Not Found — ' . APP_NAME . ' Blog';
        $meta_description = 'The blog post you are looking for does not exist.';
        $page_title = 'Post Not Found';
    }
} else {
    // ── Blog listing view ─────────────────────────────────────────────────────
    $meta_title       = APP_NAME . ' Blog — Tips, Guides & Updates';
    $meta_description = 'Learn how to earn more on ' . APP_NAME . '. Step-by-step guides, tips from top earners, payment guides, and platform updates.';
    $page_title       = '💎 Tips, Guides & Updates';

    $where  = "status = 'published'";
    $params = [];

    if ($category_filter !== 'all') {
        $where   .= " AND category = ?";
        $params[] = $category_filter;
    }

    if ($search !== '') {
        $where   .= " AND (title LIKE ? OR excerpt LIKE ? OR tags LIKE ?)";
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $posts = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT id, title, slug, excerpt, category, tags, read_time_minutes, view_count, published_at
               FROM blog_posts
              WHERE $where
              ORDER BY published_at DESC"
        );
        $stmt->execute($params);
        $posts = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Blog listing error: ' . $e->getMessage());
    }

    // Available categories for filter tabs
    $categories = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM blog_posts WHERE status = 'published' ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $categories = [];
    }
}

$blog_url    = APP_URL . '/blog/';
$current_url = APP_URL . '/blog/index.php' . ($slug !== '' ? '?slug=' . rawurlencode($slug) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">

    <!-- Open Graph -->
    <meta property="og:title"       content="<?php echo $meta_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type"        content="<?php echo $post ? 'article' : 'website'; ?>">
    <meta property="og:url"         content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:site_name"   content="<?php echo htmlspecialchars(APP_NAME); ?>">

    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;color:#333;min-height:100vh}

        /* Header */
        .blog-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:40px 20px;text-align:center}
        .blog-header h1{font-size:28px;font-weight:800;margin-bottom:8px}
        .blog-header p{font-size:15px;opacity:0.9;max-width:600px;margin:0 auto}
        .header-back{display:inline-flex;align-items:center;gap:6px;color:rgba(255,255,255,0.85);text-decoration:none;font-size:13px;margin-bottom:15px;transition:color 0.2s}
        .header-back:hover{color:#fff}

        /* Search */
        .search-bar{max-width:500px;margin:20px auto 0;display:flex;gap:10px}
        .search-bar input{flex:1;padding:12px 18px;border:none;border-radius:25px;font-size:14px;outline:none}
        .search-bar button{padding:12px 20px;background:#f7971e;color:#fff;border:none;border-radius:25px;font-weight:700;cursor:pointer;font-size:14px;transition:background 0.2s}
        .search-bar button:hover{background:#e68a1a}

        /* Container */
        .container{max-width:1100px;margin:0 auto;padding:30px 20px}

        /* Category Tabs */
        .category-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:25px}
        .cat-tab{padding:7px 16px;border-radius:20px;background:#fff;color:#666;text-decoration:none;font-size:13px;font-weight:500;border:2px solid #eee;transition:all 0.2s}
        .cat-tab:hover,.cat-tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent}

        /* Blog Grid */
        .blog-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
        @media(max-width:700px){.blog-grid{grid-template-columns:1fr}}

        /* Blog Card */
        .blog-card{background:#fff;border-radius:15px;padding:22px;box-shadow:0 3px 15px rgba(0,0,0,0.07);transition:transform 0.2s,box-shadow 0.2s;display:flex;flex-direction:column}
        .blog-card:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,0.12)}
        .blog-card a{text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1}
        .card-category{display:inline-block;padding:4px 12px;border-radius:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;margin-bottom:12px}
        .card-title{font-size:17px;font-weight:700;color:#222;margin-bottom:8px;line-height:1.4}
        .card-excerpt{font-size:13px;color:#666;line-height:1.6;flex:1;margin-bottom:12px}
        .card-meta{display:flex;gap:12px;font-size:11px;color:#999;flex-wrap:wrap}
        .card-meta span{display:flex;align-items:center;gap:3px}
        .card-read-more{margin-top:14px;color:#667eea;font-size:13px;font-weight:600}

        /* Individual Post */
        .post-container{max-width:780px;margin:0 auto}
        .post-header{background:#fff;border-radius:15px;padding:30px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,0.07)}
        .post-category-badge{display:inline-block;padding:5px 14px;border-radius:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:14px}
        .post-title{font-size:26px;font-weight:800;color:#222;line-height:1.3;margin-bottom:12px}
        .post-meta{display:flex;gap:15px;font-size:12px;color:#888;flex-wrap:wrap}
        .post-content{background:#fff;border-radius:15px;padding:30px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,0.07);line-height:1.8;font-size:15px;color:#444}
        .post-content h2{font-size:22px;color:#222;margin:24px 0 12px;font-weight:700}
        .post-content h3{font-size:18px;color:#333;margin:20px 0 10px;font-weight:600}
        .post-content h4{font-size:16px;color:#444;margin:16px 0 8px;font-weight:600}
        .post-content p{margin-bottom:14px}
        .post-content ul,.post-content ol{margin:10px 0 14px 22px}
        .post-content li{margin-bottom:6px}
        .post-content table{width:100%;border-collapse:collapse;margin:16px 0;font-size:14px}
        .post-content table th{background:#667eea;color:#fff;padding:10px 12px;text-align:left}
        .post-content table td{padding:9px 12px;border-bottom:1px solid #eee}
        .post-content table tr:nth-child(even) td{background:#f9f9f9}
        .tip-box{background:linear-gradient(135deg,#fff9c4,#fff3e0);border-left:4px solid #f7971e;padding:15px 20px;border-radius:0 10px 10px 0;margin:20px 0;font-size:14px}
        .post-content strong{color:#222}

        /* Share buttons */
        .share-section{background:#fff;border-radius:15px;padding:20px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,0.07)}
        .share-section h4{font-size:14px;color:#666;margin-bottom:12px}
        .share-btns{display:flex;gap:10px;flex-wrap:wrap}
        .share-btn{padding:10px 18px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:600;color:#fff;display:inline-flex;align-items:center;gap:6px;transition:opacity 0.2s}
        .share-btn:hover{opacity:0.85}
        .share-btn.whatsapp{background:#25d366}
        .share-btn.telegram{background:#0088cc}
        .share-btn.copy{background:#667eea;cursor:pointer;border:none}

        /* Related Posts */
        .related-section{background:#fff;border-radius:15px;padding:25px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,0.07)}
        .related-section h3{font-size:18px;font-weight:700;color:#333;margin-bottom:15px}
        .related-list{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        @media(max-width:600px){.related-list{grid-template-columns:1fr}}
        .related-card{border:1px solid #eee;border-radius:10px;padding:14px;text-decoration:none;color:#333;transition:all 0.2s}
        .related-card:hover{border-color:#667eea;box-shadow:0 3px 10px rgba(102,126,234,0.15)}
        .related-card .rc-title{font-size:13px;font-weight:600;color:#333;margin-bottom:6px;line-height:1.4}
        .related-card .rc-meta{font-size:11px;color:#999}

        /* Footer */
        .blog-footer{background:#2c3e50;color:#ccc;padding:30px 20px;text-align:center;margin-top:30px}
        .blog-footer a{color:#667eea;text-decoration:none}
        .blog-footer a:hover{text-decoration:underline}

        /* Empty state */
        .empty-state{text-align:center;padding:50px 20px;color:#999}
        .empty-state .icon{font-size:48px;margin-bottom:15px}
        .empty-state p{font-size:15px}

        /* Back link */
        .back-link{display:inline-flex;align-items:center;gap:6px;color:#667eea;text-decoration:none;font-size:13px;font-weight:600;margin-bottom:20px;transition:color 0.2s}
        .back-link:hover{color:#764ba2}
    </style>
</head>
<body>

<!-- Header -->
<div class="blog-header">
    <a href="<?php echo htmlspecialchars(APP_URL); ?>/" class="header-back">← Back to <?php echo htmlspecialchars(APP_NAME); ?></a>
    <?php if ($post): ?>
        <h1><?php echo htmlspecialchars(APP_NAME); ?> Blog</h1>
        <p>Tips, guides &amp; platform updates</p>
    <?php else: ?>
        <h1><?php echo htmlspecialchars(APP_NAME); ?> Blog</h1>
        <p><?php echo $page_title; ?></p>
        <form class="search-bar" method="get" action="<?php echo htmlspecialchars($blog_url . 'index.php'); ?>">
            <?php if ($category_filter !== 'all'): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search articles..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">🔍 Search</button>
        </form>
    <?php endif; ?>
</div>

<div class="container">

<?php if ($post): ?>
    <!-- ── Individual Post ─────────────────────────────────────────────────── -->
    <a href="<?php echo htmlspecialchars($blog_url); ?>" class="back-link">← Back to Blog</a>

    <div class="post-container">
        <div class="post-header">
            <div class="post-category-badge"><?php echo htmlspecialchars(ucfirst($post['category'])); ?></div>
            <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">
                <span>✍️ <?php echo htmlspecialchars($post['author']); ?></span>
                <span>📅 <?php echo date('d M Y', strtotime($post['published_at'])); ?></span>
                <span>⏱️ <?php echo (int)$post['read_time_minutes']; ?> min read</span>
                <span>👁️ <?php echo (int)$post['view_count'] + 1; ?> views</span>
            </div>
        </div>

        <div class="post-content">
            <?php echo $post['content']; ?>
        </div>

        <!-- Share Buttons -->
        <div class="share-section">
            <h4>📢 Share this article</h4>
            <div class="share-btns">
                <?php
                $share_url  = APP_URL . '/blog/index.php?slug=' . rawurlencode($post['slug']);
                $share_text = urlencode('📖 ' . $post['title'] . ' — Read on ' . APP_NAME . ' Blog: ' . $share_url);
                ?>
                <a href="https://wa.me/?text=<?php echo $share_text; ?>" target="_blank" class="share-btn whatsapp">🟢 WhatsApp</a>
                <a href="https://t.me/share/url?url=<?php echo rawurlencode($share_url); ?>&text=<?php echo rawurlencode('📖 ' . $post['title']); ?>" target="_blank" class="share-btn telegram">📲 Telegram</a>
                <button class="share-btn copy" onclick="copyLink('<?php echo htmlspecialchars($share_url, ENT_QUOTES); ?>')">🔗 Copy Link</button>
            </div>
        </div>

        <?php if (!empty($related_posts)): ?>
        <div class="related-section">
            <h3>📚 Related Articles</h3>
            <div class="related-list">
                <?php foreach ($related_posts as $r): ?>
                <a href="<?php echo htmlspecialchars($blog_url . 'index.php?slug=' . rawurlencode($r['slug'])); ?>" class="related-card">
                    <div class="rc-title"><?php echo htmlspecialchars($r['title']); ?></div>
                    <div class="rc-meta">⏱️ <?php echo (int)$r['read_time_minutes']; ?> min &nbsp;•&nbsp; <?php echo date('d M Y', strtotime($r['published_at'])); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php elseif (!isset($post) || $slug === ''): ?>
    <!-- ── Blog Listing ────────────────────────────────────────────────────── -->

    <!-- Category Tabs -->
    <?php if (!empty($categories)): ?>
    <div class="category-tabs">
        <a href="<?php echo htmlspecialchars($blog_url . 'index.php'); ?>" class="cat-tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="<?php echo htmlspecialchars($blog_url . 'index.php?category=' . rawurlencode($cat)); ?>" class="cat-tab <?php echo $category_filter === $cat ? 'active' : ''; ?>"><?php echo htmlspecialchars(ucfirst($cat)); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>No articles found. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="blog-grid">
            <?php foreach ($posts as $p): ?>
            <div class="blog-card">
                <a href="<?php echo htmlspecialchars($blog_url . 'index.php?slug=' . rawurlencode($p['slug'])); ?>">
                    <span class="card-category"><?php echo htmlspecialchars(ucfirst($p['category'])); ?></span>
                    <div class="card-title"><?php echo htmlspecialchars($p['title']); ?></div>
                    <div class="card-excerpt"><?php echo htmlspecialchars(substr(strip_tags($p['excerpt'] ?? ''), 0, 150)); ?>...</div>
                    <div class="card-meta">
                        <span>⏱️ <?php echo (int)$p['read_time_minutes']; ?> min read</span>
                        <span>📅 <?php echo date('d M Y', strtotime($p['published_at'])); ?></span>
                        <span>👁️ <?php echo (int)$p['view_count']; ?></span>
                    </div>
                    <div class="card-read-more">Read Article →</div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Post not found -->
    <div class="empty-state">
        <div class="icon">🔍</div>
        <p>Article not found. <a href="<?php echo htmlspecialchars($blog_url); ?>" style="color:#667eea">Browse all articles →</a></p>
    </div>
<?php endif; ?>

</div>

<!-- Footer -->
<div class="blog-footer">
    <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?> — <a href="<?php echo htmlspecialchars(APP_URL); ?>/">Home</a> · <a href="<?php echo htmlspecialchars(APP_URL); ?>/user/">Dashboard</a> · <a href="<?php echo htmlspecialchars($blog_url); ?>">Blog</a></p>
</div>

<script>
function copyLink(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            alert('✅ Link copied!');
        });
    } else {
        var i = document.createElement('input');
        i.value = url;
        document.body.appendChild(i);
        i.select();
        document.execCommand('copy');
        document.body.removeChild(i);
        alert('✅ Link copied!');
    }
}
</script>
</body>
</html>
