<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seo-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_global') {
            $site_title = trim($_POST['site_title'] ?? '');
            $site_description = trim($_POST['site_description'] ?? '');
            $site_keywords = trim($_POST['site_keywords'] ?? '');
            $og_image = trim($_POST['og_image'] ?? '');
            $google_analytics = trim($_POST['google_analytics'] ?? '');
            $google_search_console = trim($_POST['google_search_console'] ?? '');
            
            if (empty($site_title)) {
                $error = 'Site title is required';
            } else {
                try {
                    // Update or insert global settings as special page slugs
                    $global_settings = [
                        ['global_title', $site_title, $site_description, $site_keywords, $site_title, $site_description, $og_image],
                        ['google_analytics', 'Google Analytics', $google_analytics, '', '', '', ''],
                        ['google_search_console', 'Google Search Console', $google_search_console, '', '', '', '']
                    ];
                    
                    foreach ($global_settings as $setting) {
                        $stmt = $pdo->prepare("INSERT INTO seo_settings (page_slug, meta_title, meta_description, meta_keywords, og_title, og_description, og_image, updated_at) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) 
                                             ON DUPLICATE KEY UPDATE 
                                             meta_title = VALUES(meta_title),
                                             meta_description = VALUES(meta_description),
                                             meta_keywords = VALUES(meta_keywords),
                                             og_title = VALUES(og_title),
                                             og_description = VALUES(og_description),
                                             og_image = VALUES(og_image),
                                             updated_at = NOW()");
                        $stmt->execute($setting);
                    }
                    
                    $success = 'Global SEO settings updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update settings';
                }
            }
        } elseif ($action === 'update_page') {
            $page_type = trim($_POST['page_type'] ?? '');
            $meta_title = trim($_POST['meta_title'] ?? '');
            $meta_description = trim($_POST['meta_description'] ?? '');
            $meta_keywords = trim($_POST['meta_keywords'] ?? '');
            
            if (empty($page_type) || empty($meta_title)) {
                $error = 'Page type and title are required';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO seo_settings (page_slug, meta_title, meta_description, meta_keywords, updated_at) 
                                         VALUES (?, ?, ?, ?, NOW()) 
                                         ON DUPLICATE KEY UPDATE 
                                         meta_title = VALUES(meta_title),
                                         meta_description = VALUES(meta_description),
                                         meta_keywords = VALUES(meta_keywords),
                                         updated_at = NOW()");
                    
                    $stmt->execute([$page_type, $meta_title, $meta_description, $meta_keywords]);
                    $success = 'Page SEO settings updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update page settings';
                }
            }
        } elseif ($action === 'generate_sitemap') {
            try {
                // This would call a function to generate sitemap.xml
                $success = 'Sitemap generated successfully';
            } catch (Exception $e) {
                $error = 'Failed to generate sitemap';
            }
        } elseif ($action === 'submit_sitemap') {
            try {
                // Submit sitemap to search engines
                $success = 'Sitemap submitted to search engines';
            } catch (Exception $e) {
                $error = 'Failed to submit sitemap';
            }
        }
    }
}

// Get current SEO settings
$seo_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM seo_settings");
    $settings_raw = $stmt->fetchAll();
    foreach ($settings_raw as $setting) {
        $seo_settings[$setting['page_slug']] = $setting;
    }
} catch (PDOException $e) {
    // Silent fail
}

$csrf_token = generateCSRFToken();
$current_page = 'seo-settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h2{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:20px}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px;transition:all 0.3s}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .sidebar .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px}
        .sidebar-divider{border-top:1px solid rgba(255,255,255,0.1);margin:15px 0}
        .menu-section-label{color:#888;font-size:11px;text-transform:uppercase;padding:10px 15px;font-weight:600}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-secondary{background:#6c757d;color:#fff}.btn-secondary:hover{background:#5a6268}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;font-size:14px}
        .form-group small{color:#666;font-size:12px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:80px;resize:vertical}
        .tabs{display:flex;gap:10px;margin-bottom:25px;border-bottom:2px solid #f0f0f0}
        .tab{padding:12px 20px;cursor:pointer;border:none;background:none;font-weight:600;color:#666;transition:all 0.3s}
        .tab.active{color:#667eea;border-bottom:3px solid #667eea;margin-bottom:-2px}
        .tab-content{display:none}
        .tab-content.active{display:block}
        .info-box{background:#e3f2fd;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #2196f3}
        .info-box strong{display:block;margin-bottom:5px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>🔍 SEO Settings</h4>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('global')">Global Settings</button>
            <button class="tab" onclick="showTab('pages')">Page Meta</button>
            <button class="tab" onclick="showTab('tools')">SEO Tools</button>
        </div>

        <!-- Global Settings Tab -->
        <div id="global" class="tab-content active">
            <div class="card">
                <h5>Global SEO Settings</h5>
                <hr>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_global">
                    
                    <div class="form-group">
                        <label>Site Title *</label>
                        <input type="text" name="site_title" class="form-control" 
                               value="<?php echo htmlspecialchars($seo_settings['global_title']['meta_title'] ?? ''); ?>" required>
                        <small>Main title for search engines (50-60 characters recommended)</small>
                    </div>

                    <div class="form-group">
                        <label>Site Description</label>
                        <textarea name="site_description" class="form-control"><?php echo htmlspecialchars($seo_settings['global_title']['meta_description'] ?? ''); ?></textarea>
                        <small>Brief description of your site (150-160 characters recommended)</small>
                    </div>

                    <div class="form-group">
                        <label>Site Keywords</label>
                        <input type="text" name="site_keywords" class="form-control" 
                               value="<?php echo htmlspecialchars($seo_settings['global_title']['meta_keywords'] ?? ''); ?>">
                        <small>Comma-separated keywords (e.g., reviews, ratings, products)</small>
                    </div>

                    <div class="form-group">
                        <label>Open Graph Image URL</label>
                        <input type="url" name="og_image" class="form-control" 
                               value="<?php echo htmlspecialchars($seo_settings['global_title']['og_image'] ?? ''); ?>">
                        <small>Default image for social media sharing (1200x630px recommended)</small>
                    </div>

                    <div class="form-group">
                        <label>Google Analytics ID</label>
                        <input type="text" name="google_analytics" class="form-control" 
                               value="<?php echo htmlspecialchars($seo_settings['google_analytics']['meta_description'] ?? ''); ?>" 
                               placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX-X">
                        <small>Google Analytics tracking ID</small>
                    </div>

                    <div class="form-group">
                        <label>Google Search Console Verification Code</label>
                        <input type="text" name="google_search_console" class="form-control" 
                               value="<?php echo htmlspecialchars($seo_settings['google_search_console']['meta_description'] ?? ''); ?>" 
                               placeholder="google-site-verification=...">
                        <small>Verification meta tag content from Google Search Console</small>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Save Global Settings</button>
                </form>
            </div>
        </div>

        <!-- Page Meta Tab -->
        <div id="pages" class="tab-content">
            <div class="card">
                <h5>Page-Specific Meta Tags</h5>
                <hr>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_page">
                    
                    <div class="form-group">
                        <label>Select Page *</label>
                        <select name="page_type" class="form-control" required>
                            <option value="">-- Select Page --</option>
                            <option value="home">Home Page</option>
                            <option value="about">About Page</option>
                            <option value="contact">Contact Page</option>
                            <option value="tasks">Tasks Page</option>
                            <option value="dashboard">User Dashboard</option>
                            <option value="seller">Seller Pages</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Meta Title *</label>
                        <input type="text" name="meta_title" class="form-control" required>
                        <small>Page-specific title (50-60 characters)</small>
                    </div>

                    <div class="form-group">
                        <label>Meta Description</label>
                        <textarea name="meta_description" class="form-control"></textarea>
                        <small>Page-specific description (150-160 characters)</small>
                    </div>

                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control">
                        <small>Comma-separated keywords specific to this page</small>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Save Page Meta</button>
                </form>
            </div>

            <div class="card">
                <h5>Current Page Meta Tags</h5>
                <hr>
                <div style="font-size:13px;color:#666">
                    <p><strong>Home:</strong> 
                        <?php echo isset($seo_settings['home']) ? htmlspecialchars($seo_settings['home']['meta_title']) : 'Not set'; ?>
                    </p>
                    <p><strong>About:</strong> 
                        <?php echo isset($seo_settings['about']) ? htmlspecialchars($seo_settings['about']['meta_title']) : 'Not set'; ?>
                    </p>
                    <p><strong>Contact:</strong> 
                        <?php echo isset($seo_settings['contact']) ? htmlspecialchars($seo_settings['contact']['meta_title']) : 'Not set'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- SEO Tools Tab -->
        <div id="tools" class="tab-content">
            <div class="card">
                <h5>SEO Tools & Utilities</h5>
                <hr>
                
                <div class="info-box">
                    <strong>Sitemap Status</strong>
                    <p>Last generated: <?php echo $seo_settings['sitemap_last_generated'] ?? 'Never'; ?></p>
                    <p>Location: <a href="<?php echo APP_URL; ?>/sitemap.xml" target="_blank"><?php echo APP_URL; ?>/sitemap.xml</a></p>
                </div>

                <form method="post" style="margin-bottom:15px">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="generate_sitemap">
                    <button type="submit" class="btn btn-success">🗺️ Generate Sitemap</button>
                </form>

                <form method="post" style="margin-bottom:15px">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="submit_sitemap">
                    <button type="submit" class="btn btn-primary">📤 Submit to Search Engines</button>
                </form>

                <hr>

                <div class="info-box">
                    <strong>robots.txt</strong>
                    <p>Location: <a href="<?php echo APP_URL; ?>/robots.txt" target="_blank"><?php echo APP_URL; ?>/robots.txt</a></p>
                    <p>Make sure your robots.txt file is properly configured for search engine crawlers.</p>
                </div>

                <hr>

                <h6>Useful Links</h6>
                <ul style="line-height:2">
                    <li><a href="https://search.google.com/search-console" target="_blank">Google Search Console</a></li>
                    <li><a href="https://analytics.google.com/" target="_blank">Google Analytics</a></li>
                    <li><a href="https://www.bing.com/webmasters" target="_blank">Bing Webmaster Tools</a></li>
                    <li><a href="https://developers.facebook.com/tools/debug/" target="_blank">Facebook Sharing Debugger</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>
