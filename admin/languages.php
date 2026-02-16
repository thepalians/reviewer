<?php
/**
 * Language Management
 * Admin page to manage languages and translations
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/language-functions.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$page_title = 'Language Management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_language') {
        $code = $_POST['code'] ?? '';
        $isActive = (int)($_POST['is_active'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE languages SET is_active = ? WHERE code = ?");
        $stmt->bind_param('is', $isActive, $code);
        $stmt->execute();
        
        $success_message = 'Language status updated';
    }
}

// Get all languages
$languages = getActiveLanguages();
$allLanguages = $conn->query("SELECT * FROM languages ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get translation statistics
$translationStats = [];
foreach ($allLanguages as $lang) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM translations 
        WHERE language_code = ?
    ");
    $stmt->bind_param('s', $lang['code']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $translationStats[$lang['code']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ReviewFlow Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .language-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .language-card {
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: border-color 0.3s;
        }
        .language-card.active {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .language-card.inactive {
            border-color: #e5e7eb;
            background: #f9fafb;
            opacity: 0.7;
        }
        .language-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .native-name {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 15px;
        }
        .language-code {
            display: inline-block;
            padding: 4px 8px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .stat {
            margin: 10px 0;
            color: #666;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px 5px 5px 0;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <p>Manage available languages for your ReviewFlow platform. Enable or disable languages to control what users can select.</p>
        
        <div class="language-grid">
            <?php foreach ($allLanguages as $lang): ?>
                <div class="language-card <?php echo $lang['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="language-code"><?php echo strtoupper($lang['code']); ?></div>
                    <div class="language-name"><?php echo htmlspecialchars($lang['name']); ?></div>
                    <div class="native-name"><?php echo htmlspecialchars($lang['native_name']); ?></div>
                    
                    <div class="stat">
                        <strong>Translations:</strong> <?php echo $translationStats[$lang['code']] ?? 0; ?> strings
                    </div>
                    <div class="stat">
                        <strong>Status:</strong> 
                        <span style="color: <?php echo $lang['is_active'] ? '#10b981' : '#ef4444'; ?>">
                            <?php echo $lang['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_language">
                            <input type="hidden" name="code" value="<?php echo $lang['code']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $lang['is_active'] ? 0 : 1; ?>">
                            <button type="submit" class="btn <?php echo $lang['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo $lang['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        <a href="translations.php?lang=<?php echo $lang['code']; ?>" class="btn btn-primary">
                            Edit Translations
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
